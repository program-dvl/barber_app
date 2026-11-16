<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('offers a free public calculator with canonical metadata and readable non-SSR content', function () {
    config(['inertia.ssr.enabled' => false]);
    $this->get('/tools/no-show-calculator')->assertOk()
        ->assertHeader('X-Robots-Tag', 'index, follow, max-image-preview:large')
        ->assertSee('Free no-show cost calculator')
        ->assertSee('monthly estimate')
        ->assertInertia(fn (Assert $p) => $p->component('Marketing/Tools/NoShowCalculator')
            ->where('seo.canonical', url('/tools/no-show-calculator'))
            ->where('toolFallback', true)->where('publicAnalytics', null));
});

it('keeps calculator input out of canonical URLs, schema and analytics props', function () {
    $this->get('/tools/no-show-calculator?value=PRIVATE_FIGURE&missed=123')->assertOk()
        ->assertInertia(fn (Assert $p) => $p->where('seo.canonical', url('/tools/no-show-calculator'))
            ->where('publicAnalytics', null)->missing('value')->missing('missed'));
});

it('includes the canonical calculator once in the sitemap without indexing private routes', function () {
    $xml = $this->get('/sitemap.xml')->assertOk()->getContent();
    expect(substr_count($xml, '<loc>'.url('/tools/no-show-calculator').'</loc>'))->toBe(1);
    $this->get('/tools/unknown-calculator')->assertNotFound()->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
});

it('uses the production canonical origin and redirects marketing aliases for the tool', function () {
    app()->detectEnvironment(fn () => 'production');
    config(['app.url' => 'https://www.clipperdesk.com', 'brand.website_url' => 'https://clipperdesk.com']);
    $this->get('https://www.clipperdesk.com/tools/no-show-calculator')->assertRedirect('https://clipperdesk.com/tools/no-show-calculator');
});

it('renders the real built calculator markup through the live SSR server', function () {
    if (! getenv('CALCULATOR_LIVE_SSR')) {
        $this->markTestSkipped('Requires the built local SSR server.');
    }
    config(['inertia.ssr.enabled' => true, 'inertia.ssr.url' => getenv('CALCULATOR_SSR_URL') ?: 'http://127.0.0.1:13714']);
    $response = $this->get('/tools/no-show-calculator')->assertOk()->assertSee('Empty appointments.')->assertSee('Estimated earnings at risk')->assertSee('$480')->assertSee('$5,760')->assertSee('id="ns-value"', false)->assertSee('id="ns-recovery"', false)->assertSee('Make it more accurate')->assertSee('id="ns-refinements"', false)->assertDontSee('Enable JavaScript for the interactive calculator');
    $html = $response->getContent();
    $document = new DOMDocument;
    @$document->loadHTML($html);
    $xpath = new DOMXPath($document);
    foreach (['ns-value', 'ns-collected', 'ns-avoided'] as $id) {
        $input = $document->getElementById($id);
        expect($input)->not->toBeNull();
        $affixes = $xpath->query('./span[contains(concat(" ", normalize-space(@class), " "), " ns-currency-affix ")]', $input->parentNode);
        expect($affixes->length)->toBe(1);
        expect(trim($affixes->item(0)->textContent))->toBe('USD');
        expect($affixes->item(0)->getAttribute('aria-hidden'))->toBe('true');
        expect($xpath->query('//label[@for="'.$id.'"]')->length)->toBe(1);
        expect($document->getElementById($input->getAttribute('aria-describedby')))->not->toBeNull();
    }
    foreach (['ns-missed', 'ns-refilled'] as $id) {
        $input = $document->getElementById($id);
        $unit = $xpath->query('./span[contains(concat(" ", normalize-space(@class), " "), " ns-unit ")]', $input->parentNode);
        expect($unit->length)->toBe(1);
        expect($unit->item(0)->getAttribute('aria-hidden'))->toBe('true');
    }
    $this->get('/resources')->assertOk()->assertSee('Try the no-show calculator');
});
