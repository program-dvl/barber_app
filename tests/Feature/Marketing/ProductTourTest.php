<?php

use App\Domain\HelpCenter\HelpCatalog;
use App\Support\Seo\ProductEvidence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('serves a public walkthrough using only verified public demo media and published help', function () {
    $response = $this->get(route('marketing.product-tour'))->assertOk()
        ->assertHeader('X-Robots-Tag', 'index, follow, max-image-preview:large')
        ->assertInertia(fn (Assert $page) => $page->component('Marketing/ProductTour')
            ->has('steps', 5)->where('seo.canonical', route('marketing.product-tour')));

    $steps = $response->viewData('page')['props']['steps'];
    foreach ($steps as $step) {
        expect(app(HelpCatalog::class)->find($step['help']))->not->toBeNull();
        expect(config('frontsite.features.'.$step['feature']))->toBeArray();
        expect($step['screenshots'])->toHaveCount(1);
        $media = $step['screenshots'][0];
        expect($media['path'])->toStartWith('/images/help/');
        expect(array_keys($media))->not->toContain('sha256');
        expect($media['alt'])->not->toBeEmpty();
    }
    $xml = $this->get(route('sitemap.xml'))->assertOk()->getContent();
    expect($xml)->toContain(route('marketing.product-tour'))
        ->toContain(route('marketing.guides.show', 'daily-operations-checklist'));
});

it('fails closed when a reviewed screenshot cannot be verified', function () {
    config(['help.media_manifest' => storage_path('missing-public-media.json')]);
    expect(app(ProductEvidence::class)->screenshots(['calendar', 'unapproved']))->toBe([]);
    $this->get(route('marketing.product-tour'))->assertOk()->assertInertia(fn (Assert $page) => $page->has('steps.0.screenshots', 0));
});

it('keeps all thirteen industry choices and adds evidence only to reviewed industry pages', function () {
    $this->get(route('marketing.solutions'))->assertOk()->assertInertia(fn (Assert $page) => $page->has('solutions', 13));
    foreach (['salons', 'barbershops'] as $slug) {
        $this->get(route('marketing.solutions.show', $slug))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('solution.screenshots', 2)->has('solution.faqs', 4));
    }
    $this->get(route('marketing.solutions.show', 'pet-grooming'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('solution.screenshots', 0)->where('solution.slug', 'pet-grooming'));
});

it('publishes a distinct daily checklist with truthful attribution and Article dates', function () {
    $response = $this->get(route('marketing.guides.show', 'daily-operations-checklist'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('guide.sections', 5)->has('guide.checklist', 10)
            ->where('guide.published_at', '2026-11-05')->where('guide.reviewed_at', '2026-11-05'));
    $schema = collect($response->viewData('page')['props']['structuredData']['@graph'])->firstWhere('@type', 'Article');
    expect($schema['datePublished'])->toBe('2026-11-05');
    expect($schema['author']['@type'])->toBe('Organization');
});
