<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('publishes factual company and security pages without invented assurance', function (string $routeName, string $component) {
    $this->get(route($routeName))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component($component)
        ->where('seo.canonical', route($routeName)));
})->with([
    ['marketing.company', 'Marketing/Company'],
    ['marketing.security', 'Marketing/Security'],
]);

it('keeps stable legal acceptance routes and marks their drafts honestly', function () {
    $this->get(route('terms.show'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('TermsOfService')
        ->where('terms', fn (string $terms) => str_contains(strtolower($terms), 'not approved for public launch') && str_contains($terms, '16 August 2026')));
    $this->get(route('policy.show'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('PrivacyPolicy')
        ->where('policy', fn (string $policy) => str_contains(strtolower($policy), 'not approved for public launch') && str_contains($policy, 'OPEN-10')));
    $this->get(route('register'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Auth/Register'));
});

it('does not claim certifications or publish an unowned contact or status route', function () {
    $copy = file_get_contents(resource_path('js/Pages/Marketing/Security.vue'));
    expect($copy)->not->toContain('SOC 2 certified')->not->toContain('ISO 27001 certified')->not->toContain('HIPAA compliant')->not->toContain('99.9% uptime');
    $this->get('/contact')->assertNotFound();
    $this->get('/status')->assertNotFound();
});
