<?php

use App\Domain\BusinessConfiguration\Services\ServiceCatalogManager;
use App\Domain\Marketplace\LocalGrowth\Services\PublicOfferQuery;
use App\Domain\Marketplace\LocalGrowth\Services\PublicSupplyProjection;
use App\Support\Locations\LocationData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

require_once __DIR__.'/../../Support/MarketplaceGrowthFixture.php';
uses(RefreshDatabase::class);
beforeEach(function () {
    Queue::fake();
    $this->withoutVite();
    config(['inertia.ssr.enabled' => false]);
});

it('keeps reviewed many-to-many corrections inside the authorized Services command with replay and stale checks', function () {
    $f = growthSupply('Custom dog wash & trim');
    $service = $f['service']->fresh()->load(ServiceCatalogManager::RELATIONS);
    $revision = app(ServiceCatalogManager::class)->revision($service);
    $data = [...$service->only(ServiceCatalogManager::FIELDS), 'command_key' => (string) Str::uuid(), 'category' => '', 'revision' => $revision, 'impact_revision' => app(ServiceCatalogManager::class)->impactRevision(collect()), 'location_ids' => [$f['location']->public_id], 'staff_ids' => [$f['staff']->public_id], 'addon_ids' => [], 'discovery_concepts' => ['dog-grooming', 'pet-grooming']];
    $url = route('business.services.update', [$f['business'], $service]);
    $this->actingAs($f['user'])->put($url, $data)->assertSessionHasNoErrors();
    $this->put($url, $data)->assertSessionHasNoErrors();
    expect(DB::table('marketplace_service_concepts')->where('service_id', $service->id)->count())->toBe(2);
    expect($service->fresh()->name)->toBe('Custom dog wash & trim');
    app(PublicSupplyProjection::class)->rebuild($f['business']->id);
    $offers = app(PublicOfferQuery::class)->term(app(PublicOfferQuery::class)->query(), 'audience', 'pet-grooming');
    expect($offers->count())->toBe(1);
    $this->put($url, [...$data, 'command_key' => (string) Str::uuid(), 'discovery_concepts' => []])->assertSessionHasErrors('revision');
    $other = growthSupply();
    $this->actingAs($other['user'])->put($url, $data)->assertForbidden();
});

it('requires an explicit public location choice and rejects foreign geography without erasing saved details', function () {
    $f = growthSupply();
    $data = [...$f['location']->only(['name', 'address', 'time_zone', ...LocationData::FIELDS]), 'location' => $f['location']->public_id, 'keep_hours' => true, 'is_primary' => false, 'working_days' => [1, 2, 3, 4, 5, 6, 7], 'opens_at' => '09:00', 'closes_at' => '18:00', 'discovery_geography' => $f['geography']->public_id, 'discovery_address_public' => true];
    $url = route('business.locations.activation.store', $f['business']);
    $this->actingAs($f['user'])->post($url, $data)->assertSessionHasNoErrors();
    expect(DB::table('marketplace_location_geographies')->where('business_id', $f['business']->id)->count())->toBe(1);
    $this->post($url, [...$data, 'discovery_geography' => (string) Str::ulid()])->assertSessionHasErrors('discovery_geography');
    expect($f['location']->fresh()->address)->toBe($data['address']);
    $this->post($url, [...$data, 'discovery_address_public' => false])->assertSessionHasNoErrors();
    app(PublicSupplyProjection::class)->rebuild($f['business']->id);
    expect(DB::table('marketplace_public_offers')->where('business_id', $f['business']->id)->value('geography_id'))->toBeNull();
});

it('keeps legacy profile requests compatible with automatic profiles and scopes owner reports', function () {
    $f = growthSupply();
    $this->actingAs($f['user'])->post(route('business.marketplace.publication', $f['business']), ['version' => 1, 'consent' => true])->assertSessionHasNoErrors();
    expect(DB::table('marketplace_seo_pages')->value('status'))->toBe('approved')->and(config('marketplace-growth.indexation_enabled'))->toBeFalse();
    $this->get(route('business.marketplace.settings', $f['business']))->assertOk()->assertInertia(fn (Assert $p) => $p->where('publication.status', 'approved')->where('publication.approved', true));
    $this->get(route('business.marketplace.outcomes', $f['business']))->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')->assertHeader('Cache-Control', 'no-store, private')->assertInertia(fn (Assert $p) => $p->has('report.rows', 0)->where('searchConsole.available', false));
    $other = growthSupply();
    $this->get(route('business.marketplace.outcomes', $f['business']).'?location='.$other['location']->public_id)->assertNotFound();
    $this->actingAs($other['user'])->get(route('business.marketplace.outcomes', $f['business']))->assertForbidden();
});
