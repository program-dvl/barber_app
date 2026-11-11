<?php

use App\Domain\BusinessConfiguration\Models\StaffServiceAssignment;
use App\Domain\Marketplace\LocalGrowth\Jobs\RefreshPublicSupply;
use App\Domain\Marketplace\LocalGrowth\Services\DiscoveryTaxonomy;
use App\Domain\Marketplace\LocalGrowth\Services\ProjectionWork;
use App\Domain\Marketplace\LocalGrowth\Services\PublicOfferQuery;
use App\Domain\Marketplace\LocalGrowth\Services\PublicSupplyProjection;
use App\Domain\Marketplace\LocalGrowth\Services\SupplyDiscovery;
use App\Domain\Marketplace\Services\DiscoveryFilters;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

require_once __DIR__.'/../../Support/MarketplaceFixture.php';
uses(RefreshDatabase::class);

beforeEach(function () {
    config(['marketplace-growth.enabled' => false]);
    Queue::fake();
});

it('covers every Solutions audience with real qualified offers without rewriting operational types', function () {
    $examples = [
        'barbershops' => ['barber_shop', 'Skin fade'], 'salons' => ['hair_salon', 'Cut & finish'],
        'independent-stylists' => ['independent_professional', 'Gel manicure'], 'spas' => ['spa', 'Sauna session'],
        'nail-salons' => ['nail_studio', 'Gel manicure'], 'medspas' => ['skin_aesthetics', 'Aesthetics consultation'],
        'massage' => ['massage', 'Deep tissue massage'], 'fitness-recovery' => ['wellness', 'Personal training'],
        'physical-therapy' => ['other', 'Physical therapy appointment'], 'health-practices' => ['other', 'Practice appointment'],
        'tattoo-piercing' => ['other', 'Piercing appointment'], 'pet-grooming' => ['other', 'Dog grooming'], 'tanning-studios' => ['other', 'Tanning session'],
    ];
    expect(array_keys($examples))->toBe(array_keys(config('frontsite.solutions')));
    foreach ($examples as $audience => [$type, $name]) {
        $f = marketplaceBookablePath();
        $f['business']->update(['business_type' => $type]);
        $f['service']->update(['name' => $name]);
        app(PublicSupplyProjection::class)->rebuild($f['business']->id);
        $offers = app(PublicOfferQuery::class)->query()->where('o.business_id', $f['business']->id);
        app(PublicOfferQuery::class)->term($offers, 'audience', $audience);
        expect($offers->count())->toBe(1, $audience);
        expect($f['business']->fresh()->business_type)->toBe($type);
        expect($f['service']->fresh()->name)->toBe($name);
    }
    expect(app(DiscoveryTaxonomy::class)->terms(['gel-manicure'], 'nail_salon')['audience'])->toContain('nail-salons');
    expect(app(DiscoveryTaxonomy::class)->concepts('Consultation', 'skin_aesthetics'))->toBe([]);
    expect(app(DiscoveryTaxonomy::class)->concepts('Dog grooming', 'other'))->toBe(['dog-grooming']);
    expect(app(DiscoveryTaxonomy::class)->terms(['dog-grooming'], 'other')['audience'])->not->toContain('barbershops');
});

it('fails closed immediately on pause removal qualification expiry and foreign branches', function () {
    $f = marketplaceBookablePath();
    $other = marketplaceBookablePath();
    app(PublicSupplyProjection::class)->rebuild($f['business']->id);
    $query = fn () => app(PublicOfferQuery::class)->query()->where('o.business_id', $f['business']->id)->count();
    expect($query())->toBe(1);
    $f['business']->forceFill(['marketplace_listed' => false, 'marketplace_consented_at' => now()])->save();
    expect($query())->toBe(0);
    $f['business']->forceFill(['marketplace_listed' => true])->save();
    DB::table('staff_service_assignments')->where('business_id', $f['business']->id)->update(['is_qualified' => false]);
    expect($query())->toBe(0);
    DB::table('staff_service_assignments')->where('business_id', $f['business']->id)->update(['is_qualified' => true, 'effective_until' => now()->subSecond()]);
    expect($query())->toBe(0);
    DB::table('staff_service_assignments')->where('business_id', $f['business']->id)->update(['effective_until' => null]);
    DB::table('location_staff_profile')->where('business_id', $f['business']->id)->delete();
    expect(fn () => $f['staff']->locations()->attach($other['location']->id, ['business_id' => $f['business']->id]))->toThrow(QueryException::class);
    expect($query())->toBe(0);
    app(PublicSupplyProjection::class)->rebuild($f['business']->id);
    expect($query())->toBe(0);
});

it('rebuilds current truth idempotently and never restores stale events or rolled back changes', function () {
    $f = marketplaceBookablePath();
    config(['marketplace-growth.enabled' => true]);
    app(PublicSupplyProjection::class)->rebuild($f['business']->id);
    $row = DB::table('marketplace_public_offers')->first();
    app(PublicSupplyProjection::class)->rebuild($f['business']->id);
    expect(DB::table('marketplace_public_offers')->count())->toBe(1);
    expect(DB::table('marketplace_public_offers')->first()->materially_changed_at)->toBe($row->materially_changed_at);
    DB::beginTransaction();
    $f['service']->update(['name' => 'Rolled back']);
    DB::rollBack();
    app(PublicSupplyProjection::class)->rebuild($f['business']->id);
    expect(DB::table('marketplace_public_offers')->first()->service_name)->toBe('Signature Cut');
    $f['service']->fresh()->update(['name' => 'Custom exact service']);
    expect(app(PublicOfferQuery::class)->query()->count())->toBe(0); // dirty until worker commits
    (new RefreshPublicSupply($f['business']->id))->handle(app(PublicSupplyProjection::class));
    (new RefreshPublicSupply($f['business']->id))->handle(app(PublicSupplyProjection::class)); // old duplicate work
    expect(DB::table('marketplace_public_offers')->first()->service_name)->toBe('Custom exact service');
    expect(app(SupplyDiscovery::class)->search(['q' => 'Custom exact service'])['results']->total())->toBe(1);
    expect(DB::table('marketplace_offer_terms')->count())->toBe(0);
    $f['business']->update(['online_booking_enabled' => false]);
    app(PublicSupplyProjection::class)->rebuild($f['business']->id);
    expect(DB::table('marketplace_public_offers')->count())->toBe(0);
});

it('keeps a future qualification transition from serving an expired projection and reconciles safely', function () {
    $f = marketplaceBookablePath();
    DB::table('staff_service_assignments')->where('business_id', $f['business']->id)->update(['effective_until' => now()->addSeconds(20)]);
    app(PublicSupplyProjection::class)->rebuild($f['business']->id);
    $this->travel(21)->seconds();
    expect(app(PublicOfferQuery::class)->query()->count())->toBe(0);
    config(['marketplace-growth.enabled' => true]);
    $this->artisan('marketplace:growth-reconcile --all')->assertSuccessful();
    expect(DB::table('marketplace_public_offers')->count())->toBe(0);
});

it('bounds SQL reads on 1000 qualified synthetic offers across all audiences', function () {
    $names = ['Skin fade', 'Gel manicure', 'Sauna session', 'Dog grooming', 'Tanning session', 'Personal training', 'Physical therapy appointment', 'Practice appointment', 'Aesthetics consultation', 'Tattoo consultation'];
    for ($i = 0; $i < 100; $i++) {
        $f = marketplaceBookablePath();
        $f['business']->update(['business_type' => 'multi_service', 'name' => 'Synthetic business '.str_pad((string) $i, 3, '0', STR_PAD_LEFT)]);
        $f['service']->update(['name' => $names[0]]);
        foreach (array_slice($names, 1) as $name) {
            $s = $f['service']->replicate()->fill(['public_id' => (string) Str::ulid(), 'name' => $name]);
            $s->save();
            $s->locations()->attach($f['location']->id, ['business_id' => $f['business']->id, 'is_eligible' => true]);
            StaffServiceAssignment::query()->create(['business_id' => $f['business']->id, 'staff_profile_id' => $f['staff']->id, 'service_id' => $s->id, 'is_active' => true, 'is_qualified' => true, 'online_visible' => true]);
        }
        app(PublicSupplyProjection::class)->rebuild($f['business']->id);
    }
    expect(DB::table('marketplace_public_offers')->count())->toBe(1000);
    $this->mock(DiscoveryFilters::class, fn ($mock) => $mock->shouldNotReceive('match'));
    DB::enableQueryLog();
    DB::flushQueryLog();
    $started = hrtime(true);
    $a = app(SupplyDiscovery::class)->search(['q' => 'Dog grooming']);
    $b = app(SupplyDiscovery::class)->suggestions();
    $elapsed = (hrtime(true) - $started) / 1e6;
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();
    expect($a['results']->total())->toBe(100)->and($a['results']->count())->toBe(12)->and(count($b))->toBe(10);
    expect($count)->toBeLessThanOrEqual(4);
    expect($elapsed)->toBeLessThan(2500);
    file_put_contents('/tmp/marketplace-growth-load.json', json_encode(['fixture' => '100 businesses / 1000 real qualified service offers; isolated SQLite', 'retrieval_plus_suggestions_queries' => $count, 'elapsed_ms' => round($elapsed, 2), 'peak_memory_bytes' => memory_get_peak_usage(true), 'results' => $a['results']->total(), 'availability_calls' => 0], JSON_PRETTY_PRINT));
});

it('retains retryable projection work after failure and repairs it without enabling indexing', function () {
    $f = marketplaceBookablePath();
    config(['marketplace-growth.enabled' => true]);
    app(ProjectionWork::class)->mark($f['business']->id);
    $failure = Mockery::mock(PublicSupplyProjection::class);
    $failure->shouldReceive('rebuild')->once()->andThrow(new RuntimeException('synthetic projection failure'));
    (new RefreshPublicSupply($f['business']->id))->handle($failure);
    $row = DB::table('marketplace_projection_work')->where('business_id', $f['business']->id)->first();
    expect($row->attempts)->toBe(1)->and($row->failure_reference)->toStartWith('MPG-PROJECTION-');
    expect(app(PublicOfferQuery::class)->query()->count())->toBe(0);
    app(PublicSupplyProjection::class)->rebuild($f['business']->id);
    expect(app(PublicOfferQuery::class)->query()->count())->toBe(1);
    expect(DB::table('marketplace_seo_pages')->where('reviewer', 'automatic-profile-policy-v1')->count())->toBe(1)->and(config('marketplace-growth.indexation_enabled'))->toBeFalse();
});

it('keeps unreviewed non-English service names exact-searchable without assigning English aliases', function () {
    $taxonomy = app(DiscoveryTaxonomy::class);
    expect($taxonomy->concepts('Dog grooming', 'other', [], 'fr-CA'))->toBe([]);
    expect($taxonomy->concepts('Toilettage personnalisé', 'other', ['dog-grooming'], 'fr-CA'))->toBe(['dog-grooming']);
    $f = marketplaceBookablePath();
    $f['business']->update(['locale' => 'fr-CA']);
    $f['service']->update(['name' => 'Dog grooming']);
    app(PublicSupplyProjection::class)->rebuild($f['business']->id);
    expect(DB::table('marketplace_offer_terms')->count())->toBe(0);
    expect(app(SupplyDiscovery::class)->search(['q' => 'Dog grooming'])['results']->total())->toBe(1);
});
