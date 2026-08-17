<?php

namespace App\Domain\MoneyCommerce\Services;

use App\Domain\MoneyCommerce\Models\PaymentIntent;
use App\Domain\MoneyCommerce\Models\PaymentProviderEvent;
use App\Domain\MoneyCommerce\Models\PaymentTransaction;
use App\Domain\PublicBooking\Models\PublicBookingFlow;
use App\Domain\PublicBooking\Services\PublicBookingService;
use DomainException;
use Illuminate\Support\Facades\DB;
use Throwable;

class PaymentWebhookProcessor
{
    public function __construct(private readonly DepositService $deposits, private readonly PublicBookingService $booking) {}

    /** @param array{event_id:string,event_type:string,created_at:?\DateTimeInterface,payload:array<string,mixed>} $event */
    public function ingest(string $provider, array $event): PaymentProviderEvent
    {
        return DB::transaction(function () use ($provider, $event): PaymentProviderEvent {
            $hash = hash('sha256', json_encode($event['payload'], JSON_THROW_ON_ERROR));
            $row = PaymentProviderEvent::query()->firstOrCreate(['provider' => $provider, 'provider_event_id' => $event['event_id']], ['payload_hash' => $hash, 'signature_verified' => true, 'provider_created_at' => $event['created_at'], 'event_type' => $event['event_type'], 'payload' => $event['payload']]);
            if ($row->payload_hash !== $hash) {
                throw new DomainException('Provider event identifier was reused with a different payload.');
            }
            if ($row->processing_status === 'processed') {
                return $row;
            }
            $row->increment('attempts');
            try {
                $this->apply($provider, $event, $row);
                $row->update(['processing_status' => 'processed', 'processed_at' => now(), 'error' => null]);
            } catch (Throwable $error) {
                $row->update(['processing_status' => 'failed', 'error' => substr($error->getMessage(), 0, 2000)]);
                throw $error;
            }

            return $row->fresh();
        });
    }

    /** @param array<string,mixed> $event */
    private function apply(string $provider, array $event, PaymentProviderEvent $evidence): void
    {
        $object = data_get($event, 'payload.data.object', []);
        $providerIntentId = (string) ($object['id'] ?? '');
        if ($providerIntentId === '') {
            return;
        }
        $intent = PaymentIntent::query()->where('provider', $provider)->where('provider_intent_id', $providerIntentId)->lockForUpdate()->first();
        if (! $intent) {
            return;
        } // Verified but unrelated provider evidence is retained without guessing a tenant.
        $evidence->update(['business_id' => $intent->business_id]);
        $occurredAt = $event['created_at'] ?? now();
        $isSuccess = $event['event_type'] === 'payment_intent.succeeded';
        // A confirmed charge is terminal evidence even when a pending/failed notification arrived later.
        // Provider timestamps order event creation, not the order in which our inbox observed them.
        if (! $isSuccess && $intent->provider_state_at && $occurredAt < $intent->provider_state_at) {
            return;
        }
        if (! $isSuccess) {
            if ($intent->status !== 'succeeded') {
                $intent->update(['status' => str_contains($event['event_type'], 'failed') ? 'failed' : 'pending', 'provider_state_at' => $occurredAt]);
            }

            return;
        }
        if ($intent->status === 'succeeded') {
            return;
        }
        $intent->update(['status' => 'succeeded', 'provider_state_at' => $occurredAt, 'provider_evidence' => ['event_id' => $evidence->provider_event_id, 'charge_id' => data_get($object, 'latest_charge')]]);
        $payment = PaymentTransaction::query()->firstOrCreate(['business_id' => $intent->business_id, 'idempotency_key' => 'provider:'.$provider.':'.$providerIntentId], ['appointment_id' => $intent->appointment_id, 'payment_intent_id' => $intent->id, 'kind' => 'payment', 'method' => 'card', 'provider' => $provider, 'provider_reference' => $providerIntentId, 'amount_minor' => $intent->amount_minor, 'currency_code' => $intent->currency_code, 'evidence' => ['provider_event_id' => $evidence->provider_event_id], 'occurred_at' => $occurredAt]);
        if ($intent->purpose !== 'deposit') {
            return;
        }
        $flow = PublicBookingFlow::query()->where('capacity_hold_id', $intent->capacity_hold_id)->first();
        if (! $flow) {
            $this->reconcile($intent, 'missing_booking_flow', ['payment_transaction_id' => $payment->id]);

            return;
        }
        try {
            $result = $this->booking->confirm($flow->business, $flow, $intent->pending_booking_payload ?? [], 'payment:'.$intent->public_id, $intent->id);
            $appointment = $result['appointment'];
            $intent->update(['appointment_id' => $appointment->id]);
            if ($payment->appointment_id === null) {
                DB::table('payment_transactions')->where('id', $payment->id)->update(['appointment_id' => $appointment->id]);
            }
            $this->deposits->bind($appointment, $payment->fresh(), $intent->source_snapshot);
        } catch (Throwable $error) {
            $this->reconcile($intent, 'payment_succeeded_booking_finalization_failed', ['error' => $error->getMessage(), 'payment_transaction_id' => $payment->id, 'flow_id' => $flow->id]);
        }
    }

    /** @param array<string,mixed> $evidence */
    private function reconcile(PaymentIntent $intent, string $kind, array $evidence): void
    {
        DB::table('payment_reconciliation_tasks')->upsert([['business_id' => $intent->business_id, 'payment_intent_id' => $intent->id, 'kind' => $kind, 'status' => 'open', 'evidence' => json_encode($evidence, JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]], ['payment_intent_id', 'kind'], ['evidence', 'updated_at']);
    }
}
