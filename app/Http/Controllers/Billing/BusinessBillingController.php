<?php

namespace App\Http\Controllers\Billing;

use App\Domain\Billing\Contracts\SubscriptionProvider;
use App\Domain\Billing\Enums\SubscriptionStatus;
use App\Domain\Billing\Models\BillingCapacityChange;
use App\Domain\Billing\Models\BillingCheckoutAttempt;
use App\Domain\Billing\Models\BillingCoupon;
use App\Domain\Billing\Models\BillingInvoice;
use App\Domain\Billing\Models\BillingPlan;
use App\Domain\Billing\Models\BillingPlanPrice;
use App\Domain\Billing\Models\BillingRateCard;
use App\Domain\Billing\Models\BusinessSubscription;
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
use App\Models\User;
use App\Support\Audit\AuditWriter;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Stripe\Exception\ApiErrorException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class BusinessBillingController extends Controller
{
    public function show(Request $request, Business $business, EntitlementEvaluator $entitlements, PlanCatalog $catalog, StripeBillingReadiness $readiness, BillingWorkspaceQuery $workspace): Response
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
        $now = now();

        return Inertia::render('Billing/Overview', [
            'capacityPricing' => app(CapacityPricingCatalog::class)->present(true),
            'billingCountry' => $business->country_code,
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
                ->get()->map(function ($plan) use ($catalog) {
                    return $plan->only(['id', 'code', 'name', 'description']) + [
                        'prices' => $plan->prices->filter(fn ($price) => $catalog->allows($price))->map(fn ($price) => $price->only(['id', 'amount_minor', 'currency', 'billing_interval']))->values(),
                        'entitlements' => $plan->entitlements->map(fn ($item) => ['value' => $item->value, 'definition' => ['key' => $item->definition->key]]),
                    ];
                }),
            'planRanks' => $catalog->plans()->map(fn (array $plan) => (int) ($plan['rank'] ?? 0))->all(),
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
            'planChangeStatus' => in_array($request->query('plan_change'), ['success', 'canceled'], true)
                ? $request->query('plan_change')
                : null,
            'planChangeTargetPriceId' => $request->query('plan_change') === 'success' && ctype_digit((string) $request->query('target_price_id'))
                ? (int) $request->query('target_price_id')
                : null,
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
        abort_if($price->plan->code === 'capacity', 422, 'Use the locations and bookable staff review for this subscription.');
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
                'name' => $business->name,
                'email' => null,
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
            'billingTimeZone' => $business->time_zone ?: 'UTC',
            'billingLocale' => $business->locale ?: 'en-US',
            'trialEndsAt' => $business->subscription?->status === SubscriptionStatus::Trialing ? $business->subscription->trial_ends_at : null,
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

    public function changePlan(Request $request, Business $business, SubscriptionProvider $provider, SubscriptionLifecycleManager $lifecycle, PlanCatalog $catalog, StripeBillingReadiness $readiness, AuditWriter $audit): JsonResponse
    {
        abort_if($business->subscription?->capacity_snapshot, 409, 'Use the locations and bookable staff controls to change this subscription.');
        $this->authorizeBilling($request, $business);
        $validated = $request->validate([
            'price_id' => ['required', 'integer'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);
        $subscription = $business->subscription()->with(['plan', 'price'])->firstOrFail();
        abort_unless(filled($subscription->provider_subscription_id), 409, 'Complete a subscription checkout before changing plans.');
        abort_unless($subscription->provider === 'stripe', 409, 'This subscription must be migrated to Stripe before it can be changed.');
        abort_unless($subscription->status === SubscriptionStatus::Active, 409, 'Resolve the current billing status before changing plans.');
        abort_unless($readiness->status()['checkout_ready'], 503, 'Plan changes are paused until secure Stripe event delivery is configured. Your current subscription is unchanged.');
        abort_if(
            $subscription->changes()
                ->whereNull('applied_at')
                ->whereNull('superseded_at')
                ->whereIn('kind', ['scheduled_plan_change', 'over_limit_downgrade'])
                ->exists(),
            409,
            'A scheduled plan change already exists. Contact support if it needs to be changed.',
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

        abort_if(
            $catalog->isDowngrade($subscription->plan->code, $price->plan->code),
            422,
            'Plan downgrades are not available through self-service. Your current subscription is unchanged.',
        );

        $returnUrl = route('business.billing.show', [$business, 'plan_change' => 'canceled']);
        $completedUrl = route('business.billing.show', [
            $business,
            'plan_change' => 'success',
            'target_price_id' => $price->getKey(),
        ]);
        $url = $provider->planChangePortalUrl($subscription, $price, $returnUrl, $completedUrl);

        $lifecycle->supersedePendingProviderPlanChanges(
            $subscription,
            $request->user(),
            'Replaced the legacy direct-update request with a Stripe-hosted confirmation flow.',
        );
        $audit->write('subscription.plan_change.portal_started', $business, $request->user(), $subscription, $validated['reason'], after: [
            'target_plan_id' => $price->billing_plan_id,
            'target_price_id' => $price->getKey(),
            'target_interval' => $price->billing_interval->value,
        ]);

        return response()->json([
            'url' => $url,
            'status' => 'requires_confirmation',
            'message' => 'Review the exact timing and charge securely in Stripe before confirming.',
        ]);
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

    public function portal(Request $request, Business $business, SubscriptionProvider $provider): RedirectResponse
    {
        $this->authorizeBilling($request, $business);
        $subscription = $business->subscription()->firstOrFail();
        abort_unless($subscription->provider === 'stripe' && filled($subscription->provider_customer_id), 409, 'Complete Stripe checkout before opening the billing portal.');
        $url = $provider->billingPortalUrl($subscription, route('business.billing.show', [$business, 'billing' => 'returned']));

        return redirect()->away($url);
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
}
