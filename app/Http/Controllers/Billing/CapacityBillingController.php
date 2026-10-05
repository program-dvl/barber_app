<?php

namespace App\Http\Controllers\Billing;

use App\Domain\Billing\Contracts\SubscriptionProvider;
use App\Domain\Billing\Models\BillingCapacityChange;
use App\Domain\Billing\Models\BillingCheckoutAttempt;
use App\Domain\Billing\Models\BillingRateCard;
use App\Domain\Billing\Models\SmsCreditPurchase;
use App\Domain\Billing\Services\CapacityBillingManager;
use App\Domain\Billing\Services\CapacityStripeGateway;
use App\Domain\Billing\Services\SmsCreditWallet;
use App\Domain\Billing\Services\StripeBillingReadiness;
use App\Domain\Billing\Services\StripeCheckoutReconciler;
use App\Domain\Billing\Services\StripeSubscriptionReconciler;
use App\Domain\PlatformAccess\Enums\PermissionName;
use App\Domain\PlatformAccess\Models\Business;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CapacityBillingController extends Controller
{
    public function review(Request $request, Business $business, CapacityBillingManager $manager)
    {
        $this->authorizeBilling($request, $business);
        $data = $request->validate(['market' => ['required', 'string', 'size:2'], 'billing_interval' => ['required', 'in:monthly,annual'],
            'locations' => ['required', 'integer', 'min:1'], 'staff' => ['required', 'integer', 'min:1']]);
        $change = $manager->review($business, $request->user(), $data['market'], $data['billing_interval'], $data['locations'], $data['staff']);

        return response()->json($this->present($change));
    }

    public function confirm(Request $request, Business $business, CapacityBillingManager $manager, CapacityStripeGateway $stripe, SubscriptionProvider $provider, StripeBillingReadiness $readiness)
    {
        $this->authorizeBilling($request, $business);
        $data = $request->validate(['change_id' => ['required', 'string', 'size:26']]);
        $change = BillingCapacityChange::where('business_id', $business->id)->where('public_id', $data['change_id'])->firstOrFail();
        abort_unless($readiness->status()['checkout_ready'], 503, 'Secure billing is not configured.');
        $claim = $manager->claim($business, $request->user(), $change);
        $change = $claim['change'];
        if (! $claim['dispatch']) {
            if ($change->kind === 'checkout') {
                $attempt = BillingCheckoutAttempt::where('business_subscription_id', $change->business_subscription_id)->where('capacity_quote->fingerprint', $change->quote['fingerprint'])->where('capacity_quote->locations', $change->quote['locations'])->where('capacity_quote->staff', $change->quote['staff'])->whereIn('status', ['pending', 'processing'])->latest('id')->first();
                if ($attempt?->provider_checkout_url) {
                    return response()->json(['url' => $attempt->provider_checkout_url, 'attempt_id' => $attempt->public_id]);
                }
            }

            return response()->json($this->present($change), $change->status === 'applied' || $change->status === 'scheduled' ? 200 : 202);
        }
        $subscription = $business->subscription()->firstOrFail();
        if ($change->kind !== 'checkout') {
            // A timeout leaves the submitted request pending for authenticated
            // reconciliation. Never roll back or start a second payment blindly.
            $stripe->submit($subscription, $change);

            return response()->json($this->present($change->fresh()), 202);
        }
        $card = BillingRateCard::findOrFail($change->quote['rate_card_id']);
        $attempt = DB::transaction(function () use ($business, $request, $subscription, $card, $change) {
            $business->subscription()->lockForUpdate()->firstOrFail();

            return BillingCheckoutAttempt::create([
                'business_id' => $business->id, 'business_subscription_id' => $subscription->id,
                'created_by_user_id' => $request->user()->id, 'billing_plan_price_id' => $card->billing_plan_price_id,
                'provider' => 'stripe', 'provider_transaction_id' => 'pending_capacity_'.$change->public_id,
                'status' => 'pending', 'expires_at' => now()->addHours(2), 'capacity_quote' => $change->quote,
            ]);
        });
        $price = $attempt->price;
        $checkout = $provider->createCheckout($business, $price, $attempt,
            route('business.billing.show', $business).'?checkout=success&attempt='.$attempt->public_id.'&session_id={CHECKOUT_SESSION_ID}',
            route('business.billing.show', $business).'?checkout=canceled');
        $attempt->update(['provider_transaction_id' => $checkout['provider_session_id'], 'provider_checkout_url' => $checkout['url']]);

        return response()->json(['url' => $checkout['url'], 'attempt_id' => $attempt->public_id], 201);
    }

    public function status(Request $request, Business $business, StripeSubscriptionReconciler $reconciler, SubscriptionProvider $provider)
    {
        $this->authorizeBilling($request, $business);
        $data = $request->validate(['change_id' => ['required', 'string', 'size:26']]);
        $change = BillingCapacityChange::where('business_id', $business->id)->where('public_id', $data['change_id'])->firstOrFail();
        $subscription = $business->subscription()->firstOrFail();
        if ($change->kind === 'checkout') {
            $attempt = BillingCheckoutAttempt::where('business_subscription_id', $subscription->id)->where('capacity_quote->fingerprint', $change->quote['fingerprint'])->where('capacity_quote->locations', $change->quote['locations'])->where('capacity_quote->staff', $change->quote['staff'])->whereIn('status', ['pending', 'processing'])->latest('id')->first();
            if ($attempt && str_starts_with($attempt->provider_transaction_id, 'pending_capacity_') && $attempt->expires_at->isFuture()) {
                // Recover this exact operation; never choose another quote/payment key.
                $checkout = $provider->createCheckout($business, $attempt->price, $attempt,
                    route('business.billing.show', $business).'?checkout=success&attempt='.$attempt->public_id.'&session_id={CHECKOUT_SESSION_ID}',
                    route('business.billing.show', $business).'?checkout=canceled');
                $attempt->update(['provider_transaction_id' => $checkout['provider_session_id'], 'provider_checkout_url' => $checkout['url']]);

                return response()->json([...$this->present($change->fresh()), 'checkout_url' => $checkout['url']]);
            }
            if ($attempt && str_starts_with($attempt->provider_transaction_id, 'cs_')) {
                app(StripeCheckoutReconciler::class)->reconcile($attempt);
            }
        }
        if (in_array($change->status, ['submitted', 'scheduled'], true) && $subscription->provider_subscription_id && (! $change->last_checked_at || $change->last_checked_at->lte(now()->subSeconds(10)))) {
            $change->update(['last_checked_at' => now()]);
            $reconciler->reconcile($subscription);
        }

        return response()->json($this->present($change->fresh()));
    }

    public function topup(Request $request, Business $business, CapacityStripeGateway $stripe)
    {
        $this->authorizeBilling($request, $business);
        $data = $request->validate(['pack' => ['required', 'string', 'max:32']]);
        $purchase = DB::transaction(function () use ($business, $request, $data) {
            $subscription = $business->subscription()->lockForUpdate()->firstOrFail();
            abort_unless($subscription->capacity_snapshot && $subscription->status->value === 'active' && config('capacity-billing.enabled') && config('capacity-billing.sms_purchases_enabled'), 409, 'An active capacity subscription is required for text top-ups.');
            $card = BillingRateCard::findOrFail($subscription->billing_rate_card_id);
            abort_unless(config('capacity-billing.markets.'.$card->market.'.approved'), 503, 'Text purchases are temporarily unavailable.');
            $pack = $card->terms['sms_packs'][$data['pack']] ?? null;
            abort_unless($pack && str_starts_with((string) $pack['price_id'], 'price_'), 422, 'This text pack is not available.');
            $open = SmsCreditPurchase::where('business_id', $business->id)->whereIn('status', ['preparing', 'pending'])->where('expires_at', '>', now())->first();
            if ($open) {
                abort_unless($open->actor_user_id === $request->user()->id && $open->quote['pack'] === $data['pack'], 409, 'A text purchase is already pending. Refresh billing.');

                return $open;
            }

            return SmsCreditPurchase::create([
                'business_id' => $business->id, 'actor_user_id' => $request->user()->id, 'expires_at' => now()->addDay(),
                'quote' => [...$pack, 'currency' => $card->currency, 'pack' => $data['pack']],
            ]);
        }, 3);
        if (! $purchase->checkout_url) {
            $checkout = $stripe->topupCheckout($business->subscription, $purchase, route('business.billing.show', $business));
            $purchase->update(['provider_session_id' => $checkout['id'], 'checkout_url' => $checkout['url'], 'status' => 'pending']);
        }

        return response()->json(['url' => $purchase->checkout_url, 'purchase_id' => $purchase->public_id]);
    }

    public function topupStatus(Request $request, Business $business, CapacityStripeGateway $stripe, SmsCreditWallet $wallet)
    {
        $this->authorizeBilling($request, $business);
        $data = $request->validate(['purchase_id' => ['required', 'string', 'size:26']]);
        $purchase = SmsCreditPurchase::where('business_id', $business->id)->where('public_id', $data['purchase_id'])->firstOrFail();
        if (in_array($purchase->status, ['preparing', 'pending'], true) && $purchase->provider_session_id) {
            $stripe->reconcilePurchase($purchase);
        }

        return response()->json(['status' => $purchase->fresh()->status, 'credits' => $wallet->balance($business->id)]);
    }

    public function present(BillingCapacityChange $change): array
    {
        return ['change_id' => $change->public_id, 'status' => $change->status, 'kind' => $change->kind,
            'quote' => $change->quote, 'effective_at' => $change->effective_at, 'expires_at' => $change->expires_at];
    }

    private function authorizeBilling(Request $request, Business $business): void
    {
        abort_unless($request->user()?->can(PermissionName::BillingManage->value) && $request->user()->can('view', $business), 403);
    }
}
