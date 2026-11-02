<?php

use App\Models\User;
use App\Support\Seo\PublicAnalytics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->previousEnvironment = app()->environment();
    app()->instance('env', 'production');
    config(['brand.website_url' => 'https://clipperdesk.com', 'analytics.measurement_id' => 'G-TEST123456']);
});

afterEach(function () {
    app()->instance('env', $this->previousEnvironment);
});

function publicAnalyticsRequest(string $url, string $name = 'marketing.home'): Request
{
    $request = Request::create($url);
    $route = (new Route('GET', '/', fn () => null))->name($name);
    $request->setRouteResolver(fn () => $route);

    return $request;
}

it('shares only a public stream and clean canonical for an anonymous production marketing request', function () {
    $request = publicAnalyticsRequest('https://clipperdesk.com/pricing?email=private@example.test&utm_source=private', 'marketing.pricing');
    $props = app(PublicAnalytics::class)->for($request);
    expect($props['measurement_id'])->toBe('G-TEST123456')
        ->and($props['page'])->toBe(['location' => 'https://clipperdesk.com/pricing', 'path' => '/pricing', 'title' => 'ClipperDesk'])
        ->and(json_encode($props))->not->toContain('private')
        ->and($props['paths'])->toContain('/solutions/salons')->toContain('/features/online-booking');
});

it('never exposes analytics configuration on nonproduction environments or untrusted origins', function (string $environment, string $url) {
    app()->instance('env', $environment);
    expect(app(PublicAnalytics::class)->for(publicAnalyticsRequest($url)))->toBeNull();
})->with([
    ['local', 'https://clipperdesk.com/'], ['testing', 'https://clipperdesk.com/'], ['staging', 'https://clipperdesk.com/'],
    ['production', 'https://www.clipperdesk.com/'], ['production', 'https://book.clipperdesk.com/'],
    ['production', 'https://other.example/'], ['production', 'http://clipperdesk.com/'],
]);

it('fails closed for missing and malformed stream identifiers', function (mixed $id) {
    config(['analytics.measurement_id' => $id]);
    expect(app(PublicAnalytics::class)->for(publicAnalyticsRequest('https://clipperdesk.com/')))->toBeNull();
})->with([null, '', 'UA-12345-1', 'G-<script>', "G-123456\n", 'G-abc123', 'array' => [['G-TEST123456']]]);

it('excludes auth, help, booking, secure links, app pages and arbitrary content slugs', function (string $path, string $name) {
    expect(app(PublicAnalytics::class)->for(publicAnalyticsRequest('https://clipperdesk.com'.$path, $name)))->toBeNull();
})->with([
    ['/login', 'login'], ['/register', 'register'], ['/help/search?q=private', 'help.search'],
    ['/book/salon', 'booking.business.fallback'], ['/appointments/secure/token', 'public.appointment.view'],
    ['/businesses/tenant/app/clients', 'business.clients.index'], ['/solutions/private-name', 'marketing.solutions.show'],
    ['/blog/customer-defined-slug', 'blog.article'],
]);

it('keeps signed-in visits and forced error responses out of measurement', function () {
    $request = publicAnalyticsRequest('https://clipperdesk.com/');
    $request->setUserResolver(fn () => User::factory()->make());
    expect(app(PublicAnalytics::class)->for($request))->toBeNull();
    $request = publicAnalyticsRequest('https://clipperdesk.com/');
    $request->attributes->set('force_noindex', true);
    expect(app(PublicAnalytics::class)->for($request))->toBeNull();
});

it('lets legal-page visitors change consent without reporting legal page views', function () {
    $props = app(PublicAnalytics::class)->for(publicAnalyticsRequest('https://clipperdesk.com/privacy-policy', 'policy.show'));
    expect($props['measurement_id'])->toBe('G-TEST123456')->and($props['page'])->toBeNull();
});

it('integrates the fail-closed shared props into actual marketing responses without inline Google tags', function () {
    $this->get('https://clipperdesk.com/')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('publicAnalytics.measurement_id', 'G-TEST123456')
            ->where('publicAnalytics.page.location', 'https://clipperdesk.com/'))
        ->assertDontSee('googletagmanager.com/gtag', false);
    app()->instance('env', 'local');
    $this->get('https://clipperdesk.com/')->assertOk()->assertInertia(fn (Assert $page) => $page->where('publicAnalytics', null));
});
