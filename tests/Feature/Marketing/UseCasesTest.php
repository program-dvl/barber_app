<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('publishes a limited evidence-backed use-case hub', function () {
    $this->get(route('marketing.use-cases'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Marketing/UseCases/Index')
            ->has('useCases', 4)
            ->where('useCases.0.slug', 'reduce-scheduling-conflicts')
            ->where('seo.canonical', route('marketing.use-cases')));
});

it('publishes each distinct problem answer with guidance and evidence', function (string $slug, string $feature) {
    $this->get(route('marketing.use-cases.show', $slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Marketing/UseCases/Show')
            ->where('useCase.slug', $slug)
            ->where('feature.slug', $feature)
            ->has('useCase.symptoms', 3)
            ->has('useCase.practice', 4)
            ->has('useCase.product_steps', 4)
            ->has('useCase.limitations', 2)
            ->where('seo.canonical', route('marketing.use-cases.show', $slug)));
})->with([
    ['reduce-scheduling-conflicts', 'calendar-and-walk-ins'],
    ['manage-walk-ins-and-appointments', 'calendar-and-walk-ins'],
    ['protect-time-with-deposits', 'online-booking'],
    ['keep-client-history-together', 'client-management'],
]);

it('rejects thin or unsupported problem permutations', function () {
    $this->get('/use-cases/eliminate-no-shows')->assertNotFound();
    $this->get('/use-cases/ai-salon-automation')->assertNotFound();

    $copy = json_encode(config('frontsite.use_cases'));
    expect($copy)
        ->not->toContain('eliminate no-shows')
        ->not->toContain('guarantee new clients')
        ->not->toContain('easy migration');
});
