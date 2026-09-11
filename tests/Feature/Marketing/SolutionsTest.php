<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('publishes the supported solution hub', function () {
    $this->get(route('marketing.solutions'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Marketing/Solutions/Index')->has('solutions', 13)->where('seo.canonical', route('marketing.solutions')));
});

it('publishes differentiated supported business pages', function (string $slug, string $phrase) {
    $this->get(route('marketing.solutions.show', $slug))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Marketing/Solutions/Show')
        ->where('solution.slug', $slug)
        ->has('solution.challenges', 3)
        ->has('solution.day', 4)
        ->has('solution.limits', 2)
        ->has('features', 3)
        ->where('solution.title', fn (string $title) => str_contains($title, $phrase)));
})->with([
    ['barbershops', 'walk-in line'],
    ['salons', 'multi-service work'],
    ['independent-stylists', 'independent stylist'],
    ['spas', 'reserves the room'],
    ['nail-salons', 'Nail salon software'],
    ['medspas', 'clinical boundaries'],
    ['massage', 'Massage studio software'],
    ['fitness-recovery', 'Fitness and recovery scheduling'],
    ['physical-therapy', 'non-clinical side'],
    ['health-practices', 'independent health practices'],
    ['tattoo-piercing', 'Tattoo and piercing studio'],
    ['pet-grooming', 'Pet grooming software'],
    ['tanning-studios', 'Tanning studio software'],
]);

it('keeps unsupported keyword permutations out and regulated claims bounded', function () {
    $this->get('/solutions/hair-salons')->assertNotFound();
    $this->get('/solutions/beauty-salons')->assertNotFound();
    $this->get('/solutions/medical-spas')->assertNotFound();
    $this->get('/solutions/veterinary-clinics')->assertNotFound();

    expect(json_encode(config('frontsite.solutions')))
        ->not->toContain('guaranteed revenue')
        ->not->toContain('customer story')
        ->not->toContain('HIPAA compliant')
        ->toContain('not an electronic medical record system');
});
