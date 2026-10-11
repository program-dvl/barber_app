<?php

namespace App\Domain\Billing\Services;

use LogicException;
use Stripe\Stripe;
use Stripe\StripeClient;

class StripeBillingWebhook
{
    // Keep this list aligned with StripeWebhookProcessor::dispatch.
    public const EVENTS = [
        'checkout.session.completed', 'checkout.session.async_payment_succeeded',
        'checkout.session.expired', 'checkout.session.async_payment_failed',
        'customer.subscription.created', 'customer.subscription.updated',
        'customer.subscription.deleted', 'customer.subscription.pending_update_expired',
        'subscription_schedule.updated', 'subscription_schedule.released',
        'subscription_schedule.canceled', 'subscription_schedule.completed',
        'invoice.created', 'invoice.finalized', 'invoice.updated',
        'invoice.paid', 'invoice.payment_succeeded', 'invoice.payment_failed',
    ];

    public function validateUrl(string $url): void
    {
        $parts = parse_url($url);
        if (! filter_var($url, FILTER_VALIDATE_URL) || ($parts['scheme'] ?? '') !== 'https'
            || ($parts['path'] ?? '') !== '/billing/webhooks/stripe'
            || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['query']) || isset($parts['fragment'])
            || ! str_contains($parts['host'] ?? '', '.') || str_ends_with($parts['host'] ?? '', '.local')
            || filter_var($parts['host'], FILTER_VALIDATE_IP)) {
            throw new LogicException('Use the public HTTPS URL ending in /billing/webhooks/stripe. Use --local for localhost.');
        }
    }

    public function configure(string $url, string $secretFile): string
    {
        $this->validateUrl($url);
        $stripe = new StripeClient(config('billing.stripe.secret'));
        $existing = [];
        foreach ($stripe->webhookEndpoints->all(['limit' => 100])->autoPagingIterator() as $endpoint) {
            if ($endpoint->url === $url) {
                $existing[] = $endpoint;
            }
        }
        if (count($existing) > 1) {
            throw new LogicException('Multiple endpoints use this URL. Resolve duplicate delivery destinations in Stripe first.');
        }
        if ($existing) {
            $endpoint = $existing[0];
            if (($endpoint->metadata->application ?? null) !== 'clipperdesk'
                || ($endpoint->metadata->purpose ?? null) !== 'business_subscription') {
                throw new LogicException('An unmanaged endpoint already uses this URL. Review it in Stripe; it was not changed.');
            }
            $stripe->webhookEndpoints->update($endpoint->id, ['enabled_events' => self::EVENTS, 'disabled' => false]);

            return 'Existing managed endpoint verified. Its signing secret is unchanged.';
        }
        // Reserve a private recovery file before Stripe returns its one-time secret.
        $directory = dirname($secretFile);
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new LogicException('Cannot create the private webhook recovery directory.');
        }
        $handle = @fopen($secretFile, 'x');
        if (! $handle) {
            throw new LogicException('Recovery file already exists or is not writable. Review it before creating another endpoint.');
        }
        chmod($secretFile, 0600);
        try {
            $endpoint = $stripe->webhookEndpoints->create([
                'url' => $url, 'enabled_events' => self::EVENTS,
                // Pin the SDK API shape used by the existing projectors.
                'api_version' => Stripe::getApiVersion(),
                'description' => 'ClipperDesk business subscription billing',
                'metadata' => ['application' => 'clipperdesk', 'purpose' => 'business_subscription'],
            ], ['idempotency_key' => 'clipperdesk-billing-webhook-'.hash('sha256', $url)]);
            $contents = json_encode(['endpoint_id' => $endpoint->id, 'url' => $url, 'livemode' => $endpoint->livemode, 'signing_secret' => $endpoint->secret], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
            if (fwrite($handle, $contents) !== strlen($contents) || ! fflush($handle)) {
                throw new LogicException('Signing secret could not be saved. Recover or rotate this endpoint secret in Stripe before proceeding.');
            }
        } finally {
            fclose($handle);
        }

        return 'Endpoint created. The signing secret was saved privately; it was not printed.';
    }
}
