<?php

namespace App\Console\Commands\Billing;

use App\Domain\Billing\Models\BillingProviderEvent;
use App\Domain\Billing\Services\CapacityPricingCatalog;
use App\Domain\Billing\Services\StripeBillingWebhook;
use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Http;
use Stripe\Stripe;

class CheckProductionBilling extends Command
{
    protected $signature = 'billing:check-production
        {--provider-read : Read Stripe account, endpoint and price configuration with GET requests only}
        {--webhook-url= : Inspect an explicit canonical HTTPS billing callback URL}';

    protected $description = 'Check production subscription configuration without changing Stripe, sending notices or replaying events.';

    public function handle(CapacityPricingCatalog $catalog, StripeBillingWebhook $webhooks, Schedule $schedule): int
    {
        $ok = true;
        $report = function (string $name, bool $pass, string $detail = '') use (&$ok): void {
            $ok = $ok && $pass;
            $this->line(($pass ? 'PASS ' : 'BLOCK ').$name.($detail === '' ? '' : ': '.$detail));
        };
        $secret = trim((string) config('billing.stripe.secret'));
        $report('Production application settings', app()->environment('production') && ! config('app.debug'));
        $report('Live Stripe server key', (bool) preg_match('/^(sk|rk)_live_\S+$/', $secret));
        $report('Live publishable key', str_starts_with((string) config('billing.stripe.key'), 'pk_live_'));
        $report('Billing signing secret present', str_starts_with((string) config('billing.stripe.webhook_secret'), 'whsec_'));
        $report('Capacity checkout enabled', (bool) config('capacity-billing.enabled'));
        $url = (string) ($this->option('webhook-url') ?: rtrim((string) config('app.url'), '/').'/billing/webhooks/stripe');
        try {
            $webhooks->validateUrl($url);
            $report('Public HTTPS billing callback', true);
        } catch (\LogicException) {
            $report('Public HTTPS billing callback', false);
        }
        $this->line('Expected snapshot API version: '.Stripe::getApiVersion());
        $this->line('Queue connection: '.config('queue.default').'. External scheduler/worker liveness must be checked in hosting.');
        foreach (['billing:advance-lifecycle', 'billing:reconcile-provider-events', 'billing:reconcile-stripe-checkouts', 'billing:send-notices'] as $command) {
            $report('Scheduled '.$command, collect($schedule->events())->contains(fn ($event) => str_contains((string) $event->command, $command)));
        }
        try {
            $failed = BillingProviderEvent::where('provider', 'stripe')->whereIn('status', ['pending', 'failed'])->count();
            $report('No pending/failed billing events', $failed === 0, (string) $failed);
            $evidence = BillingProviderEvent::where('provider', 'stripe')->where('signature_verified', true)->where('status', 'processed')
                ->where('payload->livemode', true)->where('created_at', '>=', now()->subDays(30))->exists();
            $report('Recent processed live signed event', $evidence, $evidence ? 'Journal evidence exists; correlate actual delivery in Stripe' : 'Unverified: no recent live projection evidence; a configured secret alone does not prove delivery');
        } catch (\Throwable) {
            $report('Billing event journal readable', false, 'Could not inspect saved delivery evidence');
        }
        if (! $this->option('provider-read')) {
            $this->line('Stripe account, event destination and prices were not read. Use --provider-read to inspect them.');

            return $ok ? self::SUCCESS : self::FAILURE;
        }
        if (! preg_match('/^(sk|rk)_live_\S+$/', $secret)) {
            $this->line('Skipped provider reads: a live server key is required.');

            return self::FAILURE;
        }
        $get = function (string $path, array $query = []) use ($secret): array {
            $response = Http::withToken($secret)->withHeaders(['Stripe-Version' => Stripe::getApiVersion()])
                ->withOptions(['allow_redirects' => false])->timeout(15)->get('https://api.stripe.com/v1/'.$path, $query);
            if (! $response->successful() || ! is_array($response->json())) {
                throw new \RuntimeException('Stripe read returned HTTP '.$response->status());
            }

            return $response->json();
        };
        try {
            try {
                $account = $get('account');
                $report('Live account can accept payments', ($account['charges_enabled'] ?? false) === true);
                $this->line('Compare connected Stripe account '.($account['id'] ?? 'unknown').' with the intended live dashboard.');
            } catch (\RuntimeException $error) {
                if (! str_starts_with($secret, 'rk_live_') || $error->getMessage() !== 'Stripe read returned HTTP 403') {
                    throw $error;
                }
                // Billing-only keys need not expose connected-account details.
                // Continue the endpoint/price checks without claiming account approval.
                $this->line('REVIEW Account approval: this restricted key cannot read account details. Verify payment activation in the intended live Stripe dashboard.');
            }
            $endpoints = [];
            $after = null;
            do {
                $page = $get('webhook_endpoints', array_filter(['limit' => 100, 'starting_after' => $after]));
                $data = $page['data'] ?? [];
                $endpoints = [...$endpoints, ...array_filter($data, fn ($item) => ($item['url'] ?? '') === $url)];
                $after = end($data)['id'] ?? null;
                if (($page['has_more'] ?? false) && ! $after) {
                    throw new \RuntimeException('Incomplete endpoint listing');
                }
            } while ($page['has_more'] ?? false);
            $report('Exactly one matching billing endpoint', count($endpoints) === 1);
            if (count($endpoints) === 1) {
                $endpoint = $endpoints[0];
                $report('Live billing endpoint enabled', ($endpoint['livemode'] ?? false) === true && ($endpoint['status'] ?? '') === 'enabled');
                // Stripe guarantees additive monthly versions within a named
                // release. Do not falsely reject a later compatible monthly version.
                $sdkVersion = Stripe::getApiVersion();
                $endpointVersion = $endpoint['api_version'] ?? '';
                $compatible = preg_match('/^\d{4}-\d{2}-\d{2}\.([a-z]+)$/', $sdkVersion, $sdkRelease)
                    && preg_match('/^\d{4}-\d{2}-\d{2}\.([a-z]+)$/', $endpointVersion, $endpointRelease)
                    && $sdkRelease[1] === $endpointRelease[1] && strcmp($endpointVersion, $sdkVersion) >= 0;
                $report('Compatible snapshot API version', (bool) $compatible, $endpointVersion ?: 'Unpinned/unknown');
                $events = $endpoint['enabled_events'] ?? [];
                $missing = array_diff(StripeBillingWebhook::EVENTS, $events);
                $report('Required billing event selection', ! $missing || in_array('*', $events, true), implode(', ', $missing));
                $extra = array_diff($events, StripeBillingWebhook::EVENTS);
                if ($extra) {
                    $this->line('REVIEW Extra endpoint event types: '.implode(', ', $extra));
                }
            }
            foreach (['monthly', 'annual'] as $interval) {
                $market = (string) config('capacity-billing.default_market', 'US');
                $card = $catalog->verified($market, $interval);
                $report('Verified '.$interval.' rate card', (bool) $card);
                if (! $card) {
                    continue;
                }
                foreach (['base', 'location', 'staff'] as $role) {
                    $id = $card->terms[$role.'_price_id'] ?? '';
                    if (! is_string($id) || ! preg_match('/^price_[a-zA-Z0-9_]+$/', $id)) {
                        $report($interval.' '.$role.' price', false, 'Missing price mapping');

                        continue;
                    }
                    $price = $get('prices/'.$id, ['expand' => ['tiers']]);
                    $matches = ($price['active'] ?? false) === true && ($price['livemode'] ?? false) === true
                        && ($price['type'] ?? '') === 'recurring' && ($price['tax_behavior'] ?? '') === 'exclusive'
                        && strtoupper($price['currency'] ?? '') === $card->currency
                        && data_get($price, 'recurring.interval') === ($interval === 'annual' ? 'year' : 'month')
                        && data_get($price, 'recurring.interval_count') === 1 && data_get($price, 'recurring.usage_type') === 'licensed'
                        && $catalog->matchesStripePrice($price, $card->terms, $role, true);
                    $report($interval.' '.$role.' price', $matches);
                }
            }
        } catch (\Throwable $error) {
            // Raw provider exceptions may contain credentials or customer data.
            $report('Stripe read checks', false, preg_match('/^Stripe read returned HTTP \d{3}$/', $error->getMessage()) ? $error->getMessage() : 'Could not complete provider reads');
        }
        $this->line('No Stripe or billing records changed. No charges, notices or replays created.');
        $this->line('Signing-secret presence does not verify its match to this endpoint; confirm signed delivery separately.');
        $this->line('Renewal/cancellation rehearsal, delivery diagnostics, tax and account retry/portal policies still require separate evidence.');

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
