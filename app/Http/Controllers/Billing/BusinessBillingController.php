<?php

namespace App\Http\Controllers\Billing;

use App\Domain\Billing\Contracts\SubscriptionProvider;
use App\Domain\Billing\Enums\BillingInterval;
use App\Domain\Billing\Enums\SubscriptionStatus;
use App\Domain\Billing\Models\BillingCheckoutAttempt;
use App\Domain\Billing\Models\BillingCoupon;
use App\Domain\Billing\Models\BillingInvoice;
use App\Domain\Billing\Models\BillingPlan;
use App\Domain\Billing\Models\BillingPlanPrice;
use App\Domain\Billing\Models\OwnerRegistrationIntent;
use App\Domain\Billing\Services\EntitlementEvaluator;
use App\Domain\Billing\Services\PaddleCheckoutReconciler;
use App\Domain\Billing\Services\SubscriptionLifecycleManager;
use App\Domain\PlatformAccess\Enums\PermissionName;
use App\Domain\PlatformAccess\Models\Business;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class BusinessBillingController extends Controller
{
    public function show(Request $request, Business $business, EntitlementEvaluator $entitlements): Response
    {
        $this->authorizeBilling($request, $business);
        $subscription = $business->subscription()->with(['plan', 'price'])->firstOrFail();
        $pendingChange = $subscription->changes()
            ->whereNull('applied_at')
            ->whereNull('superseded_at')
            ->with('toPlan:id,name,code')
            ->latest('requested_at')
            ->first();
        $now = now();

        return Inertia::render('Billing/Overview', [
            'businessLabel' => $business->name,
            'subscription' => $subscription,
            'trial' => ['started_at' => $subscription->trial_started_at, 'ends_at' => $subscription->trial_ends_at],
            'plans' => BillingPlan::query()
                ->whereIn('code', ['starter', 'pro'])
                ->where('is_active', true)
                ->with([
                    'prices' => fn ($query) => $query
                        ->where('provider', config('billing.provider'))
                        ->where('is_active', true)
                        ->where('effective_from', '<=', $now)
                        ->where(fn ($effective) => $effective->whereNull('effective_until')->orWhere('effective_until', '>', $now)),
                    'entitlements' => fn ($query) => $query
                        ->where('effective_from', '<=', $now)
                        ->where(fn ($effective) => $effective->whereNull('effective_until')->orWhere('effective_until', '>', $now))
                        ->with('definition'),
                ])
                ->orderByRaw("case code when 'starter' then 1 when 'pro' then 2 else 3 end")
                ->get(),
            'entitlements' => collect(['locations.max', 'staff.max', 'messaging.monthly_allowance', 'deposits.enabled', 'inventory.enabled', 'reporting.advanced', 'branding.custom', 'support.priority', 'exports.enabled'])
                ->mapWithKeys(fn (string $key) => [$key => $entitlements->value($business, $key)]),
            'invoices' => $subscription->invoices()->latest('issued_at')->get(),
            'payments' => $subscription->invoices()->with('payments')->latest('issued_at')->get()->pluck('payments')->flatten()->values(),
            'exportAvailable' => $subscription->exportIsAvailable(),
            'pendingChange' => $pendingChange ? [
                'kind' => $pendingChange->kind,
                'plan_name' => $pendingChange->toPlan->name,
                'effective_at' => $pendingChange->effective_at,
            ] : null,
            'checkoutStatus' => in_array($request->query('checkout'), ['success', 'canceled'], true)
                ? $request->query('checkout')
                : null,
            'checkoutRecovery' => config('billing.provider') === 'paddle'
                && $subscription->status === SubscriptionStatus::Trialing
                && (filled($subscription->provider_customer_id)
                    || $subscription->checkoutAttempts()->whereIn('status', ['pending', 'processing'])->exists()),
            'signupSelection' => OwnerRegistrationIntent::query()
                ->where('business_id', $business->getKey())
                ->first(['selected_plan_code', 'selected_billing_interval'])
                ?->only(['selected_plan_code', 'selected_billing_interval']),
        ]);
    }

    public function checkout(Request $request, Business $business, SubscriptionProvider $provider, EntitlementEvaluator $entitlements): JsonResponse
    {
        $this->authorizeBilling($request, $business);
        $entitlements->authorize($business, 'billing.manage', 'billing');
        $validated = $request->validate(['price_id' => ['required', 'integer'], 'coupon' => ['nullable', 'string', 'max:64']]);
        $subscription = $business->subscription()->firstOrFail();
        abort_if(filled($subscription->provider_subscription_id) && in_array($subscription->status, [SubscriptionStatus::Active, SubscriptionStatus::CancelScheduled], true), 409, 'This billing account already has a paid subscription. Use the plan-change controls instead.');
        $price = BillingPlanPrice::query()
            ->whereKey($validated['price_id'])
            ->where('is_active', true)
            ->where('provider', config('billing.provider'))
            ->firstOrFail();
        $couponProviderId = null;

        if (filled($validated['coupon'] ?? null)) {
            $coupon = BillingCoupon::query()->where('code', str($validated['coupon'])->upper())->firstOrFail();
            abort_unless($coupon->isRedeemable(), 422, 'Coupon is not redeemable.');
            abort_unless(filled($coupon->provider_coupon_id), 422, 'Coupon is not configured with the subscription provider.');
            $couponProviderId = $coupon->provider_coupon_id;
        }

        $clientSideToken = trim((string) config('billing.paddle.client_side_token'));
        abort_unless(
            config('billing.provider') === 'paddle'
                && $clientSideToken !== ''
                && ! str_starts_with($clientSideToken, 'your-paddle-'),
            503,
            'Secure checkout is not configured yet. Please contact support.',
        );

        $existingAttempt = $subscription->checkoutAttempts()
            ->where('provider', 'paddle')
            ->where('billing_plan_price_id', $price->getKey())
            ->whereIn('status', ['pending', 'processing'])
            ->where('expires_at', '>', now())
            ->latest('created_at')
            ->first();
        if ($existingAttempt) {
            return response()->json([
                'transaction_id' => $existingAttempt->provider_transaction_id,
                'client_side_token' => $clientSideToken,
                'environment' => config('billing.paddle.sandbox') ? 'sandbox' : 'production',
                'success_url' => route('business.billing.show', $business).'?checkout=success',
            ]);
        }

        try {
            $checkout = $provider->createCheckout(
                $business,
                $price,
                route('business.billing.show', $business).'?checkout=success',
                route('business.billing.show', $business).'?checkout=canceled',
                $couponProviderId,
            );
        } catch (HttpExceptionInterface $exception) {
            if ($exception->getStatusCode() < 500) {
                throw $exception;
            }

            return response()->json(['message' => $exception->getMessage()], $exception->getStatusCode());
        }

        BillingCheckoutAttempt::query()->create([
            'business_id' => $business->getKey(),
            'business_subscription_id' => $subscription->getKey(),
            'billing_plan_price_id' => $price->getKey(),
            'created_by_user_id' => $request->user()->getKey(),
            'provider' => 'paddle',
            'provider_transaction_id' => $checkout['provider_session_id'],
            'status' => 'pending',
            'expires_at' => now()->addHours(2),
        ]);

        return response()->json([
            'transaction_id' => $checkout['provider_session_id'],
            'client_side_token' => $clientSideToken,
            'environment' => config('billing.paddle.sandbox') ? 'sandbox' : 'production',
            'success_url' => route('business.billing.show', $business).'?checkout=success',
        ], 201);
    }

    public function checkoutForm(Request $request, Business $business, EntitlementEvaluator $entitlements): Response
    {
        $this->authorizeBilling($request, $business);
        $entitlements->authorize($business, 'billing.manage', 'billing');
        abort_unless(config('billing.provider') === 'paddle', 409, 'Inline subscription checkout is available for Paddle billing only.');

        $validated = $request->validate(['price_id' => ['required', 'integer']]);
        $price = BillingPlanPrice::query()
            ->whereKey($validated['price_id'])
            ->where('provider', 'paddle')
            ->where('is_active', true)
            ->where('effective_from', '<=', now())
            ->where(fn ($effective) => $effective->whereNull('effective_until')->orWhere('effective_until', '>', now()))
            ->with(['plan.entitlements' => fn ($query) => $query
                ->where('effective_from', '<=', now())
                ->where(fn ($effective) => $effective->whereNull('effective_until')->orWhere('effective_until', '>', now()))
                ->with('definition')])
            ->firstOrFail();
        $attempt = $business->billingCheckoutAttempts()
            ->where('provider', 'paddle')
            ->where('billing_plan_price_id', $price->getKey())
            ->whereIn('status', ['pending', 'processing', 'confirmed'])
            ->latest('created_at')
            ->first();

        return Inertia::render('Billing/Checkout', [
            'businessLabel' => $business->name,
            'billingContact' => [
                'name' => $request->user()->name,
                'email' => $request->user()->email,
            ],
            'price' => [
                'id' => $price->getKey(),
                'amount_minor' => $price->amount_minor,
                'currency' => $price->currency,
                'billing_interval' => $price->billing_interval->value,
                'plan' => [
                    'name' => $price->plan->name,
                    'description' => $price->plan->description,
                    'entitlements' => $price->plan->entitlements->mapWithKeys(
                        fn ($entitlement) => [$entitlement->definition->key => $entitlement->value],
                    ),
                ],
            ],
            'paddle' => [
                'configured' => filled(config('billing.paddle.client_side_token'))
                    && ! str_starts_with((string) config('billing.paddle.client_side_token'), 'your-paddle-'),
                'environment' => config('billing.paddle.sandbox') ? 'sandbox' : 'production',
            ],
            'checkoutAttempt' => $attempt ? [
                'transaction_id' => $attempt->provider_transaction_id,
                'status' => $attempt->status,
            ] : null,
            'termsUrl' => route('terms.show'),
            'privacyUrl' => route('policy.show'),
        ]);
    }

    public function confirmCheckout(Request $request, Business $business, PaddleCheckoutReconciler $reconciler): JsonResponse
    {
        $this->authorizeBilling($request, $business);
        $validated = $request->validate([
            'transaction_id' => ['nullable', 'string', 'max:64', 'regex:/^txn_[a-z0-9]+$/'],
        ]);

        return response()->json($reconciler->reconcile($business, $validated['transaction_id'] ?? null, $request->user()));
    }

    public function changePlan(Request $request, Business $business, SubscriptionProvider $provider, SubscriptionLifecycleManager $lifecycle): JsonResponse
    {
        $this->authorizeBilling($request, $business);
        $validated = $request->validate([
            'price_id' => ['required', 'integer'],
            'timing' => ['required', Rule::in(['immediate', 'period_end'])],
            'reason' => ['required', 'string', 'max:1000'],
        ]);
        $subscription = $business->subscription()->with(['plan', 'price'])->firstOrFail();
        abort_unless(filled($subscription->provider_subscription_id), 409, 'Complete a subscription checkout before changing plans.');
        abort_unless($subscription->status === SubscriptionStatus::Active, 409, 'Resolve the current billing status before changing plans.');

        $price = BillingPlanPrice::query()
            ->whereKey($validated['price_id'])
            ->where('provider', config('billing.provider'))
            ->where('is_active', true)
            ->where('effective_from', '<=', now())
            ->where(fn ($effective) => $effective->whereNull('effective_until')->orWhere('effective_until', '>', now()))
            ->with('plan')
            ->firstOrFail();
        abort_if($subscription->billing_plan_price_id === $price->getKey(), 422, 'This is already your current plan and billing interval.');

        $rank = ['trial' => 0, 'starter' => 1, 'pro' => 2];
        $isDowngrade = ($rank[$price->plan->code] ?? 0) < ($rank[$subscription->plan->code] ?? 0);
        $annualToMonthly = $subscription->billing_interval === BillingInterval::Annual
            && $price->billing_interval === BillingInterval::Monthly;
        abort_if($annualToMonthly, 422, 'A switch from annual to monthly billing can be made after the current annual term ends.');

        $atPeriodEnd = $lifecycle->requiresPeriodEnd($subscription, $price, $isDowngrade);
        abort_if($atPeriodEnd && $subscription->billing_interval !== $price->billing_interval, 422, 'Select the current billing interval to schedule this downgrade.');
        abort_if(($validated['timing'] === 'period_end') !== $atPeriodEnd, 422, $atPeriodEnd
            ? 'This downgrade must be scheduled for the end of the current billing period.'
            : 'This upgrade is applied immediately with provider-calculated proration.');
        $provider->changePrice($subscription, $price, $atPeriodEnd);
        $change = $lifecycle->requestPlanChange(
            $subscription,
            $price,
            $request->user(),
            $validated['reason'],
            $atPeriodEnd,
        );

        return response()->json($change, 202);
    }

    public function cancel(Request $request, Business $business, SubscriptionProvider $provider, SubscriptionLifecycleManager $lifecycle): JsonResponse
    {
        $this->authorizeBilling($request, $business);
        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $subscription = $business->subscription()->firstOrFail();
        abort_unless($subscription->status === SubscriptionStatus::Active, 409, 'Only an active subscription can be scheduled for cancellation.');
        abort_unless(filled($subscription->provider_subscription_id), 409, 'A provider subscription is required to schedule cancellation.');
        $provider->cancelAtPeriodEnd($subscription);

        return response()->json($lifecycle->scheduleCancellation($subscription, $request->user(), $validated['reason']));
    }

    public function reactivate(Request $request, Business $business, SubscriptionProvider $provider, SubscriptionLifecycleManager $lifecycle): JsonResponse
    {
        $this->authorizeBilling($request, $business);
        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $subscription = $business->subscription()->firstOrFail();
        abort_unless($subscription->status === SubscriptionStatus::CancelScheduled, 409, 'Only a scheduled cancellation can be reactivated.');
        abort_unless(filled($subscription->provider_subscription_id), 409, 'A provider subscription is required to reactivate billing.');
        $provider->reactivate($subscription);

        return response()->json($lifecycle->reactivate($subscription, $request->user(), $validated['reason']));
    }

    public function portal(Request $request, Business $business, SubscriptionProvider $provider): RedirectResponse
    {
        $this->authorizeBilling($request, $business);
        $url = $provider->billingPortalUrl($business->subscription()->firstOrFail(), route('business.billing.show', $business));

        return redirect()->away($url);
    }

    public function invoice(Request $request, Business $business, string $invoice): JsonResponse
    {
        $this->authorizeBilling($request, $business);
        $record = BillingInvoice::query()->where('business_id', $business->getKey())->where('public_id', $invoice)->firstOrFail();

        return response()->json($record->load('payments'));
    }

    private function authorizeBilling(Request $request, Business $business): void
    {
        abort_unless($request->user()?->can(PermissionName::BillingManage->value) && $request->user()->can('view', $business), 403);
    }
}
