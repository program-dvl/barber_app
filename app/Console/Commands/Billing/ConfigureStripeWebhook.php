<?php

namespace App\Console\Commands\Billing;

use App\Domain\Billing\Services\StripeBillingWebhook;
use Illuminate\Console\Command;

class ConfigureStripeWebhook extends Command
{
    protected $signature = 'billing:configure-stripe-webhook {url? : Public HTTPS billing webhook URL} {--apply : Create or update the managed endpoint} {--force : Allow live Stripe endpoint changes} {--local : Show the local Stripe CLI forwarding command}';

    protected $description = 'Preview or configure the business billing webhook, without charges or subscription changes.';

    public function handle(StripeBillingWebhook $webhook): int
    {
        if ($this->option('local')) {
            if ($this->option('apply') || $this->argument('url')) {
                $this->error('Use --local by itself.');

                return self::INVALID;
            }
            $this->line('stripe listen --events '.implode(',', StripeBillingWebhook::EVENTS).' --forward-to http://127.0.0.1:8000/billing/webhooks/stripe');
            $this->info('Keep the listener running. Put its whsec_ value in STRIPE_WEBHOOK_SECRET, then run php artisan config:clear. This is separate from a deployed endpoint secret.');

            return self::SUCCESS;
        }
        $url = (string) ($this->argument('url') ?: rtrim(config('app.url'), '/').'/billing/webhooks/stripe');
        try {
            $webhook->validateUrl($url);
            $this->line('Destination: '.$url);
            $this->line('Events: '.implode(', ', StripeBillingWebhook::EVENTS));
            if (! $this->option('apply')) {
                $this->info('Preview only. Add --apply to configure Stripe. Live changes also require --force.');

                return self::SUCCESS;
            }
            $secret = trim((string) config('billing.stripe.secret'));
            if (! str_starts_with($secret, 'sk_') && ! str_starts_with($secret, 'rk_')) {
                throw new \LogicException('Configure STRIPE_SECRET with a server-side Stripe key.');
            }
            if (! str_contains($secret, '_test_') && ! $this->option('force')) {
                throw new \LogicException('Live endpoint changes require --force.');
            }
            $file = storage_path('app/private/stripe-billing-webhook-'.(str_contains($secret, '_test_') ? 'test' : 'live').'-'.substr(hash('sha256', $url), 0, 12).'.json');
            $this->info($webhook->configure($url, $file));
            $this->line('Private recovery file: '.$file);
            $this->info('Set STRIPE_WEBHOOK_SECRET from this endpoint signing secret in your deployment environment; then rebuild the configuration cache and restart workers.');

            return self::SUCCESS;
        } catch (\LogicException $error) {
            $this->error($error->getMessage());
        } catch (\Throwable $error) {
            // Provider exceptions may include credentials or request bodies.
            $this->error('Stripe configuration failed. Review provider access and the private recovery file before retrying.');
        }

        return self::FAILURE;
    }
}
