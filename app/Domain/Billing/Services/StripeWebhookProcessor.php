<?php

namespace App\Domain\Billing\Services;

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

    /** @param array<string, mixed> $payload */
    public function receiveVerified(array $payload): BillingProviderEvent
    {
        $providerEventId = (string) ($payload['id'] ?? '');
        abort_if($providerEventId === '', 400, 'Provider event ID is required.');
        $encoded = json_encode($payload, JSON_THROW_ON_ERROR);

        $event = BillingProviderEvent::query()->firstOrCreate(
            ['provider_event_id' => $providerEventId],
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

        if (! hash_equals($event->payload_hash, hash('sha256', $encoded))) {
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
            $event->update(['status' => 'failed', 'last_error' => str($exception->getMessage())->limit(4000)]);
            throw $exception;
        }

        return $event->fresh();
    }

    /** @param array<string, mixed> $payload */
    private function dispatch(array $payload, Carbon $occurredAt): bool
    {
        $type = (string) ($payload['type'] ?? '');
        $object = $payload['data']['object'] ?? [];

        return match ($type) {
            'checkout.session.completed' => $this->checkoutCompleted($object, $occurredAt),
            'customer.subscription.created', 'customer.subscription.updated' => $this->subscriptionUpdated($object, $occurredAt),
            'customer.subscription.deleted' => $this->subscriptionDeleted($object, $occurredAt),
            'invoice.created', 'invoice.finalized', 'invoice.updated', 'invoice.paid', 'invoice.payment_failed' => $this->invoiceUpdated($object, $type, $occurredAt),
            default => false,
        };
    }

    /** @param array<string, mixed> $object */
    private function checkoutCompleted(array $object, Carbon $occurredAt): bool
    {
        $subscription = $this->findSubscription($object);
        if (! $subscription) {
            return false;
        }
        if ($subscription->provider_state_at && $occurredAt->lessThanOrEqualTo($subscription->provider_state_at)) {
            return true;
        }
        $subscription->update([
            'provider_customer_id' => $object['customer'] ?? $subscription->provider_customer_id,
            'provider_subscription_id' => $object['subscription'] ?? $subscription->provider_subscription_id,
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
        if (in_array($status, ['past_due', 'unpaid'], true)) {
            $attempt = in_array($subscription->status->value, ['past_due', 'grace'], true) ? 2 : 1;
            $this->lifecycle->renewalFailed($subscription, $attempt, $occurredAt);

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
        $price = BillingPlanPrice::query()->where('provider_price_id', $providerPriceId)->first();
        if (! $price) {
            return false;
        }
        $periodStart = Carbon::createFromTimestampUTC((int) data_get($object, 'items.data.0.current_period_start', $object['current_period_start'] ?? $occurredAt->timestamp));
        $periodEnd = Carbon::createFromTimestampUTC((int) data_get($object, 'items.data.0.current_period_end', $object['current_period_end'] ?? $occurredAt->copy()->addMonth()->timestamp));
        $this->lifecycle->activate(
            $subscription, $price, $periodStart, $periodEnd, $occurredAt,
            $object['customer'] ?? null, $object['id'] ?? null,
            data_get($object, 'default_payment_method.card.brand'), data_get($object, 'default_payment_method.card.last4')
        );

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

        $paymentId = data_get($object, 'payments.data.0.payment.payment_intent') ?? data_get($object, 'payment_intent');
        if ($paymentId) {
            BillingPayment::query()->updateOrCreate(
                ['provider_payment_id' => $paymentId],
                [
                    'business_id' => $subscription->business_id,
                    'billing_invoice_id' => $invoice->getKey(),
                    'provider' => 'stripe',
                    'status' => $eventType === 'invoice.paid' ? 'succeeded' : ($eventType === 'invoice.payment_failed' ? 'failed' : 'pending'),
                    'currency' => strtoupper((string) ($object['currency'] ?? 'USD')),
                    'amount_minor' => (int) ($object['amount_paid'] ?: $object['amount_due'] ?? 0),
                    'failure_code' => data_get($object, 'last_finalization_error.code'),
                    'failure_message' => data_get($object, 'last_finalization_error.message'),
                    'attempted_at' => $occurredAt,
                    'paid_at' => $eventType === 'invoice.paid' ? $occurredAt : null,
                ]
            );
        }

        if ($eventType === 'invoice.payment_failed') {
            $this->lifecycle->renewalFailed($subscription, (int) ($object['attempt_count'] ?? 1), $occurredAt);
        } elseif ($eventType === 'invoice.paid' && in_array($subscription->status->value, ['past_due', 'grace', 'restricted'], true)) {
            $periodEnd = Carbon::createFromTimestampUTC((int) data_get($object, 'lines.data.0.period.end', $subscription->current_period_ends_at?->timestamp ?? now()->addMonth()->timestamp));
            $this->lifecycle->recover($subscription, $periodEnd, $occurredAt);
        }

        return true;
    }

    /** @param array<string, mixed> $object */
    private function findSubscription(array $object): ?BusinessSubscription
    {
        $providerSubscriptionId = is_string($object['subscription'] ?? null) ? $object['subscription'] : ($object['id'] ?? null);
        $providerCustomerId = $object['customer'] ?? null;
        $businessPublicId = data_get($object, 'metadata.business_public_id') ?? data_get($object, 'subscription_details.metadata.business_public_id') ?? $object['client_reference_id'] ?? null;

        return BusinessSubscription::query()
            ->when($providerSubscriptionId, fn ($query) => $query->where('provider_subscription_id', $providerSubscriptionId))
            ->when(! $providerSubscriptionId && $providerCustomerId, fn ($query) => $query->where('provider_customer_id', $providerCustomerId))
            ->first()
            ?? ($businessPublicId ? Business::query()->where('public_id', $businessPublicId)->first()?->subscription : null);
    }
}
