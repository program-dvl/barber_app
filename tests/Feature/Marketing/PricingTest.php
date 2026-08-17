<?php

use App\Domain\Billing\Models\BillingPlanPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['billing.provider' => 'paddle', 'billing.trial_days' => 14]);
});

it('renders an honest unavailable state when provider prices are not mapped', function () {
    BillingPlanPrice::query()->get()->each(fn (BillingPlanPrice $price) => $price->update(['provider_price_id' => 'unmapped_'.$price->id]));

    $this->get(route('marketing.pricing'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Marketing/Pricing')
            ->where('catalog.available', false)
            ->has('catalog.plans', 0)
            ->where('seo.canonical', route('marketing.pricing')));
});

it('presents only the complete effective server-owned catalog and calculated savings', function () {
    $this->get(route('marketing.pricing'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('catalog.available', true)
            ->where('catalog.currency', 'USD')
            ->has('catalog.plans', 2)
            ->where('catalog.plans.0.code', 'starter')
            ->where('catalog.plans.0.prices.monthly.amount_minor', 5000)
            ->where('catalog.plans.0.prices.annual.amount_minor', 50000)
            ->where('catalog.plans.0.annual_savings_minor', 10000)
            ->where('catalog.plans.1.entitlements', fn ($entitlements) => $entitlements['staff.max'] === 20));
});

it('preserves an allow-listed plan preference through registration and rejects tampering', function () {
    $this->get(route('register', ['plan' => 'starter', 'interval' => 'annual']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('signupIntent', ['plan' => 'starter', 'interval' => 'annual']));

    $this->get(route('register', ['plan' => 'enterprise', 'interval' => 'daily']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('signupIntent', null));

    $response = $this->post(route('register'), [
        'name' => 'Plan Owner', 'business_name' => 'Plan Salon', 'email' => 'plan@example.test',
        'password' => 'Password!234', 'password_confirmation' => 'Password!234', 'terms' => true,
        'selected_plan' => 'pro', 'selected_interval' => 'monthly',
    ]);
    $response->assertSessionHasNoErrors();
    $this->assertDatabaseHas('owner_registration_intents', ['selected_plan_code' => 'pro', 'selected_billing_interval' => 'monthly']);

    $this->post(route('logout'));

    $this->post(route('register'), [
        'name' => 'Bad Plan', 'business_name' => 'Bad Plan Salon', 'email' => 'bad-plan@example.test',
        'password' => 'Password!234', 'password_confirmation' => 'Password!234', 'terms' => true,
        'selected_plan' => 'enterprise', 'selected_interval' => 'daily',
    ])->assertSessionHasErrors('selected_plan');
});

it('excludes expired prices from a public selection', function () {
    BillingPlanPrice::query()->where('billing_interval', 'annual')->firstOrFail()->update(['effective_until' => now()->subMinute()]);

    $this->get(route('marketing.pricing'))->assertInertia(fn (Assert $page) => $page->where('catalog.available', false));
});
