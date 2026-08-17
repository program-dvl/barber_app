<?php

namespace App\Domain\MoneyCommerce\Contracts;

use App\Domain\MoneyCommerce\Models\PaymentIntent;

interface AppointmentPaymentProvider
{
    /** @return array{provider_intent_id:string,client_payload:array<string,mixed>,expires_at:?\DateTimeInterface} */
    public function create(PaymentIntent $intent, string $returnUrl): array;

    /** @return array{event_id:string,event_type:string,created_at:?\DateTimeInterface,payload:array<string,mixed>} */
    public function verifyWebhook(string $payload, ?string $signature): array;
}
