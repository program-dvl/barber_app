<?php

use App\Domain\Billing\Models\BillingPlanPrice;
use App\Domain\Billing\Models\BillingRateCard;
use App\Domain\Billing\Services\CapacityBillingManager;
use App\Domain\Billing\Services\CapacityPricingCatalog;
use App\Domain\Billing\Services\StripeBillingWebhook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Inertia\Testing\AssertableInertia as Assert;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Stripe\HttpClient\CurlClient;

uses(RefreshDatabase::class);

function withSetupStripe(callable $operation): void
{
    $client = new class implements ClientInterface
    {
        public array $products = [];

        public array $prices = [];

        public array $endpoints = [];

        public array $writes = [];

        public bool $wrongPrice = false;

        public ?string $wrongTiers = null;

        public function request($method, $url, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
        {
            $path = parse_url($url, PHP_URL_PATH);
            if ($method !== 'get') {
                $this->writes[] = [$path, $params];
            }
            if ($path === '/v1/products') {
                if ($method === 'post') {
                    $object = $params + ['id' => 'prod_setup_'.count($this->products), 'object' => 'product', 'active' => true];
                    $this->products[] = $object;
                } else {
                    $object = ['object' => 'list', 'data' => $this->products, 'has_more' => false, 'url' => $path];
                }
            } elseif ($path === '/v1/prices') {
                if ($method === 'post') {
                    $object = $params + ['id' => 'price_setup_'.count($this->prices), 'object' => 'price', 'active' => true, 'type' => 'recurring'];
                    if ($object['billing_scheme'] === 'tiered') {
                        $object['unit_amount'] = null;
                        $object['tiers'] = array_map(fn ($tier) => ['up_to' => $tier['up_to'] === 'inf' ? null : (int) $tier['up_to'], 'unit_amount' => (int) $tier['unit_amount'], 'flat_amount' => null], $object['tiers']);
                    } else {
                        $object['unit_amount'] = (int) $object['unit_amount'];
                    }
                    $object['recurring']['interval_count'] = (int) $object['recurring']['interval_count'];
                    $this->prices[$object['id']] = $object;
                } else {
                    $object = ['object' => 'list', 'data' => array_values(array_filter($this->prices, fn ($p) => in_array($p['lookup_key'], $params['lookup_keys']))), 'has_more' => false, 'url' => $path];
                }
            } elseif (str_starts_with($path, '/v1/prices/')) {
                $object = $this->prices[basename($path)];
                if ($object['billing_scheme'] === 'tiered' && $this->wrongTiers) {
                    if ($this->wrongTiers === 'volume') {
                        $object['tiers_mode'] = 'volume';
                    }
                    if ($this->wrongTiers === 'amount') {
                        $object['tiers'][2]['unit_amount']++;
                    }
                    if ($this->wrongTiers === 'bound') {
                        $object['tiers'][0]['up_to']++;
                    }
                    if ($this->wrongTiers === 'flat') {
                        $object['tiers'][0]['flat_amount'] = 1;
                    }
                }
                if ($this->wrongPrice) {
                    $object['unit_amount']++;
                }
            } elseif ($path === '/v1/webhook_endpoints') {
                if ($method === 'post') {
                    $object = $params + ['id' => 'we_setup', 'object' => 'webhook_endpoint', 'livemode' => false, 'secret' => 'whsec_private_test'];
                    $this->endpoints[] = $object;
                } else {
                    $object = ['object' => 'list', 'data' => $this->endpoints, 'has_more' => false, 'url' => $path];
                }
            } elseif ($path === '/v1/webhook_endpoints/we_setup') {
                $object = $this->endpoints[0];
            } else {
                throw new RuntimeException('Unexpected Stripe request '.$path);
            }

            return [json_encode($object), 200, []];
        }
    };
    ApiRequestor::setHttpClient($client);
    config(['billing.stripe.secret' => 'sk_test_setup', 'capacity-billing.markets.US.approved' => true, 'capacity-billing.enabled' => true]);
    try {
        $operation($client);
    } finally {
        ApiRequestor::setHttpClient(CurlClient::instance());
    }
}

it('provisions six approved prices once and reloads verified mappings without env IDs', function () {
    withSetupStripe(function ($client) {
        $this->artisan('billing:verify-capacity-rates US --provision')->assertSuccessful();
        expect(count($client->products))->toBe(3)->and(count($client->prices))->toBe(6)
            ->and(BillingRateCard::count())->toBe(2);
        $writes = count($client->writes);
        $this->artisan('billing:verify-capacity-rates US --provision')->assertSuccessful();
        expect(count($client->writes))->toBe($writes)->and(BillingRateCard::count())->toBe(2);
        $catalog = app(CapacityPricingCatalog::class);
        expect($catalog->quote('US', 'monthly', 2, 3)['total_minor'])->toBe(6600)
            ->and($catalog->quote('US', 'annual', 2, 3)['total_minor'])->toBe(66000)
            ->and($catalog->present()['available'])->toBeTrue();
        config(['capacity-billing.markets.US.monthly.staff_minor' => 1000]);
        expect($catalog->present()['markets'][0]['intervals'])->not->toHaveKey('monthly');
    });
});

it('installs no mappings when Stripe prices do not match approved terms', function () {
    withSetupStripe(function ($client) {
        $client->wrongPrice = true;
        $this->artisan('billing:verify-capacity-rates US --provision')->assertFailed();
        expect(BillingRateCard::count())->toBe(0);
    });
});

it('prevents live provisioning and webhook mutation without the explicit force flag', function () {
    withSetupStripe(function ($client) {
        config(['billing.stripe.secret' => 'sk_live_setup']);
        $this->artisan('billing:verify-capacity-rates US --provision')->assertFailed();
        $this->artisan('billing:configure-stripe-webhook https://billing.example.com/billing/webhooks/stripe --apply')->assertFailed();
        expect($client->writes)->toBe([])->and(BillingRateCard::count())->toBe(0);
    });
});

it('identifies missing setup prerequisites even with force and leaves the catalog untouched', function (string $option, bool $approved, ?string $secret, array $messages) {
    withSetupStripe(function ($client) use ($option, $approved, $secret, $messages) {
        config(['capacity-billing.markets.US.approved' => $approved, 'billing.stripe.secret' => $secret]);
        $command = $this->artisan('billing:verify-capacity-rates US '.$option.' --force');
        foreach ($messages as $message) {
            $command->expectsOutputToContain($message);
        }
        $command->expectsOutputToContain('php artisan config:clear')->assertFailed();
        expect($client->products)->toBe([])->and($client->prices)->toBe([])
            ->and($client->writes)->toBe([])->and(BillingRateCard::count())->toBe(0);
    });
})->with(['--provision', '--apply'])->with([
    'unapproved' => [false, 'sk_test_setup', ['CAPACITY_US_APPROVED=true']],
    'missing secret' => [true, null, ['STRIPE_SECRET is missing']],
    'blank secret' => [true, '   ', ['STRIPE_SECRET is missing']],
    'publishable key' => [true, 'pk_test_setup', ['STRIPE_SECRET must be a server-side sk_ or rk_ key']],
    'both missing' => [false, '', ['CAPACITY_US_APPROVED=true', 'STRIPE_SECRET is missing']],
]);

it('creates a narrowly scoped webhook and privately recovers its secret without duplicates', function () {
    withSetupStripe(function ($client) {
        $path = sys_get_temp_dir().'/stripe-billing-test-'.str()->uuid().'.json';
        try {
            $webhook = app(StripeBillingWebhook::class);
            $webhook->configure('https://billing.example.com/billing/webhooks/stripe', $path);
            expect(json_decode(file_get_contents($path), true)['signing_secret'])->toBe('whsec_private_test')
                ->and(fileperms($path) & 0777)->toBe(0600)
                ->and($client->endpoints[0]['enabled_events'])->toBe(StripeBillingWebhook::EVENTS)
                ->and($client->endpoints[0]['enabled_events'])->not->toContain('payment_intent.succeeded', '*');
            $webhook->configure('https://billing.example.com/billing/webhooks/stripe', $path);
            expect(count($client->endpoints))->toBe(1);
            $client->endpoints[0]['metadata'] = [];
            expect(fn () => $webhook->configure('https://billing.example.com/billing/webhooks/stripe', $path))->toThrow(LogicException::class);
        } finally {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    });
});

it('rejects incorrect webhook routes and insecure destinations', function (string $url) {
    expect(fn () => app(StripeBillingWebhook::class)->validateUrl($url))->toThrow(LogicException::class);
})->with(['http://billing.example.com/billing/webhooks/stripe', 'https://billing.example.com/stripe/webhook', 'https://user:pass@billing.example.com/billing/webhooks/stripe', 'https://localhost/billing/webhooks/stripe', 'https://billing.example.com/billing/webhooks/stripe?secret=x']);

it('offers ready USD subscriptions to Canadian businesses without changing operating currency', function () {
    withSetupStripe(function () {
        $this->artisan('billing:verify-capacity-rates US --provision')->assertSuccessful();
        [$owner, $business] = createTenantMembership();
        $business->update(['country_code' => 'CA', 'currency_code' => 'CAD']);
        $subscription = activateTestSubscription($business);
        $subscription->update(['status' => 'trialing', 'provider_subscription_id' => null]);
        $change = app(CapacityBillingManager::class)->review($business, $owner, 'US', 'monthly', 1, 1);
        expect($change->quote['currency'])->toBe('USD')->and($business->fresh()->currency_code)->toBe('CAD');
        $this->actingAs($owner)->get(route('business.billing.show', $business))->assertInertia(fn (Assert $page) => $page
            ->where('billingCountry', 'US')->where('capacityPricing.markets.0.currency', 'USD')->has('capacityPricing.markets', 1));
        $this->actingAs($owner)->postJson(route('business.billing.capacity.review', $business), ['market' => 'CA', 'billing_interval' => 'monthly', 'locations' => 1, 'staff' => 1])->assertUnprocessable();
        $subscription->update(['status' => 'active', 'provider_subscription_id' => 'sub_cad_existing', 'billing_plan_price_id' => BillingPlanPrice::where('currency', 'CAD')->value('id')]);
        // Explicitly install existing CAD evidence; never silently migrate a paid currency.
        $price = BillingPlanPrice::create(['billing_plan_id' => $subscription->billing_plan_id, 'provider' => 'stripe', 'provider_price_id' => 'price_existing_cad', 'billing_interval' => 'monthly', 'currency' => 'CAD', 'amount_minor' => 5000, 'is_active' => true, 'effective_from' => now()->subHour()]);
        $subscription->update(['billing_plan_price_id' => $price->id]);
        $this->actingAs($owner)->postJson(route('business.billing.capacity.review', $business), ['market' => 'US', 'billing_interval' => 'monthly', 'locations' => 1, 'staff' => 1])->assertUnprocessable();
    });
});

it('verifies exact graduated staff bands before installing either rate card', function (string $kind) {
    withSetupStripe(function ($client) use ($kind) {
        $client->wrongTiers = $kind;
        $this->artisan('billing:verify-capacity-rates US --provision')->assertFailed();
        expect(BillingRateCard::count())->toBe(0);
    });
})->with(['volume', 'amount', 'bound', 'flat']);

it('provisions graduated staff prices and fresh immutable base prices for revised rates', function () {
    withSetupStripe(function ($client) {
        $this->artisan('billing:verify-capacity-rates US --provision')->assertSuccessful();
        $cards = BillingRateCard::all();
        foreach (['monthly' => [900, 600, 400], 'annual' => [9000, 6000, 4000]] as $interval => $amounts) {
            $card = $cards->firstWhere('billing_interval', $interval);
            $price = $client->prices[$card->terms['staff_price_id']];
            expect($price['billing_scheme'])->toBe('tiered')->and($price['tiers_mode'])->toBe('graduated')
                ->and(array_column($price['tiers'], 'up_to'))->toBe([4, 9, null])
                ->and(array_column($price['tiers'], 'unit_amount'))->toBe($amounts);
        }
        $this->travel(2)->seconds();
        config(['capacity-billing.markets.US.revision' => 'reviewed-future-revision']);
        $this->artisan('billing:verify-capacity-rates US --provision')->assertSuccessful();
        expect(BillingRateCard::count())->toBe(4)->and(count($client->prices))->toBe(12);
        foreach ($cards as $card) {
            expect($card->fresh()->terms)->toBe($card->terms);
        }
    });
});

it('reads both Stripe price cadences without provisioning or exposing provider details in checkout diagnostics', function () {
    withSetupStripe(function ($client) {
        $this->artisan('billing:verify-capacity-rates US --provision')->assertSuccessful();
        [, $business] = createTenantMembership();
        $writes = $client->writes;
        $counts = [BillingRateCard::count(), BillingPlanPrice::count()];
        foreach ([false, true] as $wrongPrice) {
            $client->wrongPrice = $wrongPrice;
            expect(Artisan::call('billing:inspect-checkout', ['business' => $business->public_id, '--provider' => true]))->toBe(0);
            $output = Artisan::output();
            $data = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
            foreach (['monthly', 'annual'] as $interval) {
                expect($data['provider_checks']['current_prices'][$interval]['base']['matches'])->toBe(! $wrongPrice)
                    ->and($data['provider_checks']['current_prices'][$interval]['staff']['matches'])->toBe(! $wrongPrice);
            }
            expect($output)->not->toContain('sk_test_', 'price_setup_', 'prod_setup_', 'whsec_');
        }
        expect($client->writes)->toBe($writes)->and([BillingRateCard::count(), BillingPlanPrice::count()])->toBe($counts);
    });
});
