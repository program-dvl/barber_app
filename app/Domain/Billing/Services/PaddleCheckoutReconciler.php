<?php

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Models\BillingCheckoutAttempt;
use App\Domain\Billing\Models\BillingPlanPrice;
use App\Domain\Billing\Models\BusinessSubscription;
use App\Domain\Billing\Providers\PaddleSubscriptionProvider;
use App\Domain\PlatformAccess\Models\Business;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class PaddleCheckoutReconciler
{
    public function __construct(
        private readonly PaddleSubscriptionProvider $provider,
        private readonly PaddleWebhookProcessor $projector,
    ) {}

    /** @return array<string, mixed> */
    public function reconcile(Business $business, ?string $transactionId = null, ?User $actor = null): array
    {
        abort_unless(config('billing.provider') === 'paddle', 409, 'Paddle checkout recovery is not enabled.');
        $subscription = $business->subscription()->with(['plan', 'price'])->firstOrFail();
        $attempt = $transactionId
            ? BillingCheckoutAttempt::query()
                ->where('business_id', $business->getKey())
                ->where('provider', 'paddle')
                ->where('provider_transaction_id', $transactionId)
                ->with('price.plan')
                ->firstOrFail()
            : $this->discoverCompletedAttempt($business, $subscription, $actor);

        if (! $attempt) {
            $attempt = BillingCheckoutAttempt::query()
                ->where('business_id', $business->getKey())
                ->where('provider', 'paddle')
                ->whereIn('status', ['pending', 'processing'])
                ->latest('created_at')
                ->with('price.plan')
                ->first();
        }

        if (! $attempt) {
            return ['status' => 'none'];
        }

        if ($attempt->status === 'confirmed') {
            return $this->confirmedResponse($attempt, $business->subscription()->with('plan')->firstOrFail());
        }

        $transaction = $this->provider->transaction($subscription, $attempt->provider_transaction_id);
        $attempt->update(['last_checked_at' => now(), 'status' => 'processing', 'last_error' => null]);
        $providerStatus = (string) ($transaction['status'] ?? 'unknown');

        if (! in_array($providerStatus, ['completed', 'paid'], true)) {
            if (in_array($providerStatus, ['canceled', 'past_due'], true)) {
                $attempt->update(['status' => 'failed', 'last_error' => 'Paddle checkout ended with status '.$providerStatus.'.']);

                return ['status' => 'failed', 'message' => 'The payment was not completed. No plan change was made.'];
            }

            $attempt->update(['status' => 'pending']);

            return ['status' => 'pending'];
        }

        $this->validateTransaction($business, $subscription, $attempt, $transaction);
        $providerSubscriptionId = (string) ($transaction['subscription_id'] ?? '');
        abort_unless($providerSubscriptionId !== '', 409, 'Paddle has not attached the subscription yet. Please retry shortly.');
        $providerSubscription = $this->provider->subscriptionByProviderId($subscription, $providerSubscriptionId);
        $this->validateSubscription($business, $subscription, $attempt, $transaction, $providerSubscription);

        if (! in_array((string) ($providerSubscription['status'] ?? ''), ['active', 'trialing'], true)) {
            $attempt->update(['status' => 'pending']);

            return ['status' => 'pending'];
        }

        $occurredAt = Carbon::parse((string) ($transaction['billed_at'] ?? $transaction['updated_at'] ?? $transaction['created_at'] ?? now()), 'UTC');
        $projected = $this->projector->projectConfirmedCheckout($transaction, $providerSubscription, $occurredAt);
        $attempt->update([
            'provider_subscription_id' => $providerSubscriptionId,
            'status' => 'confirmed',
            'confirmed_at' => now(),
            'last_checked_at' => now(),
            'last_error' => null,
        ]);

        return $this->confirmedResponse($attempt->fresh('price.plan'), $projected);
    }

    private function discoverCompletedAttempt(Business $business, BusinessSubscription $subscription, ?User $actor): ?BillingCheckoutAttempt
    {
        if (! filled($subscription->provider_customer_id)) {
            return null;
        }

        $transaction = collect($this->provider->recentCompletedTransactions($subscription))
            ->filter(fn (array $candidate) => data_get($candidate, 'custom_data.application') === 'good_hours'
                && data_get($candidate, 'custom_data.business_public_id') === $business->public_id
                && ($candidate['customer_id'] ?? null) === $subscription->provider_customer_id)
            ->sortByDesc(fn (array $candidate) => $candidate['billed_at'] ?? $candidate['updated_at'] ?? $candidate['created_at'] ?? '')
            ->first();

        if (! $transaction) {
            return null;
        }

        $priceId = filter_var(data_get($transaction, 'custom_data.plan_price_id'), FILTER_VALIDATE_INT);
        $price = $priceId ? BillingPlanPrice::query()
            ->whereKey($priceId)
            ->where('provider', 'paddle')
            ->where('provider_price_id', data_get($transaction, 'items.0.price.id'))
            ->first() : null;
        if (! $price) {
            Log::warning('Completed Paddle checkout could not be recovered because its price mapping is invalid.', [
                'business_id' => $business->getKey(),
                'provider_transaction_id' => $transaction['id'] ?? null,
            ]);

            return null;
        }

        $attempt = BillingCheckoutAttempt::query()->firstOrCreate(
            ['provider_transaction_id' => $transaction['id']],
            [
                'business_id' => $business->getKey(),
                'business_subscription_id' => $subscription->getKey(),
                'billing_plan_price_id' => $price->getKey(),
                'created_by_user_id' => $actor?->getKey(),
                'provider' => 'paddle',
                'provider_subscription_id' => $transaction['subscription_id'] ?? null,
                'status' => 'pending',
                'expires_at' => now()->addDay(),
            ],
        );
        abort_unless($attempt->business_id === $business->getKey(), 409, 'This checkout belongs to another billing account.');

        return $attempt->load('price.plan');
    }

    /** @param array<string, mixed> $transaction */
    private function validateTransaction(Business $business, BusinessSubscription $subscription, BillingCheckoutAttempt $attempt, array $transaction): void
    {
        abort_unless(data_get($transaction, 'custom_data.application') === 'good_hours', 409, 'This checkout does not belong to Good Hours.');
        abort_unless(data_get($transaction, 'custom_data.business_public_id') === $business->public_id, 409, 'This checkout belongs to another billing account.');
        abort_unless((string) data_get($transaction, 'custom_data.plan_price_id') === (string) $attempt->billing_plan_price_id, 409, 'The checkout plan does not match the selected plan.');
        abort_unless(($transaction['customer_id'] ?? null) === $subscription->provider_customer_id, 409, 'The checkout customer does not match this billing account.');
        abort_unless(data_get($transaction, 'items.0.price.id') === $attempt->price->provider_price_id, 409, 'The Paddle price does not match the selected plan.');
        abort_unless(strtoupper((string) ($transaction['currency_code'] ?? '')) === strtoupper($attempt->price->currency), 409, 'The checkout currency does not match the selected plan.');
    }

    /** @param array<string, mixed> $transaction @param array<string, mixed> $providerSubscription */
    private function validateSubscription(Business $business, BusinessSubscription $subscription, BillingCheckoutAttempt $attempt, array $transaction, array $providerSubscription): void
    {
        abort_unless(($providerSubscription['id'] ?? null) === ($transaction['subscription_id'] ?? null), 409, 'The Paddle subscription does not match the checkout.');
        abort_unless(($providerSubscription['customer_id'] ?? null) === $subscription->provider_customer_id, 409, 'The Paddle subscription customer does not match this billing account.');
        abort_unless(data_get($providerSubscription, 'custom_data.application') === 'good_hours', 409, 'This subscription does not belong to Good Hours.');
        abort_unless(data_get($providerSubscription, 'custom_data.business_public_id') === $business->public_id, 409, 'This subscription belongs to another billing account.');
        abort_unless(data_get($providerSubscription, 'items.0.price.id') === $attempt->price->provider_price_id, 409, 'The Paddle subscription price does not match the checkout.');
    }

    /** @return array<string, mixed> */
    private function confirmedResponse(BillingCheckoutAttempt $attempt, BusinessSubscription $subscription): array
    {
        return [
            'status' => 'confirmed',
            'transaction_id' => $attempt->provider_transaction_id,
            'plan_name' => $subscription->plan->name,
            'subscription_status' => $subscription->status->value,
            'invoice_count' => $subscription->invoices()->count(),
        ];
    }
}
