<?php

use App\Domain\Billing\Enums\BillingInterval;
use App\Domain\Billing\Models\BillingPlanPrice;
use App\Domain\Billing\Services\PlanCatalog;
use App\Domain\Billing\Services\StripeCatalogProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

it('keeps the approved Stripe catalog centralized and does not write during a dry run', function () {
    config([
        'billing.plans.starter.prices.monthly.price_id' => 'price_starter_monthly_catalog',
        'billing.plans.starter.prices.annual.price_id' => 'price_starter_annual_catalog',
        'billing.plans.pro.prices.monthly.price_id' => 'price_pro_monthly_catalog',
        'billing.plans.pro.prices.annual.price_id' => 'price_pro_annual_catalog',
    ]);

    $before = BillingPlanPrice::query()->where('provider', 'stripe')->count();
    $this->artisan('billing:sync-stripe-catalog')->assertSuccessful();

    expect(app(PlanCatalog::class)->codes())->toBe(['starter', 'pro'])
        ->and(app(PlanCatalog::class)->price('starter', BillingInterval::Monthly)['price_id'])->toBe('price_starter_monthly_catalog')
        ->and(BillingPlanPrice::query()->where('provider', 'stripe')->count())->toBe($before);
});

it('fails closed when any required Stripe price mapping is absent', function () {
    config([
        'billing.stripe.secret' => 'sk_test_catalog',
        'billing.plans.starter.prices.monthly.price_id' => null,
    ]);

    $this->artisan('billing:sync-stripe-catalog --apply')
        ->expectsOutputToContain('- monthly: missing STRIPE_STARTER_MONTHLY_PRICE_ID at USD 50.00')
        ->expectsOutputToContain('A valid Stripe Price ID is required in STRIPE_STARTER_MONTHLY_PRICE_ID.')
        ->assertFailed();
    expect(BillingPlanPrice::query()->where('provider', 'stripe')->count())->toBe(0);
});

it('provisions missing Stripe products and prices and synchronizes generated IDs without env mappings', function () {
    config([
        'billing.stripe.secret' => 'sk_test_catalog',
        'billing.plans.starter.prices.monthly.price_id' => null,
        'billing.plans.starter.prices.annual.price_id' => null,
        'billing.plans.pro.prices.monthly.price_id' => null,
        'billing.plans.pro.prices.annual.price_id' => null,
    ]);
    $provisioner = Mockery::mock(StripeCatalogProvisioner::class);
    $provisioner->shouldReceive('provision')->once()->andReturn([
        'prices' => [
            'starter' => ['monthly' => 'price_generated_starter_monthly', 'annual' => 'price_generated_starter_annual'],
            'pro' => ['monthly' => 'price_generated_pro_monthly', 'annual' => 'price_generated_pro_annual'],
        ],
        'replaced_price_ids' => ['price_replaced_starter_monthly'],
        'messages' => ['Created the managed test catalog.'],
    ]);
    $provisioner->shouldReceive('archiveReplaced')
        ->once()
        ->with(['price_replaced_starter_monthly'])
        ->andReturn(['Archived the replaced test price.']);
    app()->instance(StripeCatalogProvisioner::class, $provisioner);

    $this->artisan('billing:sync-stripe-catalog --provision')
        ->expectsOutputToContain('Created the managed test catalog.')
        ->assertSuccessful();

    $prices = BillingPlanPrice::query()->where('provider', 'stripe')->with('plan')->get();
    expect($prices)->toHaveCount(4)
        ->and($prices->every(fn (BillingPlanPrice $price): bool => app(PlanCatalog::class)->allows($price)))->toBeTrue();

    expect(Artisan::call('billing:sync-stripe-catalog'))->toBe(0);
    expect(Artisan::output())
        ->toContain('price_generated_starter_monthly (managed local mapping)')
        ->toContain('price_generated_pro_annual (managed local mapping)');
});

it('requires explicit force before provisioning against a live Stripe key', function () {
    config(['billing.stripe.secret' => 'sk_live_catalog']);
    $provisioner = Mockery::mock(StripeCatalogProvisioner::class);
    $provisioner->shouldNotReceive('provision');
    app()->instance(StripeCatalogProvisioner::class, $provisioner);

    $this->artisan('billing:sync-stripe-catalog --provision')
        ->expectsOutputToContain('Refusing to change a live Stripe catalog without --force.')
        ->assertFailed();
});

it('rejects a publishable Stripe key before attempting catalog provisioning', function () {
    config(['billing.stripe.secret' => 'pk_test_publishable_key_in_wrong_setting']);
    $provisioner = Mockery::mock(StripeCatalogProvisioner::class);
    $provisioner->shouldNotReceive('provision');
    app()->instance(StripeCatalogProvisioner::class, $provisioner);

    $this->artisan('billing:sync-stripe-catalog --provision')
        ->expectsOutputToContain('STRIPE_SECRET contains a publishable key (pk_…).')
        ->assertFailed();
});

it('keeps the LaraFast create command as a safe alias to the Business catalog provisioner', function () {
    config(['billing.stripe.secret' => 'sk_test_catalog']);
    $provisioner = Mockery::mock(StripeCatalogProvisioner::class);
    $provisioner->shouldReceive('provision')->once()->andReturn([
        'prices' => [
            'starter' => ['monthly' => 'price_alias_starter_monthly', 'annual' => 'price_alias_starter_annual'],
            'pro' => ['monthly' => 'price_alias_pro_monthly', 'annual' => 'price_alias_pro_annual'],
        ],
        'replaced_price_ids' => [],
        'messages' => [],
    ]);
    $provisioner->shouldReceive('archiveReplaced')->once()->with([])->andReturn([]);
    app()->instance(StripeCatalogProvisioner::class, $provisioner);

    $this->artisan('stripe:create-products-and-prices')->assertSuccessful();
    expect(BillingPlanPrice::query()->where('catalog_managed', true)->count())->toBe(4);
});
