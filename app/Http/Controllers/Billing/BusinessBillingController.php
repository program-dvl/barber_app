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
use App\Domain\Billing\Models\BusinessSubscription;
use App\Domain\Billing\Models\OwnerRegistrationIntent;
use App\Domain\Billing\Services\EntitlementEvaluator;
use App\Domain\Billing\Services\PlanCatalog;
use App\Domain\Billing\Services\StripeBillingReadiness;
use App\Domain\Billing\Services\StripeCheckoutReconciler;
use App\Domain\Billing\Services\SubscriptionLifecycleManager;
use App\Domain\PlatformAccess\Enums\PermissionName;
use App\Domain\PlatformAccess\Models\Business;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Stripe\Exception\ApiErrorException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class BusinessBillingController extends Controller
{
    public function show(Request $request, Business $business, EntitlementEvaluator $entitlements, PlanCatalog $catalog, StripeBillingReadiness $readiness): Response
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
                ->whereIn('code', $catalog->codes())
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
                ->orderBy('id')
                ->get(),
            'planRanks' => $catalog->plans()->map(fn (array $plan) => (int) ($plan['rank'] ?? 0))->all(),
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
            'checkoutAttempt' => $this->checkoutAttemptFromRequest($request, $business),
            'billingReadiness' => $readiness->status(),
            'signupSelection' => OwnerRegistrationIntent::query()
                ->where('business_id', $business->getKey())
                ->first(['selected_plan_code', 'selected_billing_interval'])
                ?->only(['selected_plan_code', 'selected_billing_interval']),
        ]);
    }

    public function checkout(Request $request, Business $business, SubscriptionProvider $provider, EntitlementEvaluator $entitlements, PlanCatalog $catalog, StripeBillingReadiness $readiness): JsonResponse
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
            ->where('effective_from', '<=', now())
            ->where(fn ($effective) => $effective->whereNull('effective_until')->orWhere('effective_until', '>', now()))
            ->with('plan:id,code')
            ->firstOrFail();
        abort_unless($catalog->allows($price), 422, 'The selected Stripe price is not in the approved billing catalog.');
        $couponProviderId = null;

        if (filled($validated['coupon'] ?? null)) {
            $coupon = BillingCoupon::query()->where('code', str($validated['coupon'])->upper())->firstOrFail();
            abort_unless($coupon->provider === 'stripe', 422, 'Coupon is not configured for Stripe.');
            abort_unless($coupon->isRedeemable(), 422, 'Coupon is not redeemable.');
            abort_unless(filled($coupon->provider_coupon_id), 422, 'Coupon is not configured with the subscription provider.');
            $couponProviderId = $coupon->provider_coupon_id;
        }

        abort_unless(config('billing.provider') === 'stripe' && $readiness->status()['checkout_ready'], 503, 'Secure subscription activation is temporarily unavailable. Please contact support before attempting payment.');
        abort_if(
            filled($subscription->provider_subscription_id)
                && in_array($subscription->status, [SubscriptionStatus::PastDue, SubscriptionStatus::Grace, SubscriptionStatus::Restricted], true),
            409,
            'Use billing recovery to update the payment method for this subscription.',
        );
        abort_if(
            filled($subscription->provider_subscription_id) && $subscription->provider !== 'stripe',
            409,
            'This account requires a supported provider migration before starting Stripe checkout.',
        );

        do {
            $reservation = $this->reserveCheckoutAttempt($subscription, $price, $request->user());

            if ($reservation['state'] === 'reuse') {
                return response()->json([
                    'url' => $reservation['attempt']->provider_checkout_url,
                    'attempt_id' => $reservation['attempt']->public_id,
                ]);
            }

            abort_if(
                $reservation['state'] === 'preparing',
                409,
                'A secure checkout is already being prepared for this account. Please wait a moment and try again.',
            );

            if ($reservation['state'] === 'competing') {
                foreach ($reservation['attempts'] as $competingAttempt) {
                    $provider->expireCheckout($competingAttempt);
                }
                $subscription->checkoutAttempts()
                    ->whereKey($reservation['attempts']->modelKeys())
                    ->whereIn('status', ['pending', 'processing'])
                    ->update(['status' => 'superseded', 'expires_at' => now()]);
            }
        } while ($reservation['state'] === 'competing');

        $attempt = $reservation['attempt'];

        try {
            $checkout = $provider->createCheckout(
                $business,
                $price,
                $attempt,
                route('business.billing.show', $business).'?checkout=success&attempt='.$attempt->public_id.'&session_id={CHECKOUT_SESSION_ID}',
                route('business.billing.show', $business).'?checkout=canceled',
                $couponProviderId,
            );
        } catch (Throwable $exception) {
            $attempt->update(['status' => 'failed', 'last_error' => 'stripe_checkout_unavailable', 'expires_at' => now()]);
            if (! $exception instanceof HttpExceptionInterface || $exception->getStatusCode() < 500) {
                throw $exception;
            }

            return response()->json(['message' => $exception->getMessage()], $exception->getStatusCode());
        }

        $attempt->update([
            'provider_transaction_id' => $checkout['provider_session_id'],
            'provider_checkout_url' => $checkout['url'],
        ]);

        return response()->json([
            'url' => $checkout['url'],
            'attempt_id' => $attempt->public_id,
        ], 201);
    }

    /**
     * Reserve one local attempt under the subscription row lock before making
     * the remote Stripe call. A concurrent request sees the pending reservation
     * and cannot create a second hosted Checkout Session.
     *
     * @return array{state: 'reuse'|'preparing'|'competing'|'created', attempt?: BillingCheckoutAttempt, attempts?: Collection<int, BillingCheckoutAttempt>}
     */
    private function reserveCheckoutAttempt(BusinessSubscription $subscription, BillingPlanPrice $price, User $actor): array
    {
        return DB::transaction(function () use ($subscription, $price, $actor): array {
            $locked = BusinessSubscription::query()->lockForUpdate()->findOrFail($subscription->getKey());
            $openAttempts = $locked->checkoutAttempts()
                ->where('provider', 'stripe')
                ->whereIn('status', ['pending', 'processing'])
                ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->oldest('created_at')
                ->get();
            $reusable = $openAttempts->first(fn (BillingCheckoutAttempt $attempt): bool => $attempt->billing_plan_price_id === $price->getKey()
                && filled($attempt->provider_checkout_url));

            if ($reusable) {
                return ['state' => 'reuse', 'attempt' => $reusable];
            }

            if ($openAttempts->contains(fn (BillingCheckoutAttempt $attempt): bool => blank($attempt->provider_checkout_url))) {
                return ['state' => 'preparing'];
            }

            if ($openAttempts->isNotEmpty()) {
                return ['state' => 'competing', 'attempts' => $openAttempts];
            }

            $attemptPublicId = (string) Str::ulid();

            return [
                'state' => 'created',
                'attempt' => BillingCheckoutAttempt::query()->create([
                    'public_id' => $attemptPublicId,
                    'business_id' => $locked->business_id,
                    'business_subscription_id' => $locked->getKey(),
                    'billing_plan_price_id' => $price->getKey(),
                    'created_by_user_id' => $actor->getKey(),
                    'provider' => 'stripe',
                    'provider_transaction_id' => 'pending_'.$attemptPublicId,
                    'status' => 'pending',
                    'expires_at' => now()->addHours(2),
                ]),
            ];
        }, 3);
    }

    public function checkoutForm(Request $request, Business $business, EntitlementEvaluator $entitlements, PlanCatalog $catalog, StripeBillingReadiness $readiness): Response
    {
        $this->authorizeBilling($request, $business);
        $entitlements->authorize($business, 'billing.manage', 'billing');
        abort_unless(config('billing.provider') === 'stripe', 409, 'Stripe subscription checkout is not enabled.');

        $validated = $request->validate(['price_id' => ['required', 'integer']]);
        $price = BillingPlanPrice::query()
            ->whereKey($validated['price_id'])
            ->where('provider', 'stripe')
            ->where('is_active', true)
            ->where('effective_from', '<=', now())
            ->where(fn ($effective) => $effective->whereNull('effective_until')->orWhere('effective_until', '>', now()))
            ->with(['plan.entitlements' => fn ($query) => $query
                ->where('effective_from', '<=', now())
                ->where(fn ($effective) => $effective->whereNull('effective_until')->orWhere('effective_until', '>', now()))
                ->with('definition')])
            ->firstOrFail();
        abort_unless($catalog->allows($price), 422, 'The selected Stripe price is not in the approved billing catalog.');
        $attempt = $business->billingCheckoutAttempts()
            ->where('provider', 'stripe')
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
            'stripe' => $readiness->status(),
            'checkoutAttempt' => $attempt ? [
                'attempt_id' => $attempt->public_id,
                'status' => $attempt->status,
            ] : null,
            'termsUrl' => route('terms.show'),
            'privacyUrl' => route('policy.show'),
        ]);
    }

    public function checkoutStatus(Request $request, Business $business, StripeCheckoutReconciler $reconciler): JsonResponse
    {
        $this->authorizeBilling($request, $business);
        $validated = $request->validate([
            'attempt_id' => ['required', 'string', 'max:26', 'regex:/^[0-9A-HJKMNP-TV-Z]{26}$/i'],
        ]);
        $attempt = $business->billingCheckoutAttempts()
            ->where('provider', 'stripe')
            ->where('public_id', $validated['attempt_id'])
            ->firstOrFail();

        if (in_array($attempt->status, ['pending', 'processing'], true)
            && (! $attempt->last_checked_at || $attempt->last_checked_at->lte(now()->subSeconds(5)))) {
            try {
                $attempt = $reconciler->reconcile($attempt);
            } catch (Throwable $exception) {
                $attempt->update(['last_checked_at' => now()]);
                Log::warning('Stripe Checkout reconciliation remains pending.', [
                    'business_id' => $business->getKey(),
                    'checkout_attempt_id' => $attempt->getKey(),
                    'exception' => $exception::class,
                    'provider_code' => $exception instanceof ApiErrorException ? $exception->getStripeCode() : null,
                ]);
            }
        }

        $subscription = $business->subscription()->with('plan:id,name,code')->firstOrFail();

        return response()->json([
            'status' => $attempt->status,
            'subscription_status' => $subscription->status->value,
            'plan_name' => $subscription->plan->name,
            'confirmed_at' => $attempt->confirmed_at,
        ]);
    }

    public function changePlan(Request $request, Business $business, SubscriptionProvider $provider, SubscriptionLifecycleManager $lifecycle, PlanCatalog $catalog, StripeBillingReadiness $readiness): JsonResponse
    {
        $this->authorizeBilling($request, $business);
        $validated = $request->validate([
            'price_id' => ['required', 'integer'],
            'timing' => ['required', Rule::in(['immediate', 'period_end'])],
            'reason' => ['required', 'string', 'max:1000'],
        ]);
        $subscription = $business->subscription()->with(['plan', 'price'])->firstOrFail();
        abort_unless(filled($subscription->provider_subscription_id), 409, 'Complete a subscription checkout before changing plans.');
        abort_unless($subscription->provider === 'stripe', 409, 'This subscription must be migrated to Stripe before it can be changed.');
        abort_unless($subscription->status === SubscriptionStatus::Active, 409, 'Resolve the current billing status before changing plans.');
        abort_unless($readiness->status()['checkout_ready'], 503, 'Plan changes are paused until secure Stripe event delivery is configured. Your current subscription is unchanged.');
        abort_if(
            $subscription->changes()->whereNull('applied_at')->whereNull('superseded_at')->exists(),
            409,
            'A plan change is already being processed. Wait for Stripe confirmation before requesting another change.',
        );

        $price = BillingPlanPrice::query()
            ->whereKey($validated['price_id'])
            ->where('provider', config('billing.provider'))
            ->where('is_active', true)
            ->where('effective_from', '<=', now())
            ->where(fn ($effective) => $effective->whereNull('effective_until')->orWhere('effective_until', '>', now()))
            ->with('plan')
            ->firstOrFail();
        abort_unless($catalog->allows($price), 422, 'The selected Stripe price is not in the approved billing catalog.');
        abort_if($subscription->billing_plan_price_id === $price->getKey(), 422, 'This is already your current plan and billing interval.');

        $isDowngrade = $catalog->isDowngrade($subscription->plan->code, $price->plan->code);
        $annualToMonthly = $subscription->billing_interval === BillingInterval::Annual
            && $price->billing_interval === BillingInterval::Monthly;
        $mustWait = $isDowngrade || $annualToMonthly;
        abort_if($mustWait && $validated['timing'] !== 'period_end', 422, 'This change must be scheduled for the end of the current billing period.');
        $atPeriodEnd = $lifecycle->requiresPeriodEnd($subscription, $price, $validated['timing'] === 'period_end' || $mustWait);
        $change = $lifecycle->requestPlanChange(
            $subscription,
            $price,
            $request->user(),
            $validated['reason'],
            $atPeriodEnd,
            awaitProvider: true,
        );
        try {
            $provider->changePrice($subscription, $price, $atPeriodEnd);
        } catch (Throwable $exception) {
            $lifecycle->supersedePlanChange($change, $request->user(), 'Stripe rejected or could not complete the requested change.');
            throw $exception;
        }

        return response()->json([
            'change_id' => $change->public_id,
            'status' => $atPeriodEnd ? 'scheduled' : 'processing',
            'message' => $atPeriodEnd
                ? 'Your plan change is scheduled for the next renewal.'
                : 'Stripe accepted the upgrade. Signed confirmation is synchronizing your access.',
        ], 202);
    }

    public function cancel(Request $request, Business $business, SubscriptionProvider $provider, SubscriptionLifecycleManager $lifecycle): JsonResponse
    {
        $this->authorizeBilling($request, $business);
        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $subscription = $business->subscription()->firstOrFail();
        abort_unless($subscription->status === SubscriptionStatus::Active, 409, 'Only an active subscription can be scheduled for cancellation.');
        abort_unless($subscription->provider === 'stripe', 409, 'This subscription must be migrated to Stripe before it can be canceled here.');
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
        abort_unless($subscription->provider === 'stripe', 409, 'This subscription must be migrated to Stripe before it can be reactivated here.');
        abort_unless(filled($subscription->provider_subscription_id), 409, 'A provider subscription is required to reactivate billing.');
        $provider->reactivate($subscription);

        return response()->json($lifecycle->reactivate($subscription, $request->user(), $validated['reason']));
    }

    public function portal(Request $request, Business $business, SubscriptionProvider $provider): RedirectResponse
    {
        $this->authorizeBilling($request, $business);
        $subscription = $business->subscription()->firstOrFail();
        abort_unless($subscription->provider === 'stripe' && filled($subscription->provider_customer_id), 409, 'Complete Stripe checkout before opening the billing portal.');
        $url = $provider->billingPortalUrl($subscription, route('business.billing.show', $business));

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

    private function checkoutAttemptFromRequest(Request $request, Business $business): ?array
    {
        $attemptId = $request->query('attempt');
        $attempt = is_string($attemptId) && preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/i', $attemptId)
            ? $business->billingCheckoutAttempts()
                ->where('provider', 'stripe')
                ->where('public_id', $attemptId)
                ->first()
            : $business->billingCheckoutAttempts()
                ->where('provider', 'stripe')
                ->whereIn('status', ['pending', 'processing'])
                ->where('created_at', '>=', now()->subDays(7))
                ->latest('created_at')
                ->first();

        return $attempt ? [
            'attempt_id' => $attempt->public_id,
            'status' => $attempt->status,
            'confirmed_at' => $attempt->confirmed_at,
        ] : null;
    }
}
