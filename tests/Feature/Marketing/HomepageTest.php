<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('renders a truthful ClipperDesk homepage with unique metadata', function () {
    $this->get(route('marketing.home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Home')
            ->where('seo.title', 'Salon and barbershop software from booking to checkout')
            ->where('seo.canonical', route('marketing.home')));

    $source = file_get_contents(resource_path('js/Pages/Home.vue'));

    expect($source)
        ->toContain('Run the day.</span><span')
        ->toContain('Grow the business.</span>')
        ->toContain('Run your salon or barbershop from booking to checkout')
        ->toContain('One operating system. Five steps. Every working day.')
        ->toContain('From booking to business insight—connected.')
        ->toContain('Calendar &amp; front desk')
        ->toContain('Client context')
        ->toContain('Checkout &amp; reporting')
        ->toContain('/images/marketing/system/orbital-operations.webp')
        ->toContain('/images/marketing/system/scheduling-capacity.webp')
        ->toContain('/images/marketing/system/client-context.webp')
        ->toContain('/images/marketing/system/checkout-insight.webp')
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
        ->toContain("route('marketing.pricing')")
        ->toContain("route('marketing.features.show', 'online-booking')")
        ->toContain("route('marketing.features.show', 'calendar-and-walk-ins')")
        ->toContain("route('marketing.features.show', 'checkout-and-reporting')")
        ->not->toContain('href="#"')
        ->not->toContain('Book a demo')
        ->not->toContain('Contact sales');
});
