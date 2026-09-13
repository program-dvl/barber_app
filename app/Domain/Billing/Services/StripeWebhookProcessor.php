<?php

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Models\BillingCheckoutAttempt;
use App\Domain\Billing\Models\BillingInvoice;
use App\Domain\Billing\Models\BillingPayment;
use App\Domain\Billing\Models\BillingPlanPrice;
use App\Domain\Billing\Models\BillingProviderEvent;
use App\Domain\Billing\Models\BusinessSubscription;
use App\Domain\PlatformAccess\Models\Business;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class StripeWebhookProcessor
{
    public function __construct(private readonly SubscriptionLifecycleManager $lifecycle) {}

    /**
     * Project a Checkout snapshot retrieved directly through authenticated Stripe API access.
     * Signed webhooks remain the primary event stream; this closes redirect/delivery gaps safely.
     *
     * @param  array<string, mixed>  $object
     */
    public function synchronizeCheckoutSnapshot(array $object, Carbon $observedAt): bool
    {
        return $this->checkoutCompleted($object, $observedAt);
    }

    /** @param array<string, mixed> $object */
    public function synchronizeSubscriptionSnapshot(array $object, Carbon $observedAt): bool
    {
        return $this->subscriptionUpdated($object, $observedAt);
    }

    /** @param array<string, mixed> $object */
    public function synchronizeInvoiceSnapshot(array $object, string $eventType, Carbon $observedAt): bool
    {
        return $this->invoiceUpdated($object, $eventType, $observedAt);
    }

    /** @param array<string, mixed> $payload */
    public function receiveVerified(array $payload): BillingProviderEvent
    {
        $providerEventId = (string) ($payload['id'] ?? '');
        abort_if($providerEventId === '', 400, 'Provider event ID is required.');
        $encoded = json_encode($payload, JSON_THROW_ON_ERROR);

        $event = BillingProviderEvent::query()->firstOrCreate(
            ['provider' => 'stripe', 'provider_event_id' => $providerEventId],
            [
                'provider' => 'stripe',
                'event_type' => (string) ($payload['type'] ?? 'unknown'),
                'status' => 'pending',
                'signature_verified' => true,
                'provider_created_at' => Carbon::createFromTimestampUTC((int) ($payload['created'] ?? now()->timestamp)),
                'payload_hash' => hash('sha256', $encoded),
                'payload' => $payload,
            ]
        );

        if ($event->provider !== 'stripe' || ! hash_equals($event->payload_hash, hash('sha256', $encoded))) {
            abort(409, 'Provider event ID was reused with different content.');
        }

        if (in_array($event->status, ['processed', 'ignored'], true)) {
            return $event;
        }

        try {
            DB::transaction(function () use ($event): void {
                $locked = BillingProviderEvent::query()->lockForUpdate()->findOrFail($event->getKey());
                if (in_array($locked->status, ['processed', 'ignored'], true)) {
                    return;
                }
                $locked->increment('attempts');
                $handled = $this->dispatch($locked->payload, $locked->provider_created_at);
                $businessId = $this->findSubscription((array) data_get($locked->payload, 'data.object', []))?->business_id;
                $locked->update(['business_id' => $businessId ?? $locked->business_id, 'status' => $handled ? 'processed' : 'ignored', 'processed_at' => now(), 'last_error' => null]);
            });
        } catch (Throwable $exception) {
            BillingProviderEvent::query()->whereKey($event->getKey())->update([
                'status' => 'failed',
                'attempts' => DB::raw('attempts + 1'),
                'last_error' => str($exception->getMessage())->limit(4000),
            ]);
            throw $exception;
        }

        return $event->fresh();
    }

    /** @param array<string, mixed> $payload */
    private function dispatch(array $payload, Carbon $occurredAt): bool
    {
        $type = (string) ($payload['type'] ?? '');
        $object = $payload['data']['object'] ?? [];
        $application = $this->metadata($object, 'application');
        if ($application !== null && $application !== 'clipperdesk') {
            return false;
        }

        return match ($type) {
            'checkout.session.completed' => $this->checkoutCompleted($object, $occurredAt),
            'checkout.session.expired', 'checkout.session.async_payment_failed' => $this->checkoutFailed($object, $type),
            'customer.subscription.created', 'customer.subscription.updated' => $this->subscriptionUpdated($object, $occurredAt),
            'customer.subscription.pending_update_expired' => $this->pendingUpdateExpired($object, $occurredAt),
            'customer.subscription.deleted' => $this->subscriptionDeleted($object, $occurredAt),
            'invoice.created', 'invoice.finalized', 'invoice.updated', 'invoice.paid', 'invoice.payment_succeeded', 'invoice.payment_failed' => $this->invoiceUpdated($object, $type, $occurredAt),
            default => false,
        };
    }

    /** @param array<string, mixed> $object */
    private function checkoutCompleted(array $object, Carbon $occurredAt): bool
    {
        if (($object['mode'] ?? null) !== 'subscription') {
            return false;
        }
        $subscription = $this->findSubscription($object, allowUnboundCheckout: true);
        if (! $subscription) {
            return false;
        }
        $attempt = $this->checkoutAttempt($object);
        if (! $attempt
            || $attempt->business_id !== $subscription->business_id
            || (string) $attempt->billing_plan_price_id !== (string) $this->metadata($object, 'plan_price_id')
            || ($object['client_reference_id'] ?? null) !== $subscription->business->public_id) {
            return false;
        }
        if (! in_array($attempt->status, ['pending', 'processing', 'confirmed'], true)) {
            $attempt->update([
                'last_checked_at' => now(),
                'last_error' => 'completed_after_checkout_was_closed',
            ]);

            return false;
        }
        $providerSubscriptionId = $object['subscription'] ?? $subscription->provider_subscription_id;
        $subscription->update([
            'provider_customer_id' => $object['customer'] ?? $subscription->provider_customer_id,
            'provider_subscription_id' => $providerSubscriptionId,
        ]);
        $alreadyActivated = filled($providerSubscriptionId)
            && $subscription->provider_subscription_id === $providerSubscriptionId
            && in_array($subscription->status->value, ['active', 'cancel_scheduled'], true);
        $attempt->update([
            'provider_subscription_id' => $providerSubscriptionId,
            'status' => $attempt->status === 'confirmed' || $alreadyActivated ? 'confirmed' : 'processing',
            'confirmed_at' => $attempt->status === 'confirmed' || $alreadyActivated
                ? ($attempt->confirmed_at ?? now())
                : null,
            'last_checked_at' => now(),
            'last_error' => null,
        ]);

        return true;
    }

    /** @param array<string, mixed> $object */
    private function checkoutFailed(array $object, string $eventType): bool
    {
        $attempt = $this->checkoutAttempt($object);
        if (! $attempt) {
            return false;
        }

        $attempt->update([
            'status' => $eventType === 'checkout.session.expired' ? 'expired' : 'failed',
            'expires_at' => now(),
            'last_checked_at' => now(),
            'last_error' => $eventType === 'checkout.session.expired' ? 'checkout_expired' : 'payment_failed',
        ]);

        return true;
    }

    /** @param array<string, mixed> $object */
    private function subscriptionUpdated(array $object, Carbon $occurredAt): bool
    {
        $subscription = $this->findSubscription($object);
        if (! $subscription) {
            return false;
        }
        $status = (string) ($object['status'] ?? '');
        if ($status === 'past_due') {
            $attempt = in_array($subscription->status->value, ['past_due', 'grace'], true) ? 2 : 1;
            $this->lifecycle->renewalFailed($subscription, $attempt, $occurredAt);

            return true;
        }
        if (in_array($status, ['unpaid', 'paused'], true)) {
            $this->lifecycle->providerRestricted($subscription, $occurredAt, $status);

            return true;
        }
        if (in_array($status, ['incomplete', 'incomplete_expired'], true)) {
            BillingCheckoutAttempt::query()
                ->where('business_subscription_id', $subscription->getKey())
                ->where('provider', 'stripe')
                ->where('provider_subscription_id', $object['id'] ?? null)
                ->whereIn('status', ['pending', 'processing'])
                ->update([
                    'status' => $status === 'incomplete_expired' ? 'failed' : 'processing',
                    'last_checked_at' => now(),
                    'last_error' => $status === 'incomplete_expired' ? 'incomplete_expired' : null,
                ]);

            return true;
        }
        if ($status === 'canceled') {
            $this->lifecycle->terminate($subscription, $occurredAt);

            return true;
        }
        if (! in_array($status, ['active', 'trialing'], true)) {
            return true;
        }

        $providerPriceId = data_get($object, 'items.data.0.price.id');
        $price = BillingPlanPrice::query()->where('provider', 'stripe')->where('provider_price_id', $providerPriceId)->first();
        if (! $price) {
            return false;
        }
        $periodStart = Carbon::createFromTimestampUTC((int) data_get($object, 'items.data.0.current_period_start', $object['current_period_start'] ?? $occurredAt->timestamp));
        $periodEnd = Carbon::createFromTimestampUTC((int) data_get($object, 'items.data.0.current_period_end', $object['current_period_end'] ?? $occurredAt->copy()->addMonth()->timestamp));
        $cancelAtTimestamp = ($object['cancel_at_period_end'] ?? false)
            ? ($object['cancel_at'] ?? $periodEnd->timestamp)
            : ($object['cancel_at'] ?? null);
        $cancelAt = $cancelAtTimestamp ? Carbon::createFromTimestampUTC((int) $cancelAtTimestamp) : null;
        $updated = $this->lifecycle->activate(
            $subscription, $price, $periodStart, $periodEnd, $occurredAt,
            $object['customer'] ?? null, $object['id'] ?? null,
            null, null, $cancelAt,
        );
        if (filled($object['id'] ?? null)) {
            $attempt = $this->checkoutAttempt($object);
            if ($attempt
                && $attempt->business_subscription_id === $updated->getKey()
                && $attempt->billing_plan_price_id === $price->getKey()
                && in_array($attempt->status, ['pending', 'processing', 'confirmed'], true)) {
                $attempt->update([
                    'provider_subscription_id' => $object['id'],
                    'status' => 'confirmed',
                    'confirmed_at' => $attempt->confirmed_at ?? now(),
                    'last_checked_at' => now(),
                    'last_error' => null,
                ]);
            }

            BillingCheckoutAttempt::query()
                ->where('business_subscription_id', $updated->getKey())
                ->where('provider', 'stripe')
                ->where('provider_subscription_id', $object['id'])
                ->whereIn('status', ['pending', 'processing'])
                ->update(['status' => 'confirmed', 'confirmed_at' => now(), 'last_checked_at' => now(), 'last_error' => null]);
        }

        return true;
    }

    /** @param array<string, mixed> $object */
    private function subscriptionDeleted(array $object, Carbon $occurredAt): bool
    {
        $subscription = $this->findSubscription($object);
        if ($subscription) {
            $this->lifecycle->terminate($subscription, $occurredAt);
        }

        return (bool) $subscription;
    }

    /** @param array<string, mixed> $object */
    private function pendingUpdateExpired(array $object, Carbon $occurredAt): bool
    {
        $subscription = $this->findSubscription($object);
        if (! $subscription) {
            return false;
        }

        $this->lifecycle->expirePendingProviderPlanChanges($subscription, $occurredAt);

        return true;
    }

    /** @param array<string, mixed> $object */
    private function invoiceUpdated(array $object, string $eventType, Carbon $occurredAt): bool
    {
        $subscription = $this->findSubscription($object);
        if (! $subscription || empty($object['id'])) {
            return false;
        }
        $invoice = BillingInvoice::query()->updateOrCreate(
            ['provider_invoice_id' => $object['id']],
            [
                'business_id' => $subscription->business_id,
                'business_subscription_id' => $subscription->getKey(),
                'provider' => 'stripe',
                'number' => $object['number'] ?? null,
                'status' => $object['status'] ?? ($eventType === 'invoice.payment_failed' ? 'open' : 'draft'),
                'currency' => strtoupper((string) ($object['currency'] ?? 'USD')),
                'subtotal_minor' => (int) ($object['subtotal'] ?? 0),
                'discount_minor' => (int) data_get($object, 'total_discount_amounts.0.amount', 0),
                'tax_minor' => (int) ($object['tax'] ?? 0),
                'total_minor' => (int) ($object['total'] ?? 0),
                'amount_due_minor' => (int) ($object['amount_due'] ?? 0),
                'amount_paid_minor' => (int) ($object['amount_paid'] ?? 0),
                'issued_at' => isset($object['created']) ? Carbon::createFromTimestampUTC((int) $object['created']) : $occurredAt,
                'due_at' => isset($object['due_date']) ? Carbon::createFromTimestampUTC((int) $object['due_date']) : null,
                'paid_at' => data_get($object, 'status_transitions.paid_at') ? Carbon::createFromTimestampUTC((int) data_get($object, 'status_transitions.paid_at')) : null,
                'hosted_url' => $object['hosted_invoice_url'] ?? null,
                'pdf_url' => $object['invoice_pdf'] ?? null,
                'line_items' => $object['lines']['data'] ?? [],
            ]
        );

        $paymentId = data_get($object, 'payments.data.0.payment.payment_intent')
            ?? data_get($object, 'payment_intent')
            ?? data_get($object, 'parent.subscription_details.latest_invoice_payment.payment_intent');
        $paymentSucceeded = in_array($eventType, ['invoice.paid', 'invoice.payment_succeeded'], true);
        if ($paymentId) {
            BillingPayment::query()->updateOrCreate(
                ['provider_payment_id' => $paymentId],
                [
                    'business_id' => $subscription->business_id,
                    'billing_invoice_id' => $invoice->getKey(),
                    'provider' => 'stripe',
                    'status' => $paymentSucceeded ? 'succeeded' : ($eventType === 'invoice.payment_failed' ? 'failed' : 'pending'),
                    'currency' => strtoupper((string) ($object['currency'] ?? 'USD')),
                    'amount_minor' => (int) ($object['amount_paid'] ?: $object['amount_due'] ?? 0),
                    'failure_code' => data_get($object, 'last_finalization_error.code'),
                    'failure_message' => $eventType === 'invoice.payment_failed' ? 'Stripe could not complete this subscription payment.' : null,
                    'attempted_at' => $occurredAt,
                    'paid_at' => $paymentSucceeded ? $occurredAt : null,
                ]
            );
        }

        if ($eventType === 'invoice.payment_failed') {
            $initialPayment = ($object['billing_reason'] ?? null) === 'subscription_create'
                && $subscription->status->value === 'trialing';
            if ($initialPayment) {
                BillingCheckoutAttempt::query()
                    ->where('business_subscription_id', $subscription->getKey())
                    ->where('provider', 'stripe')
                    ->whereIn('status', ['pending', 'processing'])
                    ->update(['status' => 'failed', 'last_error' => 'initial_payment_failed', 'last_checked_at' => now()]);
            } else {
                $this->lifecycle->renewalFailed($subscription, (int) ($object['attempt_count'] ?? 1), $occurredAt);
            }
        } elseif ($paymentSucceeded && in_array($subscription->status->value, ['past_due', 'grace', 'restricted'], true)) {
            $periodEnd = Carbon::createFromTimestampUTC((int) data_get($object, 'lines.data.0.period.end', $subscription->current_period_ends_at?->timestamp ?? now()->addMonth()->timestamp));
            $this->lifecycle->recover($subscription, $periodEnd, $occurredAt);
        }

        return true;
    }

    /** @param array<string, mixed> $object */
    private function findSubscription(array $object, bool $allowUnboundCheckout = false): ?BusinessSubscription
    {
        $providerSubscriptionId = is_string($object['subscription'] ?? null)
            ? $object['subscription']
            : data_get($object, 'parent.subscription_details.subscription');
        if (! $providerSubscriptionId && ($object['object'] ?? null) === 'subscription') {
            $providerSubscriptionId = $object['id'] ?? null;
        }
        $providerCustomerId = $object['customer'] ?? null;
        $businessPublicId = $this->metadata($object, 'business_public_id')
            ?? $object['client_reference_id']
            ?? null;

        $subscription = BusinessSubscription::query()
            ->where('provider', 'stripe')
            ->when($providerSubscriptionId, fn ($query) => $query->where('provider_subscription_id', $providerSubscriptionId))
            ->when(! $providerSubscriptionId && $providerCustomerId, fn ($query) => $query->where('provider_customer_id', $providerCustomerId))
            ->first();

        if ($subscription || ! $businessPublicId) {
            return $subscription;
        }

        $candidate = Business::query()->where('public_id', $businessPublicId)->first()?->subscription;

        if (! $candidate || $candidate->provider !== 'stripe') {
            return null;
        }

        if ($candidate->provider_subscription_id) {
            return $candidate->provider_subscription_id === $providerSubscriptionId ? $candidate : null;
        }

        if ($allowUnboundCheckout) {
            return $candidate;
        }

        $attempt = $this->checkoutAttempt($object);

        return $providerSubscriptionId
            && $attempt
            && $attempt->business_subscription_id === $candidate->getKey()
            && in_array($attempt->status, ['pending', 'processing', 'confirmed'], true)
            && (! $attempt->provider_subscription_id || $attempt->provider_subscription_id === $providerSubscriptionId)
                ? $candidate
                : null;
    }

    /** @param array<string, mixed> $object */
    private function checkoutAttempt(array $object): ?BillingCheckoutAttempt
    {
        $attemptId = $this->metadata($object, 'billing_checkout_attempt_id');

        return BillingCheckoutAttempt::query()
            ->where('provider', 'stripe')
            ->when($attemptId, fn ($query) => $query->where('public_id', $attemptId))
            ->when(! $attemptId && isset($object['id']), fn ($query) => $query->where('provider_transaction_id', $object['id']))
            ->first();
    }

    private function metadata(array $object, string $key): mixed
    {
        return data_get($object, "metadata.{$key}")
            ?? data_get($object, "subscription_details.metadata.{$key}")
            ?? data_get($object, "parent.subscription_details.metadata.{$key}");
    }
}
