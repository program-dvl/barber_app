<?php

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Models\BusinessSubscription;
use Illuminate\Support\Facades\Log;
use LogicException;
use Stripe\StripeClient;

class StripeSubscriptionReconciler
{
    public function __construct(private readonly StripeWebhookProcessor $projector) {}

    public function reconcile(BusinessSubscription $subscription): BusinessSubscription
    {
        if ($subscription->provider !== 'stripe' || ! str_starts_with((string) $subscription->provider_subscription_id, 'sub_')) {
            throw new LogicException('Only a mapped Stripe subscription can be reconciled.');
        }

        $secret = trim((string) config('billing.stripe.secret'));
        if (! str_starts_with($secret, 'sk_') && ! str_starts_with($secret, 'rk_')) {
            throw new LogicException('Stripe server credentials are not configured.');
        }

        $stripe = new StripeClient($secret);
        $providerSubscription = $stripe->subscriptions->retrieve($subscription->provider_subscription_id, [
            'expand' => ['latest_invoice'],
        ]);
        $observedAt = now();
        $this->projector->synchronizeSubscriptionSnapshot($providerSubscription->toArray(), $observedAt);

        $latestInvoice = $providerSubscription->latest_invoice ?? null;
        if (is_string($latestInvoice)) {
            $latestInvoice = $stripe->invoices->retrieve($latestInvoice, []);
        }
        if (is_object($latestInvoice)) {
            try {
                $this->projector->synchronizeInvoiceSnapshot(
                    $latestInvoice->toArray(),
                    $latestInvoice->status === 'paid' ? 'invoice.paid' : 'invoice.updated',
                    $observedAt,
                );
            } catch (\Throwable $exception) {
                Log::warning('Stripe plan-change reconciliation updated the subscription but could not project its latest invoice.', [
                    'business_id' => $subscription->business_id,
                    'exception' => $exception::class,
                ]);
            }
        }

        return $subscription->fresh(['plan', 'price']);
    }
}
