<?php

namespace App\Http\Controllers\Billing;

use App\Domain\Billing\Models\BillingCapacityChange;
use App\Domain\Billing\Models\BillingInvoice;
use App\Domain\Billing\Models\BillingRateCard;
use App\Domain\Billing\Models\SmsCreditPurchase;
use App\Domain\Billing\Services\CapacityBillingManager;
use App\Domain\Billing\Services\CapacityCheckoutRecovery;
use App\Domain\Billing\Services\CapacityStripeGateway;
use App\Domain\Billing\Services\SmsCreditWallet;
use App\Domain\Billing\Services\StripeBillingReadiness;
use App\Domain\Billing\Services\StripeSubscriptionReconciler;
use App\Domain\PlatformAccess\Enums\PermissionName;
use App\Domain\PlatformAccess\Models\Business;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class CapacityBillingController extends Controller
{
    public function review(Request $request, Business $business, CapacityBillingManager $manager)
    {
        $this->authorizeBilling($request, $business);
        $data = $request->validate(['market' => ['required', 'string', 'size:2'], 'billing_interval' => ['required', 'in:monthly,annual'],
            'locations' => ['required', 'integer', 'min:1'], 'staff' => ['required', 'integer', 'min:1'], 'use_current_rates' => ['sometimes', 'boolean']]);
        $change = $manager->review($business, $request->user(), $data['market'], $data['billing_interval'], $data['locations'], $data['staff'], (bool) ($data['use_current_rates'] ?? false));

        return response()->json($this->present($change));
    }

    public function confirm(Request $request, Business $business, CapacityBillingManager $manager, CapacityStripeGateway $stripe, StripeBillingReadiness $readiness)
    {
        $this->authorizeBilling($request, $business);
        $data = $request->validate(['change_id' => ['required', 'string', 'size:26']]);
        $change = BillingCapacityChange::where('business_id', $business->id)->where('public_id', $data['change_id'])->firstOrFail();
        abort_unless($readiness->status()['checkout_ready'], 503, 'Secure billing is not configured.');
        $claim = $manager->claim($business, $request->user(), $change);
        $change = $claim['change'];
        if ($change->kind === 'checkout' && $change->status !== 'applied') {
            return $this->checkoutResponse($change, created: $claim['dispatch']);
        }
        if (! $claim['dispatch']) {
            return response()->json($this->present($change), in_array($change->status, ['applied', 'scheduled'], true) ? 200 : 202);
        }
        $subscription = $business->subscription()->firstOrFail();
        if ($change->kind !== 'checkout') {
            // A timeout leaves the submitted request pending for authenticated
            // reconciliation. Never roll back or start a second payment blindly.
            try {
                $stripe->submit($subscription, $change);
            } catch (Throwable $error) {
                return $this->providerError($change, $error);
            }

            return response()->json($this->present($change->fresh()), 202);
        }
    }

    private function checkoutResponse(BillingCapacityChange $change, bool $created = false)
    {
        try {
            $attempt = app(CapacityCheckoutRecovery::class)->synchronize($change);
            if ($attempt->provider_checkout_url && in_array($attempt->status, ['pending', 'processing'], true)) {
                return response()->json(['url' => $attempt->provider_checkout_url, 'attempt_id' => $attempt->public_id], $created ? 201 : 200);
            }

            return response()->json($this->present($change->fresh()), 202);
        } catch (Throwable $error) {
            return $this->providerError($change, $error);
        }
    }

    public function status(Request $request, Business $business, StripeSubscriptionReconciler $reconciler, CapacityStripeGateway $stripe)
    {
        $this->authorizeBilling($request, $business);
        $data = $request->validate(['change_id' => ['required', 'string', 'size:26'], 'verify_only' => ['sometimes', 'boolean']]);
        $change = BillingCapacityChange::where('business_id', $business->id)->where('public_id', $data['change_id'])->firstOrFail();
        try {
            $attempt = null;
            if ($change->kind === 'checkout' && $change->status === 'submitted') {
                $recovery = app(CapacityCheckoutRecovery::class);
                $attempt = ($data['verify_only'] ?? false) ? $recovery->verify($change) : $recovery->synchronize($change);
            }
            $subscription = $business->subscription()->firstOrFail();
            if ($change->kind !== 'checkout' && in_array($change->status, ['submitted', 'scheduled'], true) && $subscription->provider_subscription_id
                && (! $change->last_checked_at || $change->last_checked_at->lte(now()->subSeconds(10)))) {
                $change->update(['last_checked_at' => now()]);
                $reconciler->reconcile($subscription);
                if ($change->fresh()->status === 'submitted' && ! ($data['verify_only'] ?? false)) {
                    $stripe->submit($subscription->fresh(), $change->fresh(), resume: true);
                    $reconciler->reconcile($subscription->fresh());
                }
            }
            $checkoutUrl = $attempt && in_array($attempt->status, ['pending', 'processing'], true) ? $attempt->provider_checkout_url : null;

            return response()->json([...$this->present($change->fresh()), 'checkout_url' => $checkoutUrl,
                'checkout_status' => $attempt?->status]);
        } catch (Throwable $error) {
            return $this->providerError($change, $error);
        }
    }

    public function restart(Request $request, Business $business)
    {
        $this->authorizeBilling($request, $business);
        $data = $request->validate(['change_id' => ['required', 'string', 'size:26']]);
        $change = BillingCapacityChange::where('business_id', $business->id)->where('public_id', $data['change_id'])->firstOrFail();
        abort_unless($change->kind === 'checkout', 409, 'Only an unfinished checkout can be restarted here.');
        if (! in_array($change->status, ['submitted', 'scheduled'], true)) {
            return response()->json([...$this->present($change), 'checkout_url' => null]);
        }
        try {
            app(CapacityCheckoutRecovery::class)->synchronize($change, restart: true, actor: $request->user());

            return response()->json([...$this->present($change->fresh()), 'checkout_url' => null]);
        } catch (Throwable $error) {
            return $this->providerError($change, $error);
        }
    }

    private function providerError(BillingCapacityChange $change, Throwable $error)
    {
        Log::warning('Capacity checkout recovery needs verification.', ['business_id' => $change->business_id, 'change_id' => $change->public_id,
            'exception' => $error::class, 'provider_code' => $error instanceof ApiErrorException ? $error->getStripeCode() : null]);
        $status = $error instanceof HttpExceptionInterface ? $error->getStatusCode() : 503;
        $fresh = $change->fresh();
        if (! in_array($fresh->status, ['submitted', 'scheduled'], true)) {
            return response()->json([...$this->present($fresh), 'checkout_url' => null]);
        }
        $needsReview = $status === 409 || ($error instanceof ApiErrorException && $error->getStripeCode() === 'resource_missing');
        $paymentRequired = $status === 409 && $fresh->kind !== 'checkout'
            && BillingInvoice::where('business_id', $fresh->business_id)->where('business_subscription_id', $fresh->business_subscription_id)
                ->whereIn('status', ['open', 'uncollectible'])
                ->whereRaw('COALESCE(amount_remaining_minor, amount_due_minor - amount_paid_minor) > 0')->exists();
        $canRestart = $needsReview && app(CapacityCheckoutRecovery::class)->canRestartTestPreparation($fresh);
        $email = config('brand.support_email');
        $supportUrl = filter_var($email, FILTER_VALIDATE_EMAIL)
            ? 'mailto:'.$email.'?subject='.rawurlencode('Checkout help — '.$change->public_id)
                .'&body='.rawurlencode('Please help me continue my subscription checkout. Reference: '.$change->public_id.'.')
            : null;
        $message = match (true) {
            $paymentRequired => 'A payment needs attention. Your request is saved. Open Invoices to see its status and available payment options.',
            $canRestart => 'Your previous checkout setup can’t be continued. Your selection is saved. Choose Start again to review it and open a fresh checkout.',
            $needsReview => ($fresh->kind === 'checkout' ? 'We couldn’t reopen your previous checkout.' : 'We couldn’t confirm your subscription change.')
                .' Your selection is saved. '.($supportUrl ? 'Contact support so we can help you continue safely.' : 'Please try again later.'),
            default => 'We couldn’t confirm this step. Your request is saved. Check its status in a moment.',
        };
        $retryAfter = $canRestart ? 0 : ($needsReview && ! $paymentRequired ? 60 : 15);

        return response()->json(['message' => $message, 'code' => $canRestart ? 'billing_restart_available' : ($paymentRequired ? 'billing_payment_required' : ($needsReview ? 'billing_review_required' : 'billing_temporarily_unavailable')),
            'support_required' => $needsReview && ! $canRestart && ! $paymentRequired, 'payment_required' => $paymentRequired,
            'can_restart' => $canRestart, 'support_url' => $supportUrl, 'reference' => $change->public_id,
            'retry_after' => $retryAfter, 'status' => $fresh->status, 'checkout_url' => null], $status,
            ['Retry-After' => (string) $retryAfter]);
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
