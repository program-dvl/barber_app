<?php

namespace App\Domain\MoneyCommerce\Services;

use App\Domain\MoneyCommerce\Contracts\AppointmentPaymentProvider;
use App\Domain\MoneyCommerce\Models\PaymentIntent;
use App\Domain\SchedulingOperations\Models\CapacityHold;
use DomainException;
use Illuminate\Support\Facades\DB;

class PaymentIntentService
{
    public function __construct(private readonly AppointmentPaymentProvider $provider) {}

    /** @param array<string,mixed> $policy @param array<string,mixed> $bookingPayload
     * @return array{intent:PaymentIntent,client_payload:array<string,mixed>} */
    public function startDeposit(CapacityHold $hold, array $policy, array $bookingPayload, string $idempotencyKey, string $returnUrl): array
    {
        return DB::transaction(function () use ($hold, $policy, $bookingPayload, $idempotencyKey, $returnUrl): array {
            $hash = hash('sha256', json_encode([$hold->id, $policy, $bookingPayload], JSON_THROW_ON_ERROR));
            $existing = PaymentIntent::query()->where('business_id', $hold->business_id)->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($existing) {
                return ['intent' => $existing, 'client_payload' => $existing->provider_evidence['client_payload'] ?? []];
            }
            if ($hold->status !== 'active' || $hold->expires_at->isPast()) {
                throw new DomainException('This booking hold is no longer available.');
            }
            $intent = PaymentIntent::query()->create(['business_id' => $hold->business_id, 'capacity_hold_id' => $hold->id, 'purpose' => 'deposit', 'provider' => 'stripe', 'idempotency_key' => $idempotencyKey, 'request_hash' => $hash, 'status' => 'pending', 'amount_minor' => $policy['amount_minor'], 'currency_code' => $policy['currency_code'], 'expires_at' => now()->addMinutes(15), 'source_snapshot' => $policy, 'pending_booking_payload' => $bookingPayload]);
            $created = $this->provider->create($intent, $returnUrl);
            $intent->update(['provider_intent_id' => $created['provider_intent_id'], 'expires_at' => $created['expires_at'] ?? $intent->expires_at, 'provider_evidence' => ['client_payload' => $created['client_payload']]]);
            $hold->update(['expires_at' => $intent->expires_at]);

            return ['intent' => $intent->fresh(), 'client_payload' => $created['client_payload']];
        });
    }
}
