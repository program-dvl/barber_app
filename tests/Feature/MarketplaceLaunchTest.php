<?php

use App\Domain\Marketplace\Launch\LaunchArea;
use App\Domain\Marketplace\LocalGrowth\Services\PublicSupplyProjection;
use App\Mail\Marketplace\LaunchUpdate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

require_once __DIR__.'/../Support/MarketplaceFixture.php';
uses(RefreshDatabase::class);
beforeEach(function () {
    $this->withoutVite();
    config(['marketplace-growth.enabled' => true, 'inertia.ssr.enabled' => false]);
    Mail::fake();
    Queue::fake();
});
function launchSignup($test, string $area = 'Bristol, England, UK'): ?LaunchUpdate
{
    $test->from('/marketplace')->post('/marketplace/launch-updates', ['area' => $area, 'email' => 'visitor@example.test', 'consent' => true])->assertRedirect('/marketplace')->assertSessionHasNoErrors()->assertSessionHas('launchUpdate.state', 'subscribed');

    return Mail::sent(LaunchUpdate::class)->last();
}
function launchProvider(): array
{
    config(['marketplace-growth.enabled' => false]);
    $f = marketplaceBookablePath();
    app(PublicSupplyProjection::class)->rebuild($f['business']->id);
    config(['marketplace-growth.enabled' => true]);

    return $f;
}
function legacyLaunchRequest(): array
{
    $confirm = str_repeat('a', 64);
    $unsubscribe = str_repeat('b', 64);
    $id = (string) Str::ulid();
    DB::table('marketplace_launch_subscriptions')->insert(['id' => $id, 'email_hash' => hash_hmac('sha256', 'legacy@example.test', config('app.key')), 'email' => Crypt::encryptString('legacy@example.test'), 'area' => 'Bristol, England, UK', 'area_key' => LaunchArea::key('Bristol, England, UK'), 'confirm_hash' => hash('sha256', $confirm), 'confirm_expires_at' => now()->addDay(), 'unsubscribe_hash' => hash('sha256', $unsubscribe), 'consent_version' => 'area-launch-v1', 'consented_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

    return compact('id', 'confirm', 'unsubscribe');
}

it('uses launch mode only for globally empty eligible supply, preserving real filtered searches', function () {
    $this->get('/marketplace')->assertOk()->assertSee('Your next good day starts here.')->assertInertia(fn (Assert $p) => $p->where('launchMode', true)->has('editorial.categories', 6)->has('editorial.guides', 3)->has('audiences', 13)->has('results.data', 0));
    $f = launchProvider();
    $this->get('/marketplace')->assertInertia(fn (Assert $p) => $p->where('launchMode', false)->has('results.data', 1));
    $this->get('/marketplace?location=NoMatchingCity')->assertInertia(fn (Assert $p) => $p->where('launchMode', false)->has('results.data', 0));
    $f['business']->update(['online_booking_enabled' => false]);
    $this->get('/marketplace')->assertInertia(fn (Assert $p) => $p->where('launchMode', true));
});
it('covers all thirteen audiences with useful category and guide destinations', function () {
    expect(collect(config('marketplace-editorial.categories'))->pluck('audiences')->flatten()->sort()->values()->all())->toBe(collect(array_keys(config('frontsite.solutions')))->sort()->values()->all());
    foreach (config('marketplace-editorial.categories') as $category) {
        $this->get('/marketplace/explore/'.$category['slug'])->assertOk()->assertInertia(fn (Assert $p) => $p->component('Marketplace/Editorial')->has('category.tips', 3));
    }
    foreach (config('marketplace-editorial.guides') as $guide) {
        $this->get('/marketplace/guides/'.$guide['slug'])->assertOk()->assertInertia(fn (Assert $p) => $p->has('guide.sections', 3));
    }
    $this->get('/marketplace/guides')->assertOk();
    $this->get('/marketplace/guides/not-a-guide')->assertNotFound();
});
it('requires explicit consent, a valid email and an area', function () {
    $this->post('/marketplace/launch-updates', ['area' => '', 'email' => 'invalid', 'consent' => false])->assertSessionHasErrors(['area', 'email', 'consent']);
    expect(DB::table('marketplace_launch_subscriptions')->count())->toBe(0);
    Mail::assertNothingSent();
});
it('subscribes immediately, encrypts the address and does not mark an unverified email verified', function () {
    $mail = launchSignup($this);
    $row = DB::table('marketplace_launch_subscriptions')->first();
    expect($row->subscribed_at)->not->toBeNull()->and($row->confirmed_at)->toBeNull()->and($row->confirm_hash)->toBeNull()->and($row->confirmation_token)->toBeNull()->and($row->subscription_method)->toBe('direct-opt-in')->and($row->consent_version)->toBe('area-launch-v2-direct');
    expect(Crypt::decryptString($row->email))->toBe('visitor@example.test')->and($row->email)->not->toContain('visitor@example.test');
    expect($mail->welcome)->toBeTrue()->and($mail->actionUrl)->not->toContain('/confirm/');
    $this->get('/marketplace')->assertInertia(fn (Assert $p) => $p->where('flash.launchUpdate.state', 'subscribed')->where('flash.launchUpdate.retryAt', 0));
    expect(DB::table('marketplace_customers')->count())->toBe(0);
});
it('deduplicates repeated subscriptions without warning or duplicate welcome mail', function () {
    launchSignup($this);
    $this->post('/marketplace/launch-updates', ['area' => ' bristol,  england, uk ', 'email' => 'VISITOR@example.test', 'consent' => true])->assertSessionHasNoErrors()->assertSessionHas('launchUpdate.state', 'subscribed');
    expect(DB::table('marketplace_launch_subscriptions')->count())->toBe(1);
    Mail::assertSentCount(1);
    $this->travel(61)->seconds();
    launchSignup($this);
    Mail::assertSentCount(1);
});
it('allows another area without repeating welcome emails to the same recipient', function () {
    launchSignup($this);
    launchSignup($this, 'Bath, England, UK');
    expect(DB::table('marketplace_launch_subscriptions')->whereNotNull('subscribed_at')->count())->toBe(2);
    Mail::assertSentCount(1);
});
it('keeps a saved subscription active when informational welcome mail fails', function () {
    Mail::shouldReceive('to')->once()->andReturnSelf();
    Mail::shouldReceive('send')->once()->andThrow(new RuntimeException('private transport failure'));
    $this->from('/marketplace')->post('/marketplace/launch-updates', ['area' => 'Bristol, England, UK', 'email' => 'visitor@example.test', 'consent' => true])->assertSessionHasNoErrors()->assertSessionHas('launchUpdate.state', 'subscribed');
    $row = DB::table('marketplace_launch_subscriptions')->first();
    expect($row->subscribed_at)->not->toBeNull()->and($row->welcome_sent_at)->toBeNull()->and($row->welcome_failure_reference)->toHaveLength(26);
});
it('owns storage failures with safe recovery and never claims a subscription was saved', function () {
    Schema::drop('marketplace_launch_subscriptions');
    $this->from('/marketplace')->post('/marketplace/launch-updates', ['area' => 'Bristol, England, UK', 'email' => 'visitor@example.test', 'consent' => true])->assertRedirect('/marketplace')->assertSessionHasNoErrors()->assertSessionHas('launchUpdate.state', 'unavailable');
    expect(session('launchUpdate.reference'))->toHaveLength(26);
    Mail::assertNothingSent();
    $this->post('/marketplace/launch-updates', ['area' => 'Bristol, England, UK', 'email' => 'visitor@example.test', 'consent' => true])->assertSessionHas('launchUpdate.state', 'unavailable');
});
it('unsubscribes only on POST, idempotently, and permits a new explicit signup', function () {
    $mail = launchSignup($this);
    $path = parse_url($mail->unsubscribeUrl, PHP_URL_PATH);
    $this->get($path)->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')->assertHeader('Referrer-Policy', 'no-referrer');
    expect(DB::table('marketplace_launch_subscriptions')->first()->unsubscribed_at)->toBeNull();
    $this->post($path)->assertOk()->assertInertia(fn (Assert $p) => $p->where('done', true));
    $this->post($path)->assertOk()->assertInertia(fn (Assert $p) => $p->where('done', true));
    expect(DB::table('marketplace_launch_subscriptions')->first()->unsubscribed_at)->not->toBeNull();
    launchSignup($this);
    expect(DB::table('marketplace_launch_subscriptions')->first()->unsubscribed_at)->toBeNull();
    Mail::assertSentCount(1);
});
it('preserves old emailed confirmation links without requiring them for new subscriptions', function () {
    $legacy = legacyLaunchRequest();
    $path = '/marketplace/launch-updates/confirm/'.$legacy['confirm'];
    $this->get($path)->assertOk();
    expect(DB::table('marketplace_launch_subscriptions')->first()->subscribed_at)->toBeNull();
    $this->post($path)->assertInertia(fn (Assert $p) => $p->where('done', true));
    $this->post($path)->assertInertia(fn (Assert $p) => $p->where('done', true));
    $row = DB::table('marketplace_launch_subscriptions')->first();
    expect($row->subscribed_at)->not->toBeNull()->and($row->confirmed_at)->not->toBeNull()->and($row->subscription_method)->toBe('email-confirmation');
});
it('expires legacy links and never activates a cancelled request', function () {
    $legacy = legacyLaunchRequest();
    $this->travel(25)->hours();
    $this->post('/marketplace/launch-updates/confirm/'.$legacy['confirm'])->assertInertia(fn (Assert $p) => $p->where('valid', false));
    $this->post('/marketplace/launch-updates/unsubscribe/'.$legacy['unsubscribe'])->assertOk();
    expect(DB::table('marketplace_launch_subscriptions')->first()->subscribed_at)->toBeNull();
});
it('previews one-time area emails, requires reviewed live supply and sends once to subscribers', function () {
    $f = launchProvider();
    launchSignup($this);
    $args = ['area' => 'Bristol, England, UK', 'slug' => $f['business']->booking_slug];
    $this->artisan('marketplace:launch-update', $args)->assertSuccessful();
    Mail::assertSentCount(1);
    $this->artisan('marketplace:launch-update', [...$args, '--send' => true])->assertFailed();
    Mail::assertSentCount(1);
    $this->artisan('marketplace:launch-update', [...$args, '--send' => true, '--location-reviewed' => true])->assertSuccessful();
    Mail::assertSentCount(2);
    $this->artisan('marketplace:launch-update', [...$args, '--send' => true, '--location-reviewed' => true])->assertSuccessful();
    Mail::assertSentCount(2);
    $f['business']->update(['online_booking_enabled' => false]);
    $this->artisan('marketplace:launch-update', [...$args, '--send' => true, '--location-reviewed' => true])->assertFailed();
});
it('excludes pending legacy and cancelled subscribers from area mail', function () {
    $f = launchProvider();
    legacyLaunchRequest();
    $mail = launchSignup($this);
    $this->post(parse_url($mail->unsubscribeUrl, PHP_URL_PATH));
    $this->artisan('marketplace:launch-update', ['area' => 'Bristol, England, UK', 'slug' => $f['business']->booking_slug, '--send' => true, '--location-reviewed' => true])->assertSuccessful();
    Mail::assertSentCount(1);
});
it('does not retry uncertain area email acceptance automatically', function () {
    $f = launchProvider();
    launchSignup($this);
    Mail::shouldReceive('to')->once()->andReturnSelf();
    Mail::shouldReceive('send')->once()->andThrow(new RuntimeException('uncertain acceptance'));
    $args = ['area' => 'Bristol, England, UK', 'slug' => $f['business']->booking_slug, '--send' => true, '--location-reviewed' => true];
    $this->artisan('marketplace:launch-update', $args)->assertFailed();
    expect(DB::table('marketplace_launch_subscriptions')->first()->attempted_at)->not->toBeNull();
    $this->artisan('marketplace:launch-update', $args)->assertSuccessful();
});
it('retains active subscriptions past seven days and prunes stale pending or year-old requests', function () {
    launchSignup($this);
    legacyLaunchRequest();
    $this->travel(8)->days();
    $this->artisan('marketplace:prune-launch-updates')->assertSuccessful();
    expect(DB::table('marketplace_launch_subscriptions')->count())->toBe(1);
    $this->travel(366)->days();
    $this->artisan('marketplace:prune-launch-updates')->assertSuccessful();
    expect(DB::table('marketplace_launch_subscriptions')->count())->toBe(0);
});
it('renders an informational welcome in standard Marketplace branding without sign-in or confirmation instructions', function () {
    $html = (new LaunchUpdate('Boston, MA, USA', 'https://example.test/marketplace', 'https://example.test/cancel', welcome: true))->render();
    expect($html)->toContain('You’re on the list', 'Boston, MA, USA', 'https://example.test/cancel', 'data:image/png;base64,', 'Explore Marketplace')->not->toContain('Keep your sign-in link private', 'Confirm my area updates', 'Your confirmation link');
});

it('keeps ordinary repeat clicks idempotent without a page-wide throttle warning', function () {
    launchSignup($this);
    for ($i = 0; $i < 6; $i++) {
        $this->from('/marketplace')->post('/marketplace/launch-updates', ['area' => 'Bristol, England, UK', 'email' => 'visitor@example.test', 'consent' => true])->assertRedirect('/marketplace')->assertSessionHasNoErrors()->assertSessionHas('launchUpdate.state', 'subscribed');
    }
    Mail::assertSentCount(1);
    expect(DB::table('marketplace_launch_subscriptions')->count())->toBe(1);
});
