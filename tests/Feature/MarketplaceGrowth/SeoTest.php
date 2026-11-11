<?php

use App\Domain\BusinessConfiguration\Services\BookingSlugManager;
use App\Domain\Marketplace\LocalGrowth\Services\PublicSupplyProjection;
use App\Domain\Marketplace\LocalGrowth\Services\SeoPageRegistry;
use App\Domain\Marketplace\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Inertia\Ssr\Gateway;
use Inertia\Testing\AssertableInertia as Assert;

require_once __DIR__.'/../../Support/MarketplaceGrowthFixture.php';
uses(RefreshDatabase::class);
beforeEach(function () {
    Queue::fake();
    config(['inertia.ssr.enabled' => false]);
});

it('renders substantive initial HTML for an individual profile before a city qualifies and keeps indexing default off', function () {
    $f = growthSupply();
    growthApproveProfile($f);
    $url = '/marketplace/salons/'.$f['business']->booking_slug;
    $r = $this->get($url)->assertOk()->assertHeader('X-Robots-Tag', 'noindex, follow, noarchive')->assertHeader('Cache-Control', 'no-store, private');
    expect(growthMainHtml($r))->toContain('Dog grooming', 'CAD 35.00', 'Test City', 'Services & starting prices', 'America/Toronto');
    $r->assertSee('data-marketplace-fallback', false)->assertSee('application/ld+json', false);
    expect(growthMainHtml($r))->not->toContain('private-staff@example.test', 'MPG-', 'unknown');
    $this->get('/marketplace/sitemap.xml')->assertOk()->assertDontSee($f['business']->booking_slug);
    expect(config('marketplace-growth.indexation_enabled'))->toBeFalse();
});

it('aligns exact-origin robots meta canonical schema and sitemap while keeping query and private routes noindex', function () {
    $f = growthSupply();
    growthApproveProfile($f);
    $origin = 'https://clipperdesk.example';
    config(['brand.website_url' => $origin, 'marketplace-growth.indexation_enabled' => true]);
    $path = '/marketplace/salons/'.$f['business']->booking_slug;
    $r = $this->get($origin.$path)->assertOk()->assertHeader('X-Robots-Tag', 'index, follow, max-image-preview:large');
    $r->assertSee('content="index, follow, max-image-preview:large"', false)->assertSee('href="'.$origin.$path.'"', false);
    $r->assertSee('LocalBusiness')->assertSee('Dog grooming')->assertDontSee('aggregateRating')->assertDontSee('MedicalClinic');
    $this->get($origin.$path.'?date=2026-11-09')->assertHeader('X-Robots-Tag', 'noindex, follow, noarchive');
    $this->get('https://wrong.example'.$path)->assertHeader('X-Robots-Tag', 'noindex, follow, noarchive');
    $this->get($origin.'/marketplace/sitemap.xml')->assertSee('/marketplace/sitemaps/profiles/0.xml', false);
    $this->get($origin.'/marketplace/sitemaps/profiles/0.xml')->assertSee($origin.$path, false)->assertSee('<lastmod>', false);
    $this->get($origin.'/marketplace/sign-in')->assertHeader('Cache-Control', 'no-store, private')->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
    $this->get($origin.'/marketplace/sign-in/link/'.str_repeat('a', 64))->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
    $this->get($origin.'/appointments/secure/not-a-real-token')->assertNotFound();
    config(['marketplace-growth.indexation_enabled' => false]);
    $this->get($origin.'/marketplace/sign-in')->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
    $this->get($origin.'/marketplace/sitemaps/profiles/0.xml')->assertDontSee($path);
});

it('serves only reviewed inventory-backed hubs and truthful noindex empty supply', function () {
    $f = growthSupply();
    $hub = growthApproveHub($f);
    $this->get($hub->path)->assertOk()->assertHeader('X-Robots-Tag', 'noindex, follow, noarchive');
    $this->get('/marketplace/ca/on/imaginary/services/dog-grooming')->assertNotFound();
    $this->get('/marketplace/ca/on/test-city/services/physiotherapy')->assertNotFound();
    $a = growthSupply();
    $b = growthSupply();
    expect(app(SeoPageRegistry::class)->readiness($hub)['businesses'])->toBe(3);
    expect(app(SeoPageRegistry::class)->approved($hub))->toBeTrue();
    $r = $this->get($hub->path)->assertOk();
    expect(growthMainHtml($r))->toContain('Dog grooming', 'CAD 35.00', 'Explore & book');
    DB::table('businesses')->update(['online_booking_enabled' => false]);
    $r = $this->get($hub->path)->assertOk()->assertHeader('X-Robots-Tag', 'noindex, follow, noarchive');
    expect(growthMainHtml($r))->toContain('No appointments to show');
    expect(growthMainHtml($r))->not->toContain('Pine & Palm Studio');
});

it('removes paused or withdrawn delivery and handles only real slug equivalents with a 301', function () {
    $f = growthSupply();
    $page = growthApproveProfile($f);
    $old = $f['business']->booking_slug;
    app(BookingSlugManager::class)->change($f['business'], 'new-reviewed-slug');
    app(PublicSupplyProjection::class)->rebuild($f['business']->id);
    $this->get('/marketplace/salons/'.$old)->assertStatus(301)->assertRedirect('/marketplace/salons/new-reviewed-slug');
    $this->get('/marketplace/salons/not-real')->assertNotFound();
    DB::table('marketplace_seo_pages')->where('id', $page->id)->update(['status' => 'withdrawn']);
    $this->get('/marketplace/salons/new-reviewed-slug')->assertNotFound();
    $this->get('/marketplace')->assertInertia(fn (Assert $p) => $p->has('results.data', 0));
    // Owner direct booking still works; withdrawing Marketplace is not cancellation.
    $this->get('/book/new-reviewed-slug')->assertOk();
    config(['marketplace-growth.enabled' => false]);
    $this->get('/marketplace/salons/new-reviewed-slug')->assertNotFound();
    $this->get('/marketplace')->assertInertia(fn (Assert $p) => $p->has('results.data', 0));
    $this->get('/book/new-reviewed-slug')->assertOk();
});

it('gives an approved nonempty hub a bounded editorial review window after supply drops', function () {
    $a = growthSupply();
    $b = growthSupply();
    $c = growthSupply();
    $hub = growthApproveHub($a);
    $registry = app(SeoPageRegistry::class);
    expect($registry->approved($hub))->toBeTrue();
    $c['business']->update(['online_booking_enabled' => false]);
    app(PublicSupplyProjection::class)->rebuild($c['business']->id);
    $hub = DB::table('marketplace_seo_pages')->find($hub->id);
    expect($registry->readiness($hub)['businesses'])->toBe(2);
    expect($hub->low_supply_since)->not->toBeNull();
    expect($registry->approved($hub))->toBeTrue();
    $this->travel(8)->days();
    // Fresh offers ensure expiry of the review window, rather than projection TTL,
    // is the reason the still-useful page requires review.
    foreach ([$a, $b] as $f) {
        app(PublicSupplyProjection::class)->rebuild($f['business']->id);
    }
    $hub = DB::table('marketplace_seo_pages')->find($hub->id);
    expect($registry->approved($hub))->toBeFalse();
    $this->get($hub->path)->assertOk()->assertHeader('X-Robots-Tag', 'noindex, follow, noarchive');
    foreach ([$a, $b] as $f) {
        $f['business']->update(['online_booking_enabled' => false]);
        app(PublicSupplyProjection::class)->rebuild($f['business']->id);
    }
    expect($registry->readiness($hub)['businesses'])->toBe(0);
    expect($registry->approved($hub))->toBeFalse();
});

it('does not mix cookies identities secure links or precise search centres into shared public facts', function () {
    $f = growthSupply();
    growthApproveProfile($f);
    $path = '/marketplace/salons/'.$f['business']->booking_slug;
    $guest = $this->get($path)->assertOk();
    $customer = Customer::query()->create(['name' => 'Private Customer', 'email' => 'secret-customer@example.test', 'email_verified_at' => now()]);
    $auth = $this->actingAs($f['user'], 'web')->actingAs($customer, 'marketplace')->withSession(['marketplace_nearby' => [43.1234567, -79.7654321], 'marketplace_nearby_expires_at' => now()->addMinutes(30)->timestamp])->get($path)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    expect(growthMainHtml($auth))->toBe(growthMainHtml($guest));
    $facts = DB::table('marketplace_public_offers')->value('facts');
    expect($facts)->not->toContain('secret-customer', 'Private Customer', '43.1234567', 'secret_hash', 'view_url');
    expect($auth->headers->get('Cache-Control'))->not->toContain('public');
});

it('self canonicalizes distinct clean pagination and excludes filters from indexation', function () {
    $fixtures = [];
    for ($i = 0; $i < 13; $i++) {
        $fixtures[] = growthSupply();
    }
    $f = $fixtures[0];
    $hub = growthApproveHub($f);
    $origin = 'https://clipperdesk.example';
    config(['brand.website_url' => $origin, 'marketplace-growth.indexation_enabled' => true]);
    $r = $this->get($origin.$hub->path.'?page=2')->assertOk()->assertHeader('X-Robots-Tag', 'index, follow, max-image-preview:large');
    $r->assertSee('href="'.$origin.$hub->path.'?page=2"', false);
    expect(growthMainHtml($r))->toContain('Page 2', 'Previous');
    $this->get($origin.$hub->path.'?page=3')->assertNotFound();
    $this->get($origin.$hub->path.'?page=1&date=2026-11-08')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, follow, noarchive');
    $this->get($origin.$hub->path.'?page[]=2')->assertRedirect();
});

it('falls back to useful public HTML when the SSR gateway is unavailable and keeps material lastmod stable', function () {
    $f = growthSupply();
    $page = growthApproveProfile($f);
    $this->instance(Gateway::class, Mockery::mock(Gateway::class, function ($mock) {
        $mock->shouldReceive('dispatch')->andReturn(null);
    }));
    config(['inertia.ssr.enabled' => true]);
    $r = $this->get($page->path)->assertOk()->assertSee('data-marketplace-fallback', false);
    expect(growthMainHtml($r))->toContain('Dog grooming', '10 Example Street');
    app(PublicSupplyProjection::class)->rebuild($f['business']->id);
    expect(DB::table('marketplace_seo_pages')->where('id', $page->id)->value('materially_changed_at'))->toBe($page->materially_changed_at);
    $this->travel(2)->seconds();
    $f['service']->update(['price_minor' => 4200]);
    app(PublicSupplyProjection::class)->rebuild($f['business']->id);
    $updated = DB::table('marketplace_seo_pages')->where('id', $page->id)->first();
    expect($updated->materially_changed_at)->not->toBe($page->materially_changed_at);
    expect(app(SeoPageRegistry::class)->approved($updated))->toBeTrue();
    $r = $this->get($page->path)->assertOk()->assertHeader('X-Robots-Tag', 'noindex, follow, noarchive');
    expect(growthMainHtml($r))->toContain('CAD 42.00')->not->toContain('CAD 35.00');
});

it('renders real built SSR directory and canonical booking HTML when the local renderer is running', function () {
    if (! getenv('MPG_TEST_LIVE_SSR')) {
        $this->markTestSkipped('Run with MPG_TEST_LIVE_SSR=1 and the built local SSR server.');
    }
    config(['inertia.ssr.enabled' => true, 'inertia.ssr.url' => 'http://127.0.0.1:13714']);
    $f = growthSupply();
    growthApproveProfile($f);
    $hub = growthApproveHub($f);
    $r = $this->get($hub->path)->assertOk()->assertDontSee('data-marketplace-fallback', false);
    expect(growthMainHtml($r))->toContain('Dog grooming', 'Explore & book', 'Test City');
    expect($r->getContent())->toMatch('/<footer[^>]*class="mp-footer"[^>]*>.*?class="mp-cookie-settings"[^>]*>Cookie settings<.*?<\/footer>/s');
    expect(substr_count($r->getContent(), '>Cookie settings</button>'))->toBe(1);
    $r->assertDontSee('acquisition-choice', false);
    $r = $this->get('/marketplace/salons/'.$f['business']->booking_slug)->assertOk()->assertDontSee('data-marketplace-fallback', false);
    $r->assertSee('Clipperdesk Marketplace home')->assertSee('mp-booking-flow', false)
        ->assertDontSee('ClipperDesk booking home');
    expect(growthMainHtml($r))->toContain('Dog grooming', 'Pine & Palm Studio');
    expect($r->getContent())->toMatch('/<footer[^>]*class="mp-footer"[^>]*>.*?Cookie settings.*?<\/footer>/s');
    expect(substr_count($r->getContent(), '>Cookie settings</button>'))->toBe(1);
    $r->assertDontSee('acquisition-choice', false);
    $r = $this->get('/appointments/secure/not-a-real-token')->assertNotFound()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')->assertHeader('Cache-Control', 'no-store, private');
    expect(growthMainHtml($r))->toContain('Check your appointment link', 'Open the latest link in your confirmation email');
    $r->assertSee('Clipperdesk Marketplace home');
});
