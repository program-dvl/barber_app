<?php

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Models\BillingCheckoutAttempt;
use Illuminate\Support\Facades\Log;
use LogicException;
use Stripe\StripeClient;

class StripeCheckoutReconciler
{
    public function __construct(private readonly StripeWebhookProcessor $projector) {}

    public function reconcile(BillingCheckoutAttempt $attempt): BillingCheckoutAttempt
    {
        if ($attempt->provider !== 'stripe' || ! str_starts_with($attempt->provider_transaction_id, 'cs_')) {
            throw new LogicException('Only a stored Stripe Checkout Session can be reconciled.');
        }

        $secret = trim((string) config('billing.stripe.secret'));
        if (! str_starts_with($secret, 'sk_') && ! str_starts_with($secret, 'rk_')) {
            throw new LogicException('Stripe server credentials are not configured.');
        }

        $stripe = new StripeClient($secret);
        $session = $stripe->checkout->sessions->retrieve($attempt->provider_transaction_id, [
            'expand' => ['subscription'],
        ]);
        $sessionData = $session->toArray();
        $providerSubscription = $session->subscription;
        $providerSubscriptionId = is_string($providerSubscription)
            ? $providerSubscription
            : ($providerSubscription?->id ?? null);
        $sessionData['subscription'] = $providerSubscriptionId;

        if ($session->status === 'expired') {
            $attempt->update([
                'status' => 'expired',
                'expires_at' => now(),
                'last_checked_at' => now(),
                'last_error' => 'checkout_expired',
            ]);

            return $attempt->fresh();
        }

        if ($session->status !== 'complete' || ! $providerSubscriptionId) {
            $attempt->update(['last_checked_at' => now()]);

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

        return $attempt->fresh();
    }
}
