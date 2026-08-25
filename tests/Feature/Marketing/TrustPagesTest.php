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

it('keeps stable legal acceptance routes and publishes substantive versioned review drafts', function () {
    $this->get(route('terms.show'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('TermsOfService')
        ->where('document.version', '0.2-review')
        ->where('document.effective_at', 'Pending legal and Product approval')
        ->where('document.canonical', route('terms.show'))
        ->where('seo.canonical', route('terms.show'))
        ->where('terms', fn (string $terms) => str_contains($terms, 'Plans, trials, subscriptions and billing')
            && str_contains($terms, 'Salon-client bookings, payments and refunds')
            && str_contains($terms, 'Liability, indemnities, governing law and disputes')));
    $this->get(route('policy.show'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('PrivacyPolicy')
        ->where('document.version', '0.2-review')
        ->where('document.canonical', route('policy.show'))
        ->where('seo.canonical', route('policy.show'))
        ->where('policy', fn (string $policy) => str_contains($policy, 'Personal data we handle')
            && str_contains($policy, 'Payments and payment providers')
            && str_contains($policy, 'OPEN-10')));
    $this->get(route('register'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Auth/Register'));
});

it('publishes a Stripe-aware refund policy with a clear request and timing framework', function () {
    $this->get(route('refund.show'))
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, follow, noarchive')
        ->assertInertia(fn (Assert $page) => $page
            ->component('RefundPolicy')
            ->where('document.version', '0.1-review')
            ->where('document.canonical', route('refund.show'))
            ->where('seo.canonical', route('refund.show'))
            ->where('refund', fn (string $refund) => str_contains($refund, 'ClipperDesk subscription cancellations and refunds')
                && str_contains($refund, 'Salon appointments, deposits and sale cancellations')
                && str_contains($refund, '5 to 10 business days')
                && str_contains($refund, 'original payment method')
                && str_contains($refund, 'without a certified end-to-end Stripe refund executor')));
});

it('links the refund policy from evaluation checkout and the public footer', function () {
    expect(file_get_contents(resource_path('js/Components/Marketing/MarketingFooter.vue')))
        ->toContain("['Refund policy', 'refund.show']");
    expect(file_get_contents(resource_path('js/Pages/Marketing/Pricing.vue')))
        ->toContain("route('refund.show')");
    expect(file_get_contents(resource_path('js/Pages/Billing/Checkout.vue')))
        ->toContain('Refund Policy')
        ->toContain("route('refund.show')");
});

it('does not claim certifications or publish an unowned contact or status route', function () {
    $copy = file_get_contents(resource_path('js/Pages/Marketing/Security.vue'));
    expect($copy)->not->toContain('SOC 2 certified')->not->toContain('ISO 27001 certified')->not->toContain('HIPAA compliant')->not->toContain('99.9% uptime');
    $this->get('/contact')->assertNotFound();
    $this->get('/status')->assertNotFound();

    foreach (['terms.md', 'policy.md', 'refund.md'] as $document) {
        $copy = file_get_contents(resource_path('markdown/'.$document));
        expect($copy)
            ->not->toContain('@getgoodhours.com')
            ->not->toContain('SOC 2 certified')
            ->not->toContain('guaranteed refund');
    }
});
