<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('renders a truthful ClipperDesk homepage with unique metadata', function () {
    $this->get(route('marketing.home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Home')
            ->where('seo.title', 'Appointment scheduling & service business software | ClipperDesk')
            ->where('seo.canonical', route('marketing.home')));

    $source = file_get_contents(resource_path('js/Pages/Home.vue'));

    expect($source)
        ->toContain('Your whole day,')
        ->toContain('beautifully</span> run.')
        ->toContain('ambitious appointment-led service businesses')
        ->toContain('One calmer operating rhythm')
        ->toContain('Less admin between the moments that')
        ->toContain('Open ready.')
        ->toContain('Stay present.')
        ->toContain('Close clearly.')
        ->toContain('Calendar &amp; front desk')
        ->toContain('Client context')
        ->toContain('Checkout &amp; reporting')
        ->toContain('/images/marketing/industries/home-salon-hero.webp')
        ->toContain('/images/marketing/industries/barbershop.webp')
        ->toContain('/images/marketing/industries/nail-salon.webp')
        ->toContain('/images/marketing/industries/spa-sauna.webp')
        ->toContain('customer relationship is implied')
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
        ->toContain("route('marketing.solutions')")
        ->toContain("route('marketing.pricing')")
        ->toContain("route('marketing.features.show', 'online-booking')")
        ->toContain("route('marketing.features.show', 'calendar-and-walk-ins')")
        ->toContain("route('marketing.features.show', 'checkout-and-reporting')")
        ->not->toContain('href="#"')
        ->not->toContain('Book a demo')
        ->not->toContain('Contact sales');
});
