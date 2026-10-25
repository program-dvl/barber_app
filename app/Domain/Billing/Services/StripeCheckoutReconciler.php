<?php

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Models\BillingCapacityChange;
use App\Domain\Billing\Models\BillingCheckoutAttempt;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use LogicException;
use Stripe\Exception\InvalidRequestException;
use Stripe\StripeClient;

class StripeCheckoutReconciler
{
    public function __construct(private readonly StripeWebhookProcessor $projector) {}

    public function reconcile(BillingCheckoutAttempt $attempt, bool $expireOpen = false): BillingCheckoutAttempt
    {
        if ($attempt->provider !== 'stripe' || ! str_starts_with($attempt->provider_transaction_id, 'cs_')) {
            throw new LogicException('Only a stored Stripe Checkout Session can be reconciled.');
        }

        $secret = trim((string) config('billing.stripe.secret'));
        if (! str_starts_with($secret, 'sk_') && ! str_starts_with($secret, 'rk_')) {
            throw new LogicException('Stripe server credentials are not configured.');
        }

        if (preg_match('/^cs_(live|test)_/', $attempt->provider_transaction_id, $mode)) {
            abort_unless($mode[1] === (str_contains($secret, '_live_') ? 'live' : 'test'), 409,
                'Your previous checkout belongs to a different payment environment. Restore its payment configuration and check again. No new payment was started.');
        }
        $stripe = new StripeClient($secret);
        $session = $stripe->checkout->sessions->retrieve($attempt->provider_transaction_id, [
            'expand' => ['subscription'],
        ]);
        $this->assertOwned($attempt, $session->toArray());
        if ($attempt->status === 'failed' && $attempt->last_error === 'initial_payment_failed'
            && in_array($session->status, ['open', 'complete'], true)) {
            // Older releases closed the local attempt on a recoverable card
            // decline. Only an authenticated owned session can reopen it.
            abort_unless(BillingCapacityChange::where('business_subscription_id', $attempt->business_subscription_id)
                ->where('public_id', $attempt->capacity_quote['capacity_change_id'] ?? null)->where('status', 'submitted')->exists(),
                409, 'This checkout needs a billing review before it can continue.');
            $attempt->update(['status' => $session->status === 'open' ? 'pending' : 'processing',
                'last_checked_at' => now(), 'last_error' => null]);
        }
        if ($expireOpen && $session->status === 'open') {
            try {
                $session = $stripe->checkout->sessions->expire($session->id, [], ['idempotency_key' => 'clipperdesk-expire-'.$attempt->public_id]);
            } catch (InvalidRequestException $error) {
                // Completion can win the race with expiration. Re-read it; never
                // release a payment that Stripe may already have accepted.
                $session = $stripe->checkout->sessions->retrieve($attempt->provider_transaction_id, ['expand' => ['subscription']]);
                if ($session->status === 'open') {
                    throw $error;
                }
            }
            $this->assertOwned($attempt, $session->toArray());
        }
        $sessionData = $session->toArray();
        $providerSubscription = $session->subscription;
        $providerSubscriptionId = is_string($providerSubscription)
            ? $providerSubscription
            : ($providerSubscription?->id ?? null);
        $sessionData['subscription'] = $providerSubscriptionId;

        if ($session->status === 'expired') {
            $this->projector->synchronizeCheckoutExpirationSnapshot($sessionData);
            $attempt->update([
                'status' => 'expired',
                'expires_at' => now(),
                'last_checked_at' => now(),
                'last_error' => 'checkout_expired',
            ]);

            return $attempt->fresh();
        }

        if ($session->status !== 'complete' || ! $providerSubscriptionId) {
            $attempt->update(['last_checked_at' => now(),
                'expires_at' => isset($session->expires_at) ? Carbon::createFromTimestampUTC($session->expires_at) : $attempt->expires_at,
                'provider_checkout_url' => $session->status === 'open' ? ($session->url ?? $attempt->provider_checkout_url) : null]);

            return $attempt->fresh();
        }

        $this->projector->synchronizeCheckoutSnapshot($sessionData, now());
        $subscription = is_string($providerSubscription)
            ? $stripe->subscriptions->retrieve($providerSubscription, ['expand' => ['latest_invoice']])
            : $providerSubscription;
        $subscriptionData = $subscription->toArray();
        $this->projector->synchronizeSubscriptionSnapshot($subscriptionData, now());

        $latestInvoice = $subscription->latest_invoice ?? null;
        if (is_string($latestInvoice)) {
            $latestInvoice = $stripe->invoices->retrieve($latestInvoice, []);
        }
        if (is_object($latestInvoice)) {
            try {
                $this->projector->synchronizeInvoiceSnapshot(
                    $latestInvoice->toArray(),
                    $latestInvoice->status === 'paid' ? 'invoice.paid' : 'invoice.updated',
                    now(),
                );
            } catch (\Throwable $exception) {
                Log::warning('Stripe Checkout reconciliation activated access but could not project the latest invoice.', [
                    'business_id' => $attempt->business_id,
                    'checkout_attempt_id' => $attempt->id,
                    'exception' => $exception::class,
                ]);
            }
        }
        $attempt->refresh();
        if (in_array($attempt->status, ['pending', 'processing'], true)) {
            $invoice = is_object($latestInvoice) ? $latestInvoice->toArray() : [];
            $invoiceSubscription = $invoice['subscription'] ?? data_get($invoice, 'parent.subscription_details.subscription');
            $invoiceUrl = ($invoice['status'] ?? null) === 'open'
                && ($invoice['customer'] ?? null) === $attempt->subscription->provider_customer_id
                && $invoiceSubscription === $providerSubscriptionId
                && strtoupper($invoice['currency'] ?? '') === ($attempt->capacity_quote['currency'] ?? null)
                && ($invoice['subtotal'] ?? null) === ($attempt->capacity_quote['total_minor'] ?? null)
                    ? app(BillingWorkspaceQuery::class)->secureLink($invoice['hosted_invoice_url'] ?? null) : null;
            // Completed Checkout URLs cannot collect another payment. Offer the
            // verified existing invoice when payment requires further action.
            $attempt->update(['provider_checkout_url' => $invoiceUrl, 'last_checked_at' => now()]);
        }

        return $attempt->fresh();
    }

    /** Find an uncertain creation without issuing a new Checkout or charging. */
    public function recoverPreparation(BillingCheckoutAttempt $attempt): BillingCheckoutAttempt
    {
        $subscription = $attempt->subscription;
        $secret = trim((string) config('billing.stripe.secret'));
        abort_unless(str_starts_with($secret, 'sk_') || str_starts_with($secret, 'rk_'), 503, 'Secure checkout is temporarily unavailable. Please try again shortly.');
        $mode = str_contains($secret, '_live_') ? 'live' : 'test';
        abort_if(isset($attempt->capacity_quote['stripe_mode']) && $attempt->capacity_quote['stripe_mode'] !== $mode, 409,
            'Your previous checkout belongs to a different payment environment. Restore its payment configuration and check again. No new payment was started.');
        $stripe = new StripeClient($secret);
        if (! $subscription->provider_customer_id) {
            // An unknown create-customer outcome must reuse its original key.
            $fingerprint = $attempt->capacity_quote['stripe_credential_fingerprint'] ?? null;
            abort_unless($fingerprint && hash_equals($fingerprint, hash('sha256', $secret)), 409,
                'Your previous checkout needs verification in its original payment account. No new payment was started.');
            // Replaying the original idempotency key can recover a lost response.
            // Never replay after Stripe's minimum key-retention window.
            abort_unless($attempt->created_at->gt(now()->subHours(23)), 409,
                'This checkout needs verification in its original payment account before another payment can start. Please contact support.');

            return $attempt;

        }
        $customer = $stripe->customers->retrieve($subscription->provider_customer_id, []);
        abort_unless($customer->id === $subscription->provider_customer_id
            && ($customer->metadata->business_public_id ?? null) === $attempt->business->public_id, 409, 'The previous payment account could not be verified. Please contact support.');
        $sessions = $stripe->checkout->sessions->all(['customer' => $customer->id, 'created' => ['gte' => $attempt->created_at->timestamp - 60], 'limit' => 100]);
        $matches = collect($sessions->data)->filter(fn ($session) => ($session->metadata->billing_checkout_attempt_id ?? null) === $attempt->public_id);
        abort_if($sessions->has_more || $matches->count() > 1, 409, 'Your previous checkout needs a billing review before another payment can start.');
        if ($session = $matches->first()) {
            $this->assertOwned($attempt, $session->toArray(), requireSessionId: false);
            $attempt->update(['provider_transaction_id' => $session->id, 'provider_checkout_url' => $session->url, 'last_checked_at' => now()]);
        } else {
            $attempt->update(['status' => 'expired', 'expires_at' => now(), 'last_checked_at' => now(), 'last_error' => 'checkout_not_created']);
        }

        return $attempt->fresh();
    }

    private function assertOwned(BillingCheckoutAttempt $attempt, array $session, bool $requireSessionId = true): void
    {
        abort_unless((! $requireSessionId || ($session['id'] ?? null) === $attempt->provider_transaction_id)
            && ($session['mode'] ?? null) === 'subscription'
            && (! isset($session['livemode']) || $session['livemode'] === str_contains((string) config('billing.stripe.secret'), '_live_'))
            && data_get($session, 'metadata.billing_checkout_attempt_id') === $attempt->public_id
            && data_get($session, 'metadata.business_public_id') === $attempt->business->public_id
            && (string) data_get($session, 'metadata.plan_price_id') === (string) $attempt->billing_plan_price_id
            && ($session['customer'] ?? null) === $attempt->subscription->provider_customer_id
            && (! isset($session['client_reference_id']) || $session['client_reference_id'] === $attempt->business->public_id),
            409, 'The checkout could not be verified for your business. Please contact support before starting another payment.');
    }
}
