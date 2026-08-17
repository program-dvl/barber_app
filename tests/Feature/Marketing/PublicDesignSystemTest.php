<?php

use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('renders the named public home route with the marketing shell contract', function () {
    $this->get(route('marketing.home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Home')
            ->where('canLogin', true)
            ->where('canRegister', true));

    expect(file_get_contents(resource_path('js/Layouts/MarketingLayout.vue')))
        ->toContain('Skip to main content')
        ->toContain('id="main-content"')
        ->toContain('MarketingHeader')
        ->toContain('MarketingFooter');
});

it('keeps marketing navigation on real named routes with accessible mobile behavior', function () {
    $header = file_get_contents(resource_path('js/Components/Marketing/MarketingHeader.vue'));
    $footer = file_get_contents(resource_path('js/Components/Marketing/MarketingFooter.vue'));

    expect($header)
        ->toContain('gh-marketing-header')
        ->toContain('<ProductMark />')
        ->not->toContain('<ProductMark inverse />')
        ->toContain('aria-controls="marketing-mobile-menu"')
        ->toContain(':aria-expanded="menuOpen"')
        ->toContain("event.key === 'Escape'")
        ->toContain('watch(() => page.url')
        ->not->toContain('href="#"')
        ->not->toContain('Book a demo')
        ->not->toContain('newsletter');

    expect(file_get_contents(resource_path('css/app.css')))
        ->toContain('.gh-marketing-header')
        ->toContain('background: rgb(255 252 247 / 0.94)');

    expect($footer)
        ->not->toContain('href="#"')
        ->not->toContain('Twitter')
        ->not->toContain('GitHub')
        ->not->toContain('Subscribe');

    expect(file_get_contents(resource_path('js/Components/Marketing/PublicCta.vue')))
        ->toContain('data-cta-context')
        ->toContain('data-cta-action');
});

it('provides an authenticated dashboard action without a signup loop', function () {
    $user = User::factory()->create();
    $business = Business::factory()->create();
    Membership::factory()->for($business)->for($user)->create();

    $this->actingAs($user)->get(route('marketing.home'))->assertOk();

    expect(file_get_contents(resource_path('js/Components/Marketing/PublicCta.vue')))
        ->toContain("authenticated.value ? route('dashboard') : route('register')")
        ->toContain("authenticated.value ? 'Open dashboard' : 'Start your trial'");
});
