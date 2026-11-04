<?php

use App\Domain\PlatformAccess\Models\Business;
use App\Http\Middleware\RedirectPublicMarketingAliases;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['brand.website_url' => 'https://clipperdesk.com', 'booking.host' => 'book.clipperdesk.com']);
    $this->app->detectEnvironment(fn () => 'production');
});

it('permanently redirects successful public marketing aliases to the primary HTTPS origin', function (string $host, string $path) {
    $this->get('https://'.$host.$path)->assertStatus(301)->assertRedirect('https://clipperdesk.com'.$path);
})->with([
    ['book.clipperdesk.com', '/'],
    ['book.clipperdesk.com', '/features'],
    ['book.clipperdesk.com', '/features/online-booking'],
    ['book.clipperdesk.com', '/solutions'],
    ['book.clipperdesk.com', '/solutions/barbershops'],
    ['book.clipperdesk.com', '/use-cases'],
    ['book.clipperdesk.com', '/use-cases/reduce-scheduling-conflicts'],
    ['book.clipperdesk.com', '/pricing'],
    ['book.clipperdesk.com', '/company'],
    ['book.clipperdesk.com', '/security'],
    ['book.clipperdesk.com', '/resources'],
    ['book.clipperdesk.com', '/guides/booking-policy-basics'],
    ['book.clipperdesk.com', '/help/articles/account-profile'],
    ['book.clipperdesk.com', '/help/category/account'],
    ['book.clipperdesk.com', '/blog'],
    ['www.clipperdesk.com', '/'],
    ['www.clipperdesk.com', '/features'],
    ['www.clipperdesk.com', '/help'],
]);

it('keeps primary and unrecognized hosts unchanged', function (string $host) {
    $this->get('https://'.$host.'/features')->assertOk()->assertHeaderMissing('Location');
})->with(['clipperdesk.com', 'other.example', 'clipperdesk.com.evil.example', 'another.clipperdesk.com']);

it('preserves query bytes and redirects HEAD without a response body', function () {
    $path = '/features?utm_source=partner&campaign=a%2Bb&campaign=second';
    $this->get('https://book.clipperdesk.com'.$path)->assertStatus(301)
        ->assertRedirect('https://clipperdesk.com'.$path);
    $this->head('https://www.clipperdesk.com/features')->assertStatus(301)
        ->assertRedirect('https://clipperdesk.com/features')->assertContent('');
});

it('uses an external Inertia visit rather than an off-origin partial request', function () {
    $version = $this->get('https://clipperdesk.com/features')->viewData('page')['version'];
    $this->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => $version])
        ->get('https://book.clipperdesk.com/features?utm_source=partner')
        ->assertStatus(409)->assertHeader('X-Inertia-Location', 'https://clipperdesk.com/features?utm_source=partner');
});

it('redirects a published article but retains private and missing article failures', function () {
    $article = Article::create([
        'user_id' => User::factory()->create(['name' => 'Public author'])->id,
        'title' => 'A working day', 'content' => '<p>Prepare the appointment diary.</p>',
        'excerpt' => 'Practical preparation for the working day.', 'topic' => 'Operations',
        'thumbnail' => 'articles/working-day.png', 'seo_title' => 'A working day',
        'seo_description' => 'Practical preparation for an appointment-led working day.',
        'active' => true, 'status' => 'published', 'content_owner' => 'Editorial team',
        'published_at' => now()->subDay(), 'reviewed_at' => now()->subDay(),
    ]);
    $path = '/blog/'.$article->slug;
    $this->get('https://book.clipperdesk.com'.$path)->assertStatus(301)->assertRedirect('https://clipperdesk.com'.$path);
    $article->update(['status' => 'draft']);
    $this->get('https://book.clipperdesk.com'.$path)->assertNotFound()->assertHeaderMissing('Location');
    $this->get('https://book.clipperdesk.com/blog/no-such-article')->assertNotFound()->assertHeaderMissing('Location');
    $this->get('https://book.clipperdesk.com/features/no-such-feature')->assertNotFound()->assertHeaderMissing('Location');
});

it('preserves booking entry points tenant pages and booking writes on their current origin', function () {
    $business = Business::factory()->create([
        'name' => 'A real booking studio', 'booking_slug' => 'alias-test-studio', 'status' => 'active',
        'configuration_published_at' => now(), 'online_booking_enabled' => true, 'currency_code' => 'USD',
    ]);
    activateTestSubscription($business);
    foreach (['https://book.clipperdesk.com/'.$business->booking_slug,
        'https://book.clipperdesk.com/book/'.$business->booking_slug,
        'https://clipperdesk.com/book/'.$business->booking_slug] as $url) {
        $this->get($url)->assertOk()->assertHeaderMissing('Location')
            ->assertHeader('X-Robots-Tag', 'noindex, follow, noarchive')
            ->assertInertia(fn (Assert $page) => $page->component('Booking/Welcome')
                ->where('business.booking_url', 'https://book.clipperdesk.com/'.$business->booking_slug));
    }
    $this->get('https://book.clipperdesk.com/')->assertStatus(301)->assertRedirect('https://clipperdesk.com/');
    $this->get('https://book.clipperdesk.com/book')->assertOk()->assertHeaderMissing('Location');
    $this->withoutMiddleware(VerifyCsrfToken::class)
        ->postJson('https://book.clipperdesk.com/book/'.$business->booking_slug.'/flows')
        ->assertOk()->assertHeaderMissing('Location')->assertJsonStructure(['flow', 'secret', 'expires_at']);
});

it('does not redirect authentication assets secure links search endpoints or mutations', function () {
    foreach (['/login', '/book', '/manage-appointment', '/help/search?q=booking',
        '/robots.txt', '/sitemap.xml', '/terms-of-service', '/refund-policy'] as $path) {
        $this->get('https://book.clipperdesk.com'.$path)->assertOk()->assertHeaderMissing('Location');
    }
    foreach (['/appointments/secure/'.str_repeat('a', 64), '/client-files/'.str_repeat('b', 64),
        '/storage/not-an-image.png', '/api/no-such-endpoint', '/admin/not-a-page'] as $path) {
        $this->get('https://book.clipperdesk.com'.$path)->assertNotFound()->assertHeaderMissing('Location');
    }
    $this->post('https://book.clipperdesk.com/features')->assertStatus(405)->assertHeaderMissing('Location');
    $this->get('https://www.clipperdesk.com/help?q=appointments')->assertOk()->assertHeaderMissing('Location')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
    $this->get('https://book.clipperdesk.com/features?signature=protected&expires=123')->assertOk()->assertHeaderMissing('Location');
});

it('does not redirect local previews or incomplete primary-origin configuration', function (string $environment, mixed $origin) {
    config(['brand.website_url' => $origin]);
    $this->app->detectEnvironment(fn () => $environment);
    $request = Request::create('https://book.clipperdesk.com/features');
    $route = new Route('GET', 'features', fn () => '');
    $route->name('marketing.features');
    $request->setRouteResolver(fn () => $route);
    $response = app(RedirectPublicMarketingAliases::class)->handle($request, fn () => response('Public content'));
    expect($response->getStatusCode())->toBe(200)->and($response->headers->has('Location'))->toBeFalse();
})->with([
    ['local', 'https://clipperdesk.com'], ['testing', 'https://clipperdesk.com'],
    ['production', null], ['production', 'not a URL'], ['production', 'http://clipperdesk.com'],
    ['production', 'https://clipperdesk.com/subdirectory'], ['production', 'https://user:secret@clipperdesk.com'],
    ['production', 'https://clipperdesk.com/?target=elsewhere'],
]);

it('never redirects a successful mutation even when its route has a public name', function (string $method) {
    $request = Request::create('https://book.clipperdesk.com/features', $method);
    $route = new Route([$method], 'features', fn () => '');
    $route->name('marketing.features');
    $request->setRouteResolver(fn () => $route);
    $response = app(RedirectPublicMarketingAliases::class)->handle($request, fn () => response('Saved'));
    expect($response->getStatusCode())->toBe(200)->and($response->headers->has('Location'))->toBeFalse();
})->with(['POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS']);
