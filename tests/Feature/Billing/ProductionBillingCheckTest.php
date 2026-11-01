<?php

use App\Domain\Billing\Models\BillingPlan;
use App\Domain\Billing\Models\BillingPlanPrice;
use App\Domain\Billing\Models\BillingProviderEvent;
use App\Domain\Billing\Models\BillingRateCard;
use App\Domain\Billing\Services\CapacityPricingCatalog;
use App\Domain\Billing\Services\StripeBillingWebhook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function productionBillingCheckFixture(): array
{
    app()->instance('env', 'production');
    config([
        'app.debug' => false, 'app.url' => 'https://clipperdesk.com',
        'billing.stripe.secret' => 'sk_live_read_check_secret',
        'billing.stripe.key' => 'pk_live_read_check_public',
        'billing.stripe.webhook_secret' => 'whsec_read_check_secret',
        'capacity-billing.enabled' => true, 'capacity-billing.markets.US.approved' => true,
    ]);
    $prices = [];
    $catalog = app(CapacityPricingCatalog::class);
    foreach (['monthly', 'annual'] as $interval) {
        foreach (['base', 'location', 'staff'] as $role) {
            config(['capacity-billing.markets.US.'.$interval.'.'.$role.'_price_id' => 'price_'.$interval.'_'.$role]);
        }
        $terms = $catalog->terms('US', $interval);
        $planPrice = BillingPlanPrice::create([
            'billing_plan_id' => BillingPlan::where('code', 'capacity')->value('id'),
            'billing_interval' => $interval, 'currency' => 'USD', 'amount_minor' => $terms['base_minor'],
            'provider' => 'stripe', 'provider_price_id' => $terms['base_price_id'],
            'catalog_managed' => true, 'is_active' => true, 'effective_from' => now()->subDay(),
        ]);
        BillingRateCard::create([
            'market' => 'US', 'currency' => 'USD', 'revision' => $terms['revision'],
            'billing_interval' => $interval, 'terms' => $terms,
            'fingerprint' => $catalog->fingerprint($terms), 'verified_at' => now(), 'billing_plan_price_id' => $planPrice->id,
        ]);
        foreach (['base', 'location', 'staff'] as $role) {
            $prices[$terms[$role.'_price_id']] = [
                'active' => true, 'livemode' => true, 'type' => 'recurring', 'currency' => 'usd',
                'tax_behavior' => 'exclusive', 'billing_scheme' => $role === 'staff' ? 'tiered' : 'per_unit',
                'tiers_mode' => $role === 'staff' ? 'graduated' : null,
                'unit_amount' => $role === 'staff' ? null : $terms[$role.'_minor'],
                'tiers' => $role === 'staff' ? array_map(fn ($t) => ['up_to' => $t['up_to'], 'unit_amount' => $t['unit_minor']], $terms['staff_tiers']) : null,
                'recurring' => ['interval' => $interval === 'annual' ? 'year' : 'month', 'interval_count' => 1, 'usage_type' => 'licensed'],
            ];
        }
    }
    BillingProviderEvent::create([
        'provider' => 'stripe', 'provider_event_id' => 'evt_read_check', 'event_type' => 'invoice.paid',
        'status' => 'processed', 'signature_verified' => true, 'payload' => ['livemode' => true],
        'payload_hash' => hash('sha256', 'read_check'), 'provider_created_at' => now(),
    ]);

    return $prices;
}

it('checks live endpoint and all recurring prices using only GETs without writing billing records or exposing secrets', function (bool $restricted) {
    $prices = productionBillingCheckFixture();
    if ($restricted) {
        config(['billing.stripe.secret' => 'rk_live_read_check_secret']);
    }
    Http::preventStrayRequests();
    Http::fake(function ($request) use ($prices, $restricted) {
        expect($request->method())->toBe('GET');
        if (str_contains($request->url(), '/account')) {
            if ($restricted) {
                return Http::response(['error' => ['message' => 'account read is outside billing permissions']], 403);
            }

            return Http::response(['id' => 'acct_expected', 'charges_enabled' => true]);
        }
        if (str_contains($request->url(), '/webhook_endpoints')) {
            // Later additive monthly API versions are supported by Stripe.
            return Http::response(['has_more' => false, 'data' => [[
                'id' => 'we_expected', 'url' => 'https://clipperdesk.com/billing/webhooks/stripe',
                'livemode' => true, 'status' => 'enabled', 'api_version' => '2026-07-29.dahlia',
                'enabled_events' => StripeBillingWebhook::EVENTS,
            ]]]);
        }

        return Http::response($prices[basename(parse_url($request->url(), PHP_URL_PATH))]);
    });
    $before = BillingProviderEvent::first()->getAttributes();
    expect(Artisan::call('billing:check-production', ['--provider-read' => true]))->toBe(0);
    $output = Artisan::output();
    expect($output)->toContain('PASS Compatible snapshot API version', 'PASS annual staff price', $restricted ? 'REVIEW Account approval:' : 'acct_expected')
        ->not->toContain('sk_live_read_check_secret', 'rk_live_read_check_secret', 'whsec_read_check_secret');
    Http::assertSentCount(8);
    expect(BillingRateCard::count())->toBe(2)->and(BillingProviderEvent::count())->toBe(1)
        ->and(BillingProviderEvent::first()->getAttributes())->toBe($before);
})->with([false, true]);

it('does not contact Stripe unless provider reads are explicitly requested', function () {
    productionBillingCheckFixture();
    Http::fake();
    expect(Artisan::call('billing:check-production'))->toBe(0);
    Http::assertNothingSent();
});

it('fails safely for provider outages without logging provider payloads', function () {
    productionBillingCheckFixture();
    Http::fake(['*' => Http::response(['error' => ['message' => 'sensitive_provider_text']], 401)]);
    expect(Artisan::call('billing:check-production', ['--provider-read' => true]))->toBe(1);
    expect(Artisan::output())->toContain('Stripe read returned HTTP 401')->not->toContain('sensitive_provider_text');
    Http::assertSentCount(1);
});

it('blocks incompatible event versions, missing renewal events and test price mappings', function () {
    $prices = productionBillingCheckFixture();
    $prices['price_monthly_base']['livemode'] = false;
    Http::fake(function ($request) use ($prices) {
        if (str_contains($request->url(), '/account')) {
            return Http::response(['id' => 'acct_expected', 'charges_enabled' => true]);
        }
        if (str_contains($request->url(), '/webhook_endpoints')) {
            return Http::response(['has_more' => false, 'data' => [[
                'id' => 'we_bad', 'url' => 'https://clipperdesk.com/billing/webhooks/stripe',
                'livemode' => true, 'status' => 'enabled', 'api_version' => '2026-10-28.endive',
                'enabled_events' => ['checkout.session.completed'],
            ]]]);
        }

        return Http::response($prices[basename(parse_url($request->url(), PHP_URL_PATH))]);
    });
    expect(Artisan::call('billing:check-production', ['--provider-read' => true]))->toBe(1);
    expect(Artisan::output())->toContain('BLOCK Compatible snapshot API version', 'BLOCK Required billing event selection', 'invoice.paid', 'BLOCK monthly base price');
});
