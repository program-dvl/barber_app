<?php

use App\Domain\HelpCenter\HelpCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// The curated marketing baseline is extended only by published Help content.

it('crawls every curated sitemap URL as a canonical indexable 200 page', function () {
    $xml = simplexml_load_string($this->get(route('sitemap.xml'))->assertOk()->getContent());
    $xml->registerXPathNamespace('sm', 'http://www.sitemaps.org/schemas/sitemap/0.9');
    $urls = collect($xml->xpath('//sm:url/sm:loc'))->map(fn ($node) => (string) $node);

    $help = app(HelpCatalog::class);
    $helpUrls = 1 + count($help->articles()) + collect($help->categories())->where('article_count', '>', 0)->count();
    expect($urls)->toHaveCount(32 + $helpUrls);
    foreach ($urls as $url) {
        $path = parse_url($url, PHP_URL_PATH) ?: '/';
        $response = $this->get($path);
        $response->assertOk()->assertHeader('X-Robots-Tag', 'index, follow, max-image-preview:large')
            ->assertSee('<link rel="canonical" href="'.$url.'" data-inertia="canonical">', false);
    }
});

it('keeps attribution-like query values out of canonical identity', function () {
    $this->get('/pricing?utm_source=%3Cscript%3E&email=private%40example.test')
        ->assertOk()->assertSee('<link rel="canonical" href="'.route('marketing.pricing').'" data-inertia="canonical">', false)
        ->assertDontSee('private@example.test', false)->assertDontSee('&lt;script&gt;', false);
});
