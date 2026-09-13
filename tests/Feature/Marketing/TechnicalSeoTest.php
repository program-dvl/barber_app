<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders centralized metadata and a truthful schema graph for an indexable page', function () {
    $response = $this->get(route('marketing.home'));
    $response->assertOk()->assertHeader('X-Robots-Tag', 'index, follow, max-image-preview:large')
        ->assertSee('<link rel="canonical" href="'.route('marketing.home').'">', false)
        ->assertSee('<meta property="og:url" content="'.route('marketing.home').'">', false)
        ->assertSee('application/ld+json', false);
    preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $response->getContent(), $matches);
    $graph = json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR);
    expect(collect($graph['@graph'])->pluck('@type')->all())->toContain('Organization', 'WebSite', 'WebPage', 'SoftwareApplication');
    $software = collect($graph['@graph'])->firstWhere('@type', 'SoftwareApplication');
    expect($software['audience']['audienceType'])->toContain('fitness', 'health', 'pet-service')
        ->and($software['featureList'])->toContain('Online booking', 'Calendar and walk-ins', 'Client management', 'Checkout and reporting');
    expect($matches[1])->not->toContain('founder')->not->toContain('award')->not->toContain('Review')->not->toContain('LocalBusiness');
});

it('fails closed for auth booking legal secure and missing route families', function (string $url, string $directive) {
    $this->get($url)->assertHeader('X-Robots-Tag', $directive);
})->with([
    ['/login', 'noindex, follow, noarchive'], ['/register', 'noindex, follow, noarchive'], ['/terms-of-service', 'noindex, follow, noarchive'], ['/privacy-policy', 'noindex, follow, noarchive'], ['/refund-policy', 'noindex, follow, noarchive'], ['/book', 'noindex, follow, noarchive'],
    ['/appointments/secure/'.str_repeat('a', 64), 'noindex, nofollow, noarchive'], ['/missing-public-page', 'noindex, nofollow, noarchive'],
]);

it('publishes a deterministic curated sitemap and canonical robots reference', function () {
    $xml = $this->get(route('sitemap.xml'))->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->getContent();
    $document = simplexml_load_string($xml);
    expect($document)->not->toBeFalse();
    $document->registerXPathNamespace('sm', 'http://www.sitemaps.org/schemas/sitemap/0.9');
    $urls = collect($document->xpath('//sm:url/sm:loc'))->map(fn ($url) => (string) $url);
    expect($urls)->toContain(route('marketing.home'), route('marketing.pricing'), route('marketing.resources'))->not->toContain(route('register'))->not->toContain(url('/book'))->not->toContain(url('/roadmap'));
    expect($urls->count())->toBe(32);
    $this->get('/sitemap')->assertRedirect(route('sitemap.xml'))->assertStatus(301);
    $this->get('/robots.txt')->assertOk()
        ->assertSee("User-agent: OAI-SearchBot\nAllow: /", false)
        ->assertSee("User-agent: ChatGPT-User\nAllow: /", false)
        ->assertSee('Sitemap: '.route('sitemap.xml'), false);
});

it('publishes unique useful search titles and descriptions across the curated public site', function () {
    $xml = simplexml_load_string($this->get(route('sitemap.xml'))->assertOk()->getContent());
    $xml->registerXPathNamespace('sm', 'http://www.sitemaps.org/schemas/sitemap/0.9');
    $urls = collect($xml->xpath('//sm:url/sm:loc'))->map(fn ($node) => (string) $node);
    $titles = collect();
    $descriptions = collect();

    foreach ($urls as $url) {
        $html = $this->get(parse_url($url, PHP_URL_PATH) ?: '/')->assertOk()->getContent();
        preg_match('/<meta property="og:title" content="([^"]+)">/', $html, $titleMatch);
        preg_match('/<meta name="description" content="([^"]+)">/', $html, $descriptionMatch);

        expect($titleMatch[1] ?? null)->not->toBeNull()
            ->and($descriptionMatch[1] ?? null)->not->toBeNull();
        $title = html_entity_decode($titleMatch[1], ENT_QUOTES | ENT_HTML5);
        $description = html_entity_decode($descriptionMatch[1], ENT_QUOTES | ENT_HTML5);
        expect(mb_strlen($title))->toBeLessThanOrEqual(70)
            ->and(mb_strlen($description))->toBeGreaterThanOrEqual(80)
            ->toBeLessThanOrEqual(180);
        $titles->push($title);
        $descriptions->push($description);
    }

    expect($titles->unique())->toHaveCount($titles->count())
        ->and($descriptions->unique())->toHaveCount($descriptions->count());
});

it('returns an accessible real 404 and keeps local-only og utilities out of testing', function () {
    $this->get('/not-a-real-route')->assertNotFound()->assertInertia(fn ($page) => $page->component('Error')->where('status', 404));
    $this->get('/og-image-testing')->assertNotFound();
});
