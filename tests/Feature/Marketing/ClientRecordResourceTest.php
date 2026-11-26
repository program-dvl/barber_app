<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('publishes a printable fictional record template with source and product boundaries', function () {
    $url = route('marketing.guides.show', 'salon-client-record-template');
    $this->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('seo.canonical', $url)->has('guide.worksheet.rows', 6)
        ->has('guide.sections', 5)->has('guide.sources', 1)
        ->where('guide.feature', 'client-management')
        ->where('guide.related_guide', 'salon-client-migration-rehearsal'));
    $this->get('/help/articles/clients-visits-rebook')->assertOk();
    $this->get('/sitemap.xml')->assertOk()->assertSee($url);
    $html = view('marketing.guide-worksheet', ['guide' => config('frontsite.guides.salon-client-record-template')])->render();
    expect($html)->toContain('Printable client record and handoff template')->toContain('not an import file')->not->toContain('<input');
});

it('grounds product evaluation steps in fictional records and maintains unsupported feature limits', function () {
    foreach (['client-management', 'calendar-and-walk-ins'] as $feature) {
        $this->get(route('marketing.features.show', $feature))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('feature.walkthrough', 3));
    }
    $this->get(route('marketing.solutions.show', 'salons'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('solution.walkthrough', 3)
            ->where('guides.1.href', route('marketing.guides.show', 'salon-client-record-template')));
    expect(json_encode(config('frontsite.features.calendar-and-walk-ins.walkthrough')))
        ->toContain('An offer is not a booking')->toContain('are unavailable');
});
