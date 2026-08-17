<?php

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Models\BillingInvoice;
use App\Domain\Billing\Models\BillingPayment;
use App\Domain\Billing\Models\BillingPlanPrice;
use App\Domain\Billing\Models\BillingProviderEvent;
use App\Domain\Billing\Models\BusinessSubscription;
use App\Domain\PlatformAccess\Models\Business;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/** Normalizes Paddle's signed event stream without making provider payloads our model. */
class PaddleWebhookProcessor
{
    public function __construct(private readonly SubscriptionLifecycleManager $lifecycle) {}

    /** @param array<string, mixed> $payload */
    public function receiveVerified(array $payload): BillingProviderEvent
    {
        $eventId = (string) ($payload['event_id'] ?? '');
        abort_if($eventId === '', 400, 'Paddle event ID is required.');
        $encoded = json_encode($payload, JSON_THROW_ON_ERROR);
        $event = BillingProviderEvent::query()->firstOrCreate(['provider_event_id' => $eventId], [
            'provider' => 'paddle', 'event_type' => (string) ($payload['event_type'] ?? 'unknown'), 'status' => 'pending',
            'signature_verified' => true, 'provider_created_at' => Carbon::parse((string) ($payload['occurred_at'] ?? now()), 'UTC'),
            'payload_hash' => hash('sha256', $encoded), 'payload' => $payload,
        ]);
        abort_unless($event->provider === 'paddle' && hash_equals($event->payload_hash, hash('sha256', $encoded)), 409, 'Provider event ID was reused with different content.');
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
                $businessId = $this->subscription((array) data_get($locked->payload, 'data', []))?->business_id;
                $locked->update(['business_id' => $businessId ?? $locked->business_id, 'status' => $handled ? 'processed' : 'ignored', 'processed_at' => now(), 'last_error' => null]);
            });
        } catch (Throwable $exception) {
            $event->update(['status' => 'failed', 'last_error' => str($exception->getMessage())->limit(4000)]);
            throw $exception;
        }

        return $event->fresh();
    }

    /** @param array<string, mixed> $payload */
    public function acceptsPayload(array $payload): bool
    {
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $application = data_get($data, 'custom_data.application');

        if ($application !== null) {
            return $application === 'good_hours';
        }

        return $this->subscription($data) !== null;
    }

    /**
     * Projects state retrieved directly from Paddle's authenticated API.
     * This is intentionally separate from receiveVerified() so API recovery is
     * never mislabeled as a signed webhook event.
     *
     * @param  array<string, mixed>  $transaction
     * @param  array<string, mixed>  $providerSubscription
     */
    public function projectConfirmedCheckout(array $transaction, array $providerSubscription, Carbon $occurredAt): BusinessSubscription
    {
        return DB::transaction(function () use ($transaction, $providerSubscription, $occurredAt): BusinessSubscription {
            $paymentType = data_get($transaction, 'payments.0.method_details.card.type')
                ?? data_get($transaction, 'payments.0.method_details.type');
            $lastFour = data_get($transaction, 'payments.0.method_details.card.last4');

            abort_unless($this->subscriptionUpdated($providerSubscription, $occurredAt, $paymentType, $lastFour), 409, 'The Paddle subscription could not be matched to this billing account.');
            abort_unless($this->transactionUpdated($transaction, 'transaction.completed', $occurredAt), 409, 'The Paddle payment could not be matched to this billing account.');

            return $this->subscription($providerSubscription)?->fresh(['plan', 'price'])
                ?? abort(409, 'The Paddle subscription could not be matched to this billing account.');
        });
    }

    /** @param array<string, mixed> $payload */
    private function dispatch(array $payload, Carbon $occurredAt): bool
    {
        $type = (string) ($payload['event_type'] ?? '');
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];

        return match ($type) {
            'subscription.created', 'subscription.updated', 'subscription.activated', 'subscription.trialing', 'subscription.resumed', 'subscription.past_due', 'subscription.canceled' => $this->subscriptionUpdated($data, $occurredAt),
            'transaction.completed', 'transaction.paid', 'transaction.payment_failed', 'transaction.past_due', 'transaction.billed' => $this->transactionUpdated($data, $type, $occurredAt),
            default => false,
        };
    }

    /** @param array<string, mixed> $data */
    private function subscriptionUpdated(array $data, Carbon $occurredAt, ?string $paymentType = null, ?string $lastFour = null): bool
    {
        $subscription = $this->subscription($data);
        if (! $subscription) {
            return false;
        }
        $status = (string) ($data['status'] ?? '');
        if ($status === 'canceled') {
            $this->lifecycle->terminate($subscription, $occurredAt);

            return true;
        }
        if ($status === 'past_due') {
            $this->lifecycle->renewalFailed($subscription, 1, $occurredAt);

            return true;
        }
        if (! in_array($status, ['active', 'trialing'], true)) {
            return true;
        }
        $price = BillingPlanPrice::query()->where('provider_price_id', data_get($data, 'items.0.price.id'))->first();
        if (! $price) {
            return false;
        }
        $this->lifecycle->activate(
            $subscription, $price,
            Carbon::parse((string) ($data['current_billing_period']['starts_at'] ?? $occurredAt), 'UTC'),
            Carbon::parse((string) ($data['current_billing_period']['ends_at'] ?? $occurredAt->copy()->addMonth()), 'UTC'),
            $occurredAt, $data['customer_id'] ?? null, $data['id'] ?? null,
            $paymentType, $lastFour,
        );

        return true;
    }

    /** @param array<string, mixed> $data */
    private function transactionUpdated(array $data, string $eventType, Carbon $occurredAt): bool
    {
        $subscription = $this->subscription($data);
        if (! $subscription || empty($data['id'])) {
            return false;
        }
        $totals = $data['details']['totals'] ?? [];
        $invoice = BillingInvoice::query()->updateOrCreate(['provider_invoice_id' => $data['id']], [
            'business_id' => $subscription->business_id, 'business_subscription_id' => $subscription->getKey(), 'provider' => 'paddle',
            'number' => $data['invoice_number'] ?? null, 'status' => $data['status'] ?? 'draft', 'currency' => strtoupper((string) ($data['currency_code'] ?? 'USD')),
            'subtotal_minor' => (int) ($totals['subtotal'] ?? 0), 'discount_minor' => (int) ($totals['discount'] ?? 0), 'tax_minor' => (int) ($totals['tax'] ?? 0),
            'total_minor' => (int) ($totals['total'] ?? 0), 'amount_due_minor' => (int) ($totals['total'] ?? 0),
            'amount_paid_minor' => in_array($eventType, ['transaction.completed', 'transaction.paid'], true) ? (int) ($totals['total'] ?? 0) : 0,
            'issued_at' => Carbon::parse((string) ($data['billed_at'] ?? $data['created_at'] ?? $occurredAt), 'UTC'),
            'paid_at' => in_array($eventType, ['transaction.completed', 'transaction.paid'], true) ? $occurredAt : null,
            'hosted_url' => data_get($data, 'checkout.url'), 'line_items' => $data['items'] ?? [],
        ]);
        $paymentId = data_get($data, 'payments.0.id') ?? $data['id'];
        if ($paymentId) {
            BillingPayment::query()->updateOrCreate(['provider_payment_id' => $paymentId], [
                'business_id' => $subscription->business_id, 'billing_invoice_id' => $invoice->getKey(), 'provider' => 'paddle',
                'status' => in_array($eventType, ['transaction.completed', 'transaction.paid'], true) ? 'succeeded' : 'failed',
                'currency' => strtoupper((string) ($data['currency_code'] ?? 'USD')), 'amount_minor' => (int) ($totals['total'] ?? 0),
                'attempted_at' => $occurredAt, 'paid_at' => in_array($eventType, ['transaction.completed', 'transaction.paid'], true) ? $occurredAt : null,
            ]);
        }
        if (in_array($eventType, ['transaction.payment_failed', 'transaction.past_due'], true)) {
            $this->lifecycle->renewalFailed($subscription, 1, $occurredAt);
        }
        if (in_array($eventType, ['transaction.completed', 'transaction.paid'], true) && in_array($subscription->status->value, ['past_due', 'grace', 'restricted'], true)) {
            $this->lifecycle->recover($subscription, Carbon::parse((string) ($data['billing_period']['ends_at'] ?? now()->addMonth()), 'UTC'), $occurredAt);
        }

        return true;
    }

    /** @param array<string, mixed> $data */
    private function subscription(array $data): ?BusinessSubscription
    {
        $providerSubscriptionId = $data['subscription_id'] ?? $data['id'] ?? null;
        $customerId = $data['customer_id'] ?? null;
        $businessPublicId = data_get($data, 'custom_data.business_public_id');

        return BusinessSubscription::query()->when($providerSubscriptionId, fn ($q) => $q->where('provider_subscription_id', $providerSubscriptionId))->when(! $providerSubscriptionId && $customerId, fn ($q) => $q->where('provider_customer_id', $customerId))->first()
            ?? ($businessPublicId ? Business::query()->where('public_id', $businessPublicId)->first()?->subscription : null);
    }
}
