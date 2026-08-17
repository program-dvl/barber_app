<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('publishes the evidence-backed feature hub', function () {
    $this->get(route('marketing.features'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Marketing/Features/Index')
            ->has('features', 4)
            ->where('features.0.slug', 'online-booking')
            ->where('seo.canonical', route('marketing.features')));
});

it('publishes unique substantive feature pages', function (string $slug, string $title) {
    $this->get(route('marketing.features.show', $slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Marketing/Features/Show')
            ->where('feature.slug', $slug)
            ->where('feature.title', $title)
            ->has('feature.workflow', 4)
            ->has('feature.proof', 3)
            ->has('feature.limitations', 2)
            ->has('related', 2)
            ->where('seo.canonical', route('marketing.features.show', $slug)));
})->with([
    ['online-booking', 'Online booking that respects the working calendar'],
    ['calendar-and-walk-ins', 'Run booked appointments and walk-ins in one operational day'],
    ['client-management', 'Keep useful client context without weakening consent or access'],
    ['checkout-and-reporting', 'Close the service and keep the numbers explainable'],
]);

it('does not expose unapproved feature permutations', function () {
    $this->get('/features/ai-receptionist')->assertNotFound();
    $this->get('/features/payroll')->assertNotFound();

    $content = json_encode(config('frontsite.features'));
    expect($content)
        ->not->toContain('gift card')
        ->not->toContain('loyalty')
        ->not->toContain('forecasting')
        ->not->toContain('HIPAA');
});
