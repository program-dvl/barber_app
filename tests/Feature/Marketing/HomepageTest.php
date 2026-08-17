<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('renders a truthful Good Hours homepage with unique metadata', function () {
    $this->get(route('marketing.home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Home')
            ->where('seo.title', 'Salon and barbershop software from booking to checkout')
            ->where('seo.canonical', route('marketing.home')));

    $source = file_get_contents(resource_path('js/Pages/Home.vue'));

    expect($source)
        ->toContain('Run your salon or barbershop from booking to checkout.')
        ->toContain('Get booked')
        ->toContain('Protect the calendar')
        ->toContain('Run the day')
        ->toContain('Get paid')
        ->toContain('Know clients')
        ->toContain('Know the business')
        ->toContain('data is used')
        ->not->toContain('Larafast')
        ->not->toContain('Trusted by')
        ->not->toContain('99.9%')
        ->not->toContain('Watch Demo')
        ->not->toContain('Integrations');
});

it('uses one page heading and only valid homepage destinations', function () {
    $source = file_get_contents(resource_path('js/Pages/Home.vue'));

    expect(substr_count($source, '<h1'))
        ->toBe(1)
        ->and($source)
        ->toContain("route('marketing.features')")
        ->not->toContain('href="#"')
        ->not->toContain('Book a demo')
        ->not->toContain('Contact sales');
});
