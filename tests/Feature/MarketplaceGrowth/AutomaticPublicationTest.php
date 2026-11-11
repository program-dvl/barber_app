<?php

use App\Domain\BusinessConfiguration\Services\OnboardingManager;
use App\Domain\Marketplace\LocalGrowth\Services\PublicSupplyProjection;
use App\Exceptions\Handler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Inertia\Inertia;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

require_once __DIR__.'/../../Support/MarketplaceFixture.php';
uses(RefreshDatabase::class);

beforeEach(function () {
    config(['marketplace-growth.enabled' => false, 'marketplace-growth.indexation_enabled' => false, 'inertia.ssr.enabled' => (bool) getenv('MPG_TEST_LIVE_SSR'), 'inertia.ssr.url' => 'http://127.0.0.1:13714']);
    Queue::fake();
    Notification::fake();
    $this->withoutVite();
});

function automaticallyPublishedStore(): array
{
    $fixture = marketplaceBookablePath();
    $fixture['business']->update(['configuration_published_at' => null, 'online_booking_enabled' => false,
        'business_type' => 'multi_service', 'description' => 'Book our published appointment services at our customer-facing studio. Review prices and choose a convenient time.',
        'country_code' => 'IN', 'appointment_interval_minutes' => 15, 'week_starts_on' => 1, 'tax_posture' => 'exclusive', 'phone' => '+919999999999', 'email' => 'studio@example.test', 'address' => '18 Studio Road', 'locale' => 'en-IN']);
    $fixture['location']->update(['address' => '18 Studio Road']);
    app(OnboardingManager::class)->resume($fixture['business']);
    app(OnboardingManager::class)->markPreviewed($fixture['business']);
    config(['marketplace-growth.enabled' => true]);

    return $fixture;
}

it('enables discovery without legacy environment configuration or per-store pilot fields', function () {
    $settings = require config_path('marketplace-growth.php');
    expect($settings['enabled'])->toBeTrue()->and($settings)->not->toHaveKeys(['approved_origin', 'pilot_business_ids']);
    expect(file_get_contents(base_path('.env.example')))->not->toContain('MARKETPLACE_GROWTH_ENABLED=', 'MARKETPLACE_GROWTH_ORIGIN=', 'MARKETPLACE_GROWTH_PILOT_IDS=', 'MARKETPLACE_LISTED_SLUGS=');
});

it('makes a completed store searchable through normal publication before a background worker runs', function () {
    $f = automaticallyPublishedStore();
    $this->get('/marketplace?q=Signature')->assertInertia(fn (Assert $p) => $p->has('results.data', 0));
    $this->actingAs($f['user'])->post(route('business.configuration.publish', $f['business']))->assertSessionHasNoErrors();
    $this->get('/marketplace?q=Signature')->assertOk()->assertInertia(fn (Assert $p) => $p->has('results.data', 1)->where('results.data.0.name', $f['business']->name));
    $this->get('/marketplace/services')->assertJsonFragment(['name' => 'Signature Cut']);
    $settings = $this->get(route('business.marketplace.settings', $f['business']))->assertOk();
    if (getenv('MPG_TEST_LIVE_SSR')) {
        $settings->assertSee('Publishing your booking page makes your business searchable')->assertDontSee('Request publication review');
    }
    expect(DB::table('marketplace_location_geographies')->count())->toBe(0);
    $page = DB::table('marketplace_seo_pages')->where('business_id', $f['business']->id)->first();
    expect($page->status)->toBe('approved')->and($page->reviewer)->toBe('automatic-profile-policy-v1');
    $this->get($page->path)->assertOk()->assertSee('18 Studio Road')->assertSee('Signature Cut')
        ->assertHeader('X-Robots-Tag', 'noindex, follow, noarchive');
});

it('does not expose rolled-back publication unfinished stores or unqualified services', function () {
    $f = automaticallyPublishedStore();
    expect(fn () => DB::transaction(function () use ($f) {
        app(OnboardingManager::class)->publish($f['business']);
        throw new RuntimeException('Synthetic rollback');
    }))->toThrow(RuntimeException::class, 'Synthetic rollback');
    expect($f['business']->fresh()->configuration_published_at)->toBeNull();
    expect(DB::table('marketplace_public_offers')->count())->toBe(0);
    $this->get('/marketplace?q=Signature')->assertInertia(fn (Assert $p) => $p->has('results.data', 0));
    $f['business']->update(['configuration_published_at' => now(), 'online_booking_enabled' => true]);
    $f['service']->update(['online_visible' => false]);
    $this->get('/marketplace?q=Signature')->assertInertia(fn (Assert $p) => $p->has('results.data', 0));
});

it('automatically updates profile readiness and respects an owner pause and resume', function () {
    $f = automaticallyPublishedStore();
    app(OnboardingManager::class)->publish($f['business']);
    $before = DB::table('marketplace_seo_pages')->where('business_id', $f['business']->id)->first();
    $f['service']->update(['name' => 'New exact appointment name']);
    app(PublicSupplyProjection::class)->rebuild($f['business']->id);
    $after = DB::table('marketplace_seo_pages')->where('business_id', $f['business']->id)->first();
    expect($after->status)->toBe('approved')->and($after->approved_hash)->not->toBe($before->approved_hash);
    $this->actingAs($f['user'])->patch(route('business.marketplace.listing', $f['business']), ['listed' => false, 'version' => 1])->assertSessionHasNoErrors();
    $this->get('/marketplace?q=New%20exact')->assertInertia(fn (Assert $p) => $p->has('results.data', 0));
    $this->patch(route('business.marketplace.listing', $f['business']), ['listed' => true, 'version' => 2, 'consent' => true])->assertSessionHasNoErrors();
    $this->get('/marketplace?q=New%20exact')->assertInertia(fn (Assert $p) => $p->has('results.data', 1));
    DB::table('marketplace_seo_pages')->where('id', $after->id)->update(['status' => 'withdrawn']);
    app(PublicSupplyProjection::class)->rebuild($f['business']->id);
    expect(DB::table('marketplace_seo_pages')->where('id', $after->id)->value('status'))->toBe('withdrawn');
    $this->get($after->path)->assertNotFound();
});

it('uses one global indexing choice and the existing canonical origin without a store allowlist', function () {
    $f = automaticallyPublishedStore();
    app(OnboardingManager::class)->publish($f['business']);
    $path = '/marketplace/salons/'.$f['business']->booking_slug;
    config(['brand.website_url' => 'https://example.test', 'marketplace-growth.indexation_enabled' => true]);
    $this->get('https://example.test'.$path)->assertOk()->assertHeader('X-Robots-Tag', 'index, follow, max-image-preview:large');
    $this->get('https://example.test/marketplace/sitemaps/profiles/0.xml')->assertSee($path, false);
    $this->get('https://wrong.example'.$path)->assertHeader('X-Robots-Tag', 'noindex, follow, noarchive');
    $this->get('https://example.test'.$path.'?date=2026-12-01')->assertHeader('X-Robots-Tag', 'noindex, follow, noarchive');
    $this->get('https://example.test/marketplace/sign-in')->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')->assertHeader('Cache-Control', 'no-store, private');
    config(['marketplace-growth.indexation_enabled' => false]);
    $this->get('https://example.test'.$path)->assertHeader('X-Robots-Tag', 'noindex, follow, noarchive');
    $this->get('https://example.test/marketplace/sitemaps/profiles/0.xml')->assertDontSee($path, false);
});

it('renders Marketplace recovery with route configuration when an exception bypasses shared middleware', function () {
    $request = Request::create('https://example.test/marketplace/unavailable-fixture');
    $this->app->instance('request', $request);
    Inertia::flushShared();
    $response = app(Handler::class)->render($request, new NotFoundHttpException);
    $result = TestResponse::fromBaseResponse($response)->assertNotFound();
    $result->assertInertia(fn (Assert $page) => $page->component('Marketplace/Unavailable')->where('ziggy.location', $request->url()));
    if (getenv('MPG_TEST_LIVE_SSR')) {
        $result->assertSee('Clipperdesk Marketplace home')->assertSee('A little help to continue');
    }
});

it('prepares a published store profile from real services and address when the optional description is empty', function () {
    $f = automaticallyPublishedStore();
    $f['business']->update(['description' => null, 'name' => str_repeat('Studio ', 35)]);
    app(OnboardingManager::class)->publish($f['business']);
    $page = DB::table('marketplace_seo_pages')->where('business_id', $f['business']->id)->first();
    expect($page->status)->toBe('approved')->and(mb_strlen($page->title))->toBeLessThanOrEqual(255);
    config(['brand.website_url' => 'https://example.test', 'marketplace-growth.indexation_enabled' => true]);
    $this->get('https://example.test'.$page->path)->assertOk()->assertSee('Signature Cut')->assertSee('18 Studio Road')->assertHeader('X-Robots-Tag', 'index, follow, max-image-preview:large');
});
