<?php

use App\Actions\Auth\CompleteGoogleRegistration;
use App\Domain\Billing\Models\BillingPlan;
use App\Domain\Billing\Models\BillingPlanPrice;
use App\Domain\Billing\Models\BillingRateCard;
use App\Domain\Billing\Models\BusinessSubscription;
use App\Domain\Billing\Models\OwnerRegistrationIntent;
use App\Domain\Billing\Services\CapacityPricingCatalog;
use App\Domain\Billing\Services\PublicPricingCatalog;
use App\Domain\PlatformAccess\Enums\StarterRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['billing.trial_days' => 14, 'capacity-billing.enabled' => true, 'capacity-billing.pricing_mode' => 'usd', 'capacity-billing.markets.US.approved' => true]);
    foreach (['monthly', 'annual'] as $interval) {
        foreach (['base', 'location', 'staff'] as $role) {
            config(["capacity-billing.markets.US.{$interval}.{$role}_price_id" => "price_public_{$interval}_{$role}"]);
        }
        $catalog = app(CapacityPricingCatalog::class);
        $terms = $catalog->terms('US', $interval);
        $price = BillingPlanPrice::create(['billing_plan_id' => BillingPlan::where('code', 'capacity')->value('id'), 'billing_interval' => $interval,
            'currency' => 'USD', 'amount_minor' => $terms['base_minor'], 'provider' => 'stripe', 'provider_price_id' => $terms['base_price_id'],
            'catalog_managed' => true, 'is_active' => true, 'effective_from' => now()->subMinute()]);
        BillingRateCard::create(['fingerprint' => $catalog->fingerprint($terms), 'market' => 'US', 'currency' => 'USD', 'revision' => $terms['revision'],
            'billing_interval' => $interval, 'billing_plan_price_id' => $price->id, 'terms' => $terms, 'verified_at' => now()]);
    }
});

it('publishes the same verified capacity components without exposing provider identifiers or tier offers', function () {
    $this->get(route('marketing.pricing'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Marketing/Pricing')->where('catalog.available', true)->has('catalog.markets', 1)
        ->where('catalog.markets.0.currency', 'USD')->where('catalog.markets.0.intervals.monthly.base_minor', 2900)
        ->where('catalog.markets.0.intervals.monthly.location_minor', 1900)->where('catalog.markets.0.intervals.monthly.staff_minor', 900)
        ->where('catalog.markets.0.intervals.annual.base_minor', 29000)->where('catalog.markets.0.intervals.annual.staff_minor', 9000)
        ->where('catalog.trial_days', 14)->missing('catalog.plans')->missing('capacityPricing')
        ->missing('catalog.markets.0.intervals.monthly.rate_card_id')->missing('catalog.markets.0.intervals.monthly.base_price_id')
        ->missing('catalog.markets.0.intervals.monthly.fingerprint')->where('seo.canonical', route('marketing.pricing')));
});

it('keeps a trial path without falling back to retired tiers when capacity rates cannot be sold', function (string $gate) {
    config([$gate => false]);
    $this->get(route('marketing.pricing'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('catalog.available', false)->has('catalog.markets', 0)->missing('catalog.plans'));
    expect(app(PublicPricingCatalog::class)->validSelection('capacity', 'monthly'))->toBeNull();
})->with(['capacity-billing.enabled', 'capacity-billing.markets.US.approved']);

it('removes changed or expired rates from public selections', function () {
    config(['capacity-billing.markets.US.monthly.base_minor' => 3000]);
    $this->get(route('marketing.pricing'))->assertInertia(fn (Assert $page) => $page
        ->missing('catalog.markets.0.intervals.monthly')->has('catalog.markets.0.intervals.annual'));
    expect(app(PublicPricingCatalog::class)->validSelection('capacity', 'monthly'))->toBeNull();
    BillingPlanPrice::where('billing_interval', 'annual')->update(['effective_until' => now()]);
    $this->get(route('marketing.pricing'))->assertInertia(fn (Assert $page) => $page->where('catalog.available', false));
});

it('preserves quantity and cadence preferences through registration without granting paid access', function () {
    $this->get(route('register', ['plan' => 'capacity', 'interval' => 'annual', 'locations' => 2, 'staff' => 8]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('signupIntent', ['plan' => 'capacity', 'interval' => 'annual', 'locations' => 2, 'staff' => 8]));
    $this->post(route('register'), [
        'name' => 'Plan Owner', 'business_name' => 'Plan Salon', 'email' => 'plan@example.test',
        'password' => 'Password!234', 'password_confirmation' => 'Password!234', 'terms' => true,
        'selected_plan' => 'capacity', 'selected_interval' => 'annual', 'selected_locations' => 2, 'selected_staff' => 8,
        'amount_minor' => 1, 'payment_status' => 'paid',
    ])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('owner_registration_intents', ['selected_plan_code' => 'capacity', 'selected_billing_interval' => 'annual', 'selected_locations' => 2, 'selected_staff' => 8]);
    expect(BusinessSubscription::count())->toBe(0);
});

it('rejects retired tiers and malformed quantity preferences', function (mixed $plan, mixed $interval, mixed $locations, mixed $staff) {
    $this->get(route('register', compact('plan', 'interval', 'locations', 'staff')))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('signupIntent', null));
    $this->post(route('register'), [
        'name' => 'Invalid Owner', 'business_name' => 'Invalid Salon', 'email' => 'invalid@example.test',
        'password' => 'Password!234', 'password_confirmation' => 'Password!234', 'terms' => true,
        'selected_plan' => $plan, 'selected_interval' => $interval, 'selected_locations' => $locations, 'selected_staff' => $staff,
    ])->assertSessionHasErrors('selected_plan');
    $this->assertDatabaseMissing('users', ['email' => 'invalid@example.test']);
})->with([
    ['starter', 'monthly', 1, 1], ['pro', 'annual', 1, 1], ['capacity', 'daily', 1, 1],
    ['capacity', 'monthly', 0, 1], ['capacity', 'monthly', 1, 1.5], ['capacity', 'monthly', 1001, 1],
    ['capacity', 'monthly', 1, 10001], ['capacity', 'monthly', ['bad'], 1],
]);

it('retains the server saved Google selection rather than submitted replacement quantities', function () {
    $user = app(CompleteGoogleRegistration::class)->handle([
        'name' => 'Google Preview', 'email' => 'google-pricing@example.test', 'account_id' => 'google_pricing_fixture',
        'selection' => ['plan' => 'capacity', 'interval' => 'annual', 'locations' => 2, 'staff' => 8],
    ], ['business_name' => 'Google Studio', 'terms' => true, 'selected_staff' => 999]);
    $this->assertDatabaseHas('owner_registration_intents', ['user_id' => $user->id, 'selected_plan_code' => 'capacity', 'selected_staff' => 8, 'selected_locations' => 2]);
    expect(BusinessSubscription::where('status', 'active')->count())->toBe(0);
});

it('exposes only the current owners own saved preference to the billing calculator', function () {
    [$owner, $business] = createTenantMembership(StarterRole::Owner);
    activateTestSubscription($business);
    OwnerRegistrationIntent::create(['user_id' => $owner->id, 'business_id' => $business->id,
        'business_name' => $business->name, 'selected_plan_code' => 'capacity', 'selected_billing_interval' => 'annual', 'selected_locations' => 2, 'selected_staff' => 8, 'status' => 'completed']);
    $this->actingAs($owner)->get(route('business.billing.show', $business))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('signupSelection.selected_locations', 2)->where('signupSelection.selected_staff', 8)->where('signupSelection.selected_billing_interval', 'annual'));
});

it('publishes component pricing in structured data and omits unavailable offers', function () {
    $response = $this->get(route('marketing.pricing'))->assertOk();
    preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $response->getContent(), $matches);
    $graph = json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR);
    $software = collect($graph['@graph'])->firstWhere('@type', 'SoftwareApplication');
    expect($software['offers'])->toHaveCount(2)
        ->and($software['offers'][0]['priceSpecification']['@type'])->toBe('CompoundPriceSpecification')
        ->and(array_column($software['offers'][0]['priceSpecification']['priceComponent'], 'price'))->toBe(['29.00', '19.00', '9.00'])
        ->and(array_column($software['offers'][1]['priceSpecification']['priceComponent'], 'price'))->toBe(['290.00', '190.00', '90.00'])
        ->and($software['offers'][1]['priceSpecification']['priceComponent'][0]['billingDuration'])->toBe('P1Y');
    config(['capacity-billing.enabled' => false]);
    $html = $this->get(route('marketing.pricing'))->assertOk()->getContent();
    preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches);
    $graph = json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR);
    expect(collect($graph['@graph'])->firstWhere('@type', 'SoftwareApplication'))->not->toHaveKey('offers');
});
