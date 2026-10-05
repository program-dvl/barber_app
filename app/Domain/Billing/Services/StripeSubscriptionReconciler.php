<?php

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Models\BusinessSubscription;
use Illuminate\Support\Facades\Log;
use LogicException;
use Stripe\StripeClient;

class StripeSubscriptionReconciler
{
    private ?object $lastSnapshot = null;

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
            'expand' => ['latest_invoice', 'schedule', 'default_payment_method'],
        ]);
        $this->lastSnapshot = $providerSubscription;
        $observedAt = now();
        if (! $this->projector->synchronizeSubscriptionSnapshot($providerSubscription->toArray(), $observedAt)) {
            throw new LogicException('Provider subscription ownership could not be verified.');
        }
        if (is_object($providerSubscription->schedule ?? null)) {
            $this->projector->synchronizeScheduleSnapshot($providerSubscription->schedule->toArray(), $observedAt);
        } elseif (($providerSubscription->schedule ?? null) === null) {
            $this->projector->synchronizeScheduleSnapshot(['subscription' => $subscription->provider_subscription_id, 'customer' => $subscription->provider_customer_id, 'status' => 'released'], $observedAt);
        }

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

        $subscription->update(['billing_checked_at' => $observedAt]);

        return $subscription->fresh(['plan', 'price']);
    }

    public function reconcileAccount(BusinessSubscription $subscription): BusinessSubscription
    {
        $secret = trim((string) config('billing.stripe.secret'));
        if ($subscription->provider !== 'stripe' || ! str_starts_with((string) $subscription->provider_customer_id, 'cus_') || ! $secret) {
            throw new LogicException('Mapped billing account is required.');
        }
        $stripe = new StripeClient($secret);
        if (filled($subscription->provider_subscription_id)) {
            $subscription = $this->reconcile($subscription);
        }
        $customer = $stripe->customers->retrieve($subscription->provider_customer_id, ['expand' => ['invoice_settings.default_payment_method']]);
        if ($customer->id !== $subscription->provider_customer_id || ($customer->deleted ?? false)) {
            throw new LogicException('Billing account could not be verified.');
        }
        $paymentMethod = $customer->invoice_settings->default_payment_method ?? null;
        if (filled($subscription->provider_subscription_id)) {
            $paymentMethod = ($this->lastSnapshot?->default_payment_method ?? null) ?: $paymentMethod;
        }
        $card = is_object($paymentMethod) ? ($paymentMethod->card ?? null) : null;
        $currency = strtoupper((string) ($customer->currency ?? ''));
        $subscription->update([
            'billing_name' => $customer->name, 'billing_email' => $customer->email,
            'payment_method_type' => $card ? ucfirst($card->brand) : (is_object($paymentMethod) ? $paymentMethod->type : null),
            'payment_method_last_four' => $card?->last4,
            'payment_method_expiry_month' => $card?->exp_month, 'payment_method_expiry_year' => $card?->exp_year,
            'customer_balance_minor' => $currency ? $customer->balance : null,
            'balance_currency' => $currency ?: null, 'account_checked_at' => now(),
        ]);

        return $subscription->fresh(['plan', 'price']);
    }
}
