<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('renders a truthful ClipperDesk homepage with unique metadata', function () {
    $this->get(route('marketing.home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Home')
            ->where('seo.title', 'Appointment Scheduling & Business Software | ClipperDesk')
            ->where('seo.canonical', route('marketing.home')));

    $source = file_get_contents(resource_path('js/Pages/Home.vue'));

    expect($source)
        ->toContain('A good day.')
        ->toContain('By design.')
        ->toContain('homepage.lead')
        ->toContain('SalonDayDemo')
        ->toContain('From first booking')
        ->toContain('/images/marketing/homepage/salon-day.webp')
        ->toContain('No customer relationship is implied')
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
        ->toContain("route('marketing.features.show', 'client-management')")
        ->toContain("route('marketing.features.show', 'calendar-and-walk-ins')")
        ->toContain("route('marketing.features.show', 'checkout-and-reporting')")
        ->not->toContain('href="#"')
        ->not->toContain('Book a demo')
        ->not->toContain('Contact sales');
});

it('keeps homepage content and conversion links in original HTML when SSR is unavailable', function () {
    config(['inertia.ssr.enabled' => false]);
    $response = $this->get(route('marketing.home'))->assertOk();
    $response->assertSee('<h1>A good day. By design.</h1>', false)
        ->assertSee('From first booking to next visit.')
        ->assertSee('href="'.route('register').'"', false)
        ->assertSee('href="'.route('marketing.pricing').'"', false)
        ->assertSee('It does not charge cards');
    expect(substr_count($response->getContent(), '<h1'))->toBe(1);
});

it('shares meaningful intent coverage with the original HTML and resolves every editorial destination', function () {
    config(['inertia.ssr.enabled' => false]);
    $response = $this->get(route('marketing.home'))->assertOk();
    $dom = new DOMDocument;
    @$dom->loadHTML($response->getContent());
    $xpath = new DOMXPath($dom);
    $main = $xpath->query('//main')->item(0);
    expect($main)->not->toBeNull();
    foreach (config('homepage.faqs') as $faq) {
        expect($main->textContent)->toContain($faq['question'])->toContain($faq['answer']);
    }
    foreach (config('homepage.moment_benefits') as $benefit) {
        expect($main->textContent)->toContain($benefit['title'])->toContain($benefit['body']);
    }
    expect($main->textContent)->toContain(config('homepage.audience'));
    expect(array_column(config('homepage.businesses'), 'slug'))->toEqualCanonicalizing(array_keys(config('frontsite.solutions')));
    foreach (config('homepage.businesses') as $business) {
        expect(config('frontsite.solutions.'.$business['slug']))->toBeArray();
        $url = route('marketing.solutions.show', $business['slug']);
        expect($xpath->query('//main//a[@href="'.$url.'"]')->length)->toBe(1);
        $this->get($url)->assertOk();
    }
    foreach (config('homepage.use_cases') as $item) {
        expect($main->textContent)->toContain($item['title'])->toContain($item['body']);
        $url = route($item['route'], $item['slug']);
        expect($xpath->query('//main//a[@href="'.$url.'"]')->length)->toBe(1);
        $this->get($url)->assertOk();
    }
    expect($xpath->query('//title')->length)->toBe(1)
        ->and($xpath->query('//link[@rel="canonical"]')->length)->toBe(1)
        ->and($xpath->query('//meta[@name="description"]')->length)->toBe(1)
        ->and($xpath->query('//meta[@property="og:title"]')->item(0)->getAttribute('content'))->toBe(config('homepage.title'));
    $schema = json_decode($xpath->query('//script[@type="application/ld+json"]')->item(0)->textContent, true, flags: JSON_THROW_ON_ERROR);
    expect(array_column($schema['@graph'], '@type'))->toContain('Organization', 'WebSite', 'WebPage', 'SoftwareApplication');
    foreach ($schema['@graph'] as $entity) {
        expect($entity)->not->toHaveKeys(['aggregateRating', 'review']);
    }
});
