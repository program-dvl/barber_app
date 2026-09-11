<?php

namespace App\Domain\Billing\Services;

class StripeBillingReadiness
{
    /** @return array{secret_configured: bool, webhook_configured: bool, checkout_ready: bool} */
    public function status(): array
    {
        $secret = trim((string) config('billing.stripe.secret'));
        $webhookSecret = trim((string) config('billing.stripe.webhook_secret'));
        $secretConfigured = str_starts_with($secret, 'sk_') || str_starts_with($secret, 'rk_');
        $webhookConfigured = str_starts_with($webhookSecret, 'whsec_');

        return [
            'secret_configured' => $secretConfigured,
            'webhook_configured' => $webhookConfigured,
            'checkout_ready' => $secretConfigured && $webhookConfigured,
        ];
    }
}
