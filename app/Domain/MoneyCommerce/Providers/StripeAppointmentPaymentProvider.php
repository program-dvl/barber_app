<?php

namespace App\Domain\MoneyCommerce\Providers;

use App\Domain\MoneyCommerce\Contracts\AppointmentPaymentProvider;
use App\Domain\MoneyCommerce\Models\PaymentIntent;
use Carbon\CarbonImmutable;
use DomainException;
use Stripe\StripeClient;
use Stripe\Webhook;

class StripeAppointmentPaymentProvider implements AppointmentPaymentProvider
{
    public function create(PaymentIntent $intent, string $returnUrl): array
    {
        $secret = (string) config('services.stripe.secret');
        if ($secret === '') {
            throw new DomainException('Online card payments are not configured.');
        }
        $client = new StripeClient($secret);
        $remote = $client->paymentIntents->create(['amount' => $intent->amount_minor, 'currency' => strtolower($intent->currency_code), 'metadata' => ['good_hours_intent' => $intent->public_id, 'business_id' => (string) $intent->business_id, 'purpose' => $intent->purpose], 'description' => 'Good Hours appointment deposit'], ['idempotency_key' => $intent->idempotency_key]);

        return ['provider_intent_id' => $remote->id, 'client_payload' => ['provider' => 'stripe', 'client_secret' => $remote->client_secret, 'return_url' => $returnUrl], 'expires_at' => null];
    }

    public function verifyWebhook(string $payload, ?string $signature): array
    {
        $secret = (string) config('commerce.stripe_webhook_secret');
        if ($secret === '' || ! $signature) {
            throw new DomainException('Missing payment webhook verification configuration.');
        }
        $event = Webhook::constructEvent($payload, $signature, $secret);

        return ['event_id' => $event->id, 'event_type' => $event->type, 'created_at' => CarbonImmutable::createFromTimestampUTC($event->created), 'payload' => $event->toArray()];
    }
}
