<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('crawls every curated sitemap URL as a canonical indexable 200 page', function () {
    $xml = simplexml_load_string($this->get(route('sitemap.xml'))->assertOk()->getContent());
    $xml->registerXPathNamespace('sm', 'http://www.sitemaps.org/schemas/sitemap/0.9');
    $urls = collect($xml->xpath('//sm:url/sm:loc'))->map(fn ($node) => (string) $node);

    expect($urls)->toHaveCount(23);
    foreach ($urls as $url) {
        $path = parse_url($url, PHP_URL_PATH) ?: '/';
        $response = $this->get($path);
        $response->assertOk()->assertHeader('X-Robots-Tag', 'index, follow, max-image-preview:large')
            ->assertSee('<link rel="canonical" href="'.$url.'">', false);
    }
});

it('keeps attribution-like query values out of canonical identity', function () {
    $this->get('/pricing?utm_source=%3Cscript%3E&email=private%40example.test')
        ->assertOk()->assertSee('<link rel="canonical" href="'.route('marketing.pricing').'">', false)
        ->assertDontSee('private@example.test', false)->assertDontSee('&lt;script&gt;', false);
});
