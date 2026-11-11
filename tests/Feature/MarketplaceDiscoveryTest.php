<?php

use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\Location;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

// Retain coverage for the compatibility directory path alongside the automatic supply suite.
beforeEach(fn () => config(['marketplace-growth.enabled' => false]));

function marketplaceSalon(array $attributes = [], string $address = 'Toronto, Canada'): Business
{
    $business = Business::factory()->create(array_merge([
        'booking_slug' => 'studio-'.strtolower((string) Str::ulid()), 'name' => 'Juniper Studio', 'status' => 'active', 'configuration_published_at' => now(),
        'online_booking_enabled' => true, 'email' => 'private@example.test',
    ], $attributes));
    Location::factory()->create(['business_id' => $business->id, 'address' => $address, 'status' => 'active', 'is_active' => true]);

    return $business;
}

it('lists published salons by default and never exposes private business fields', function () {
    $listed = marketplaceSalon();
    marketplaceSalon(['name' => 'Hidden by owner', 'marketplace_consented_at' => now(), 'marketplace_listed' => false]);
    config(['marketplace.listed_slugs' => [$listed->booking_slug]]);
    $this->get('/marketplace')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Marketplace/Discover')->has('results.data', 1)
        ->where('results.data.0.name', 'Juniper Studio')
        ->missing('results.data.0.email')->missing('results.data.0.id')
        ->missing('results.data.0.reviews.items'));
});

it('excludes paused draft and suspended businesses even when enrolled', function () {
    $slugs = collect([
        marketplaceSalon(['online_booking_enabled' => false]),
        marketplaceSalon(['configuration_published_at' => null]),
        marketplaceSalon(['status' => 'suspended']),
    ])->pluck('booking_slug')->all();
    config(['marketplace.listed_slugs' => $slugs]);
    $this->get('/marketplace')->assertInertia(fn (Assert $page) => $page->has('results.data', 0));
});

it('searches only public names and active location addresses and preserves filters', function () {
    $toronto = marketplaceSalon();
    $london = marketplaceSalon(['name' => 'Elm Studio'], 'London, UK');
    config(['marketplace.listed_slugs' => [$toronto->booking_slug, $london->booking_slug]]);
    $this->get('/marketplace?q=Juniper&location=Toronto')->assertInertia(fn (Assert $page) => $page
        ->has('results.data', 1)->where('filters.q', 'Juniper')->where('filters.location', 'Toronto'));
    $this->get('/marketplace?location=Vancouver')->assertInertia(fn (Assert $page) => $page->has('results.data', 0));
    $this->get('/marketplace?q=private@example.test')->assertInertia(fn (Assert $page) => $page->has('results.data', 0));
    $toronto->locations()->update(['is_active' => false]);
    $this->get('/marketplace?q=Juniper')->assertInertia(fn (Assert $page) => $page->has('results.data', 0));
});

it('validates query shapes and keeps an empty marketplace usable', function () {
    config(['marketplace.listed_slugs' => []]);
    $this->get('/marketplace')->assertOk()->assertInertia(fn (Assert $page) => $page->has('results.data', 0));
    $this->getJson('/marketplace?q[]=bad')->assertUnprocessable();
    $this->getJson('/marketplace?page=0')->assertUnprocessable();
});
