<?php

namespace App\Http\Controllers\Billing;

use App\Domain\Billing\Contracts\SubscriptionProvider;
use App\Domain\Billing\Enums\SubscriptionStatus;
use App\Domain\Billing\Models\BillingCapacityChange;
use App\Domain\Billing\Models\BillingInvoice;
use App\Domain\Billing\Models\BillingPlanPrice;
use App\Domain\Billing\Models\BillingRateCard;
use App\Domain\Billing\Models\OwnerRegistrationIntent;
use App\Domain\Billing\Models\SmsCreditPurchase;
use App\Domain\Billing\Services\BillingWorkspaceQuery;
use App\Domain\Billing\Services\CapacityPricingCatalog;
use App\Domain\Billing\Services\EntitlementEvaluator;
use App\Domain\Billing\Services\PlanCatalog;
use App\Domain\Billing\Services\SmsCreditWallet;
use App\Domain\Billing\Services\StripeBillingReadiness;
use App\Domain\Billing\Services\StripeCheckoutReconciler;
use App\Domain\Billing\Services\StripeSubscriptionReconciler;
use App\Domain\Billing\Services\SubscriptionLifecycleManager;
use App\Domain\PlatformAccess\Enums\PermissionName;
use App\Domain\PlatformAccess\Models\Business;
use App\Http\Controllers\Controller;
use App\Support\Audit\AuditWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Stripe\Exception\ApiErrorException;
use Throwable;

class BusinessBillingController extends Controller
{
    public function show(Request $request, Business $business, EntitlementEvaluator $entitlements, StripeBillingReadiness $readiness, BillingWorkspaceQuery $workspace): Response
    {
        $this->authorizeBilling($request, $business);
        $subscription = $business->subscription()->with(['plan', 'price'])->firstOrFail();
        $pendingChange = $subscription->changes()
            ->whereNull('applied_at')
            ->whereNull('superseded_at')
            ->whereIn('kind', ['scheduled_plan_change', 'over_limit_downgrade'])
            ->with('toPlan:id,name,code')
            ->latest('requested_at')
            ->first();
        $scheduledPrice = $subscription->scheduled_billing_plan_price_id ? BillingPlanPrice::with('plan:id,name')->find($subscription->scheduled_billing_plan_price_id) : null;

        return Inertia::render('Billing/Overview', [
            'capacityPricing' => app(CapacityPricingCatalog::class)->present(),
            'billingCountry' => app(CapacityPricingCatalog::class)->marketFor($business),
            'capacityRequest' => ($capacityRequest = BillingCapacityChange::where('business_id', $business->id)->whereIn('status', ['submitted', 'scheduled'])->latest('id')->first()) ? app(CapacityBillingController::class)->present($capacityRequest) : null,
            'smsCredits' => app(SmsCreditWallet::class)->balance($business->id),
            'smsPacks' => config('capacity-billing.sms_purchases_enabled') && $subscription->billing_rate_card_id ? collect(BillingRateCard::find($subscription->billing_rate_card_id)?->terms['sms_packs'] ?? [])->map(fn ($pack, $key) => ['key' => (string) $key, 'credits' => $pack['credits'], 'amount_minor' => $pack['amount_minor'], 'currency' => $subscription->capacity_snapshot['currency']])->values() : [],
            'smsPurchase' => is_string($request->query('sms_purchase')) ? SmsCreditPurchase::where('business_id', $business->id)->where('public_id', $request->query('sms_purchase'))->first()?->only(['public_id', 'status']) : null,
            'businessLabel' => $business->name,
            'subscription' => $workspace->subscription($subscription),
            'billingTimeZone' => $business->time_zone ?: 'UTC',
            'billingLocale' => $business->locale ?: 'en-US',
            'attention' => $workspace->attention($subscription),
            'usage' => collect(['locations.max', 'staff.max', 'messaging.monthly_allowance'])->mapWithKeys(fn ($key) => [$key => $entitlements->usage($business, $key)]),
            'trial' => ['started_at' => $subscription->trial_started_at, 'ends_at' => $subscription->trial_ends_at],
            'entitlements' => $workspace->entitlements($business, $subscription),
            'invoices' => $subscription->invoices()->orderByDesc('issued_at')->orderByDesc('id')->paginate(10)->withQueryString()->through(fn ($invoice) => $workspace->invoice($invoice)),
            'exportAvailable' => $subscription->exportIsAvailable(),
            'portalReturned' => $request->query('billing') === 'returned',
            'pendingChange' => $scheduledPrice ? ['plan_name' => $scheduledPrice->plan->name.' · '.($scheduledPrice->billing_interval->value === 'annual' ? 'yearly' : 'monthly'), 'effective_at' => $subscription->scheduled_change_at] : ($pendingChange ? [
                'kind' => $pendingChange->kind,
                'plan_name' => $pendingChange->toPlan->name,
                'effective_at' => $pendingChange->effective_at,
            ] : null),
            'checkoutStatus' => $request->query('checkout') === 'canceled' ? 'canceled' : null,
            'checkoutAttempt' => $this->checkoutAttemptFromRequest($request, $business),
            'checkoutReturn' => $this->capacityCheckoutReturn($request, $business),
            'planChangeStatus' => in_array($request->query('plan_change'), ['success', 'canceled'], true)
                ? $request->query('plan_change')
                : null,
            'planChangeTargetPriceId' => $request->query('plan_change') === 'success' && ctype_digit((string) $request->query('target_price_id'))
                ? (int) $request->query('target_price_id')
                : null,
            'billingReadiness' => $readiness->status(),
            'signupSelection' => OwnerRegistrationIntent::query()
                ->where('business_id', $business->getKey())
                ->first(['selected_plan_code', 'selected_billing_interval', 'selected_locations', 'selected_staff'])
                ?->only(['selected_plan_code', 'selected_billing_interval', 'selected_locations', 'selected_staff']),
        ]);
    }

    public function checkout(Request $request, Business $business): JsonResponse
    {
        $this->authorizeBilling($request, $business);

        return response()->json(['message' => 'Choose your subscription using locations and bookable staff.'], 410);
    }

    public function checkoutForm(Request $request, Business $business): RedirectResponse
    {
        $this->authorizeBilling($request, $business);

        return redirect()->route('business.billing.show', [$business, 'section' => 'plan']);
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

    public function changePlan(Request $request, Business $business): JsonResponse
    {
        return $this->checkout($request, $business);
    }

    public function planChangeStatus(Request $request, Business $business, StripeSubscriptionReconciler $reconciler, PlanCatalog $catalog): JsonResponse
    {
        $this->authorizeBilling($request, $business);
        $validated = $request->validate(['price_id' => ['required', 'integer']]);
        $price = BillingPlanPrice::query()
            ->whereKey($validated['price_id'])
            ->where('provider', 'stripe')
            ->with('plan:id,code,name')
            ->firstOrFail();
        abort_unless($catalog->allows($price), 422, 'The selected Stripe price is not in the approved billing catalog.');

        $subscription = $business->subscription()->with(['plan', 'price'])->firstOrFail();
        if ($subscription->billing_plan_price_id !== $price->getKey() && ! $subscription->billing_checked_at?->gt(now()->subSeconds(5))) {
            try {
                $subscription = $reconciler->reconcile($subscription);
            } catch (Throwable $exception) {
                Log::warning('Stripe plan change is awaiting provider synchronization.', [
                    'business_id' => $business->getKey(),
                    'target_price_id' => $price->getKey(),
                    'exception' => $exception::class,
                    'provider_code' => $exception instanceof ApiErrorException ? $exception->getStripeCode() : null,
                ]);
            }
        }

        $subscription->refresh();
        $confirmed = $subscription->billing_plan_price_id === $price->getKey();
        $scheduled = $subscription->scheduled_billing_plan_price_id === $price->getKey() && $subscription->scheduled_change_at?->isFuture();

        return response()->json([
            'status' => $confirmed ? 'confirmed' : ($scheduled ? 'scheduled' : 'processing'),
            'effective_at' => $scheduled ? $subscription->scheduled_change_at : null,
            'subscription_status' => $subscription->fresh()->status->value,
            'plan_name' => $subscription->fresh('plan')->plan->name,
            'billing_interval' => $subscription->fresh()->billing_interval?->value,
        ], $confirmed || $scheduled ? 200 : 202);
    }

    public function cancel(Request $request, Business $business, SubscriptionProvider $provider, SubscriptionLifecycleManager $lifecycle): JsonResponse
    {
        $this->authorizeBilling($request, $business);
        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:1000'], 'version' => ['nullable', 'integer', 'min:0']]);

        return DB::transaction(function () use ($request, $business, $provider, $lifecycle, $validated): JsonResponse {
            $subscription = $business->subscription()->lockForUpdate()->firstOrFail();
            if ($subscription->status === SubscriptionStatus::CancelScheduled) {
                return response()->json(['status' => $subscription->status->value]);
            }
            abort_if(isset($validated['version']) && $validated['version'] !== $subscription->version, 409, 'Your subscription changed. Refresh billing before trying again.');
            abort_unless($subscription->status === SubscriptionStatus::Active, 409, 'Only an active subscription can be scheduled for cancellation.');
            abort_unless($subscription->provider === 'stripe', 409, 'This subscription must be migrated to Stripe before it can be canceled here.');
            abort_unless(filled($subscription->provider_subscription_id), 409, 'A provider subscription is required to schedule cancellation.');
            abort_unless($subscription->current_period_ends_at?->isFuture(), 409, 'The paid access end date needs verification before cancellation. Refresh billing or contact support.');
            $provider->cancelAtPeriodEnd($subscription);

            return response()->json($lifecycle->scheduleCancellation($subscription, $request->user(), $validated['reason'] ?? 'Owner requested cancellation.'));
        }, 3);
    }

    public function reactivate(Request $request, Business $business, SubscriptionProvider $provider, SubscriptionLifecycleManager $lifecycle): JsonResponse
    {
        $this->authorizeBilling($request, $business);
        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:1000'], 'version' => ['nullable', 'integer', 'min:0']]);

        return DB::transaction(function () use ($request, $business, $provider, $lifecycle, $validated): JsonResponse {
            $subscription = $business->subscription()->lockForUpdate()->firstOrFail();
            if ($subscription->status === SubscriptionStatus::Active) {
                return response()->json(['status' => $subscription->status->value]);
            }
            abort_if(isset($validated['version']) && $validated['version'] !== $subscription->version, 409, 'Your subscription changed. Refresh billing before trying again.');
            abort_unless($subscription->status === SubscriptionStatus::CancelScheduled, 409, 'Only a scheduled cancellation can be reactivated.');
            abort_unless($subscription->provider === 'stripe', 409, 'This subscription must be migrated to Stripe before it can be reactivated here.');
            abort_unless(filled($subscription->provider_subscription_id), 409, 'A provider subscription is required to reactivate billing.');
            abort_unless($subscription->cancel_at?->isFuture(), 409, 'This subscription has already ended. Choose a plan to subscribe again.');
            $provider->reactivate($subscription);

            return response()->json($lifecycle->reactivate($subscription, $request->user(), $validated['reason'] ?? 'Owner requested continued renewal.'));
        }, 3);
    }

    public function portal(Request $request, Business $business, SubscriptionProvider $provider): RedirectResponse|JsonResponse
    {
        $this->authorizeBilling($request, $business);
        $subscription = $business->subscription()->firstOrFail();
        try {
            abort_unless($subscription->provider === 'stripe' && filled($subscription->provider_customer_id), 409, 'Start secure checkout before managing payment details.');
            $url = $provider->billingPortalUrl($subscription, route('business.billing.show', [$business, 'billing' => 'returned']));

            return $request->expectsJson() ? response()->json(['url' => $url]) : redirect()->away($url);
        } catch (Throwable $error) {
            Log::warning('Billing portal could not be opened.', ['business_id' => $business->id, 'exception' => $error::class]);
            $message = 'Payment details could not be opened. Your subscription is unchanged. Please try again shortly or contact support.';
            if (! $subscription->provider_customer_id) {
                $message = 'Start secure checkout before managing payment details.';
            }

            return $request->expectsJson()
                ? response()->json(['message' => $message], 503)
                : redirect()->route('business.billing.show', [$business, 'section' => 'overview'])->withErrors(['billing' => $message]);
        }
    }

    public function refreshBilling(Request $request, Business $business, StripeSubscriptionReconciler $reconciler, AuditWriter $audit): JsonResponse
    {
        $this->authorizeBilling($request, $business);
        $subscription = $business->subscription()->firstOrFail();
        abort_unless($subscription->provider === 'stripe' && filled($subscription->provider_customer_id), 409, 'No secure billing account has been set up yet.');
        if ($subscription->account_checked_at?->gt(now()->subSeconds(15))) {
            return response()->json(['status' => 'recently_checked']);
        }
        try {
            $before = $subscription->only(['payment_method_type', 'payment_method_last_four', 'payment_method_expiry_month', 'payment_method_expiry_year', 'billing_name', 'billing_email']);
            $updated = $reconciler->reconcileAccount($subscription);
            $paymentChanged = collect(['payment_method_type', 'payment_method_last_four', 'payment_method_expiry_month', 'payment_method_expiry_year'])->contains(fn ($field) => $before[$field] !== $updated->$field);
            $detailsChanged = collect(['billing_name', 'billing_email'])->contains(fn ($field) => $before[$field] !== $updated->$field);
            if ($paymentChanged) {
                $audit->write('subscription.payment_method.updated', $business, $request->user(), $subscription, 'Verified a secure payment-method update.', after: ['updated' => true], source: 'provider');
            }
            if ($detailsChanged) {
                $audit->write('subscription.billing_details.updated', $business, $request->user(), $subscription, 'Verified secure billing-detail changes.', after: ['updated' => true], source: 'provider');
            }
        } catch (Throwable $exception) {
            Log::warning('Billing account verification is pending.', ['business_id' => $business->id, 'exception' => $exception::class]);

            return response()->json(['message' => 'Billing details could not be verified yet. Your recorded details remain available. Please check again shortly.'], 503);
        }

        return response()->json(['status' => 'verified']);
    }

    public function invoice(Request $request, Business $business, string $invoice, BillingWorkspaceQuery $workspace): JsonResponse
    {
        $this->authorizeBilling($request, $business);
        $record = BillingInvoice::query()->where('business_id', $business->getKey())->where('public_id', $invoice)->firstOrFail();

        return response()->json($workspace->invoice($record) + [
            'line_items' => collect($record->line_items ?? [])->map(fn ($line) => [
                'description' => data_get($line, 'description', 'Subscription charge'),
                'amount_minor' => data_get($line, 'amount'),
                'period_started_at' => data_get($line, 'period.start'),
                'period_ends_at' => data_get($line, 'period.end'),
            ])->values(),
        ]);
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

    private function capacityCheckoutReturn(Request $request, Business $business): ?array
    {
        if ($request->query('checkout') !== 'success' || ! is_string($request->query('attempt'))) {
            return null;
        }
        $attempt = $business->billingCheckoutAttempts()->where('provider', 'stripe')
            ->where('public_id', $request->query('attempt'))->first();
        if (! $attempt || ! str_starts_with($attempt->provider_transaction_id, 'cs_')
            || $request->query('session_id') !== $attempt->provider_transaction_id) {
            return null;
        }
        $change = BillingCapacityChange::where('business_id', $business->id)
            ->where('business_subscription_id', $attempt->business_subscription_id)->where('kind', 'checkout')
            ->where('public_id', $attempt->capacity_quote['capacity_change_id'] ?? null)->first();

        // The redirect is a navigation hint. Only stored, verified payment state
        // can announce success; pending returns are checked by the workspace.
        return $change ? ['status' => $attempt->status, 'request' => app(CapacityBillingController::class)->present($change)] : null;
    }
}
