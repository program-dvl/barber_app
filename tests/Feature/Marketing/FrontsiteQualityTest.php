<?php

it('declares the launch language without publishing fake locale alternatives', function () {
    $this->get(route('marketing.home'))->assertOk()->assertSee('<html lang="en-IN"', false)->assertDontSee('hreflang=', false);
});

it('keeps the shared public accessibility foundations present', function () {
    $layout = file_get_contents(resource_path('js/Layouts/MarketingLayout.vue'));
    $css = file_get_contents(resource_path('css/app.css'));
    expect($layout)->toContain('href="#main-content"')->toContain('id="main-content"')->toContain('tabindex="-1"');
    expect($css)->toContain(':focus-visible')->toContain('prefers-reduced-motion: reduce')->toContain('scroll-padding-top: 6rem')->toContain('@media print');
});

it('keeps public font and evidence-image files within component budgets', function () {
    foreach (glob(public_path('fonts/good-hours/*.{ttf,woff2}'), GLOB_BRACE) as $font) {
        expect(filesize($font))->toBeLessThanOrEqual(130 * 1024);
    }
    foreach ([
        base_path('docs/evidence/product-shell/good-hours-shop-dashboard-desktop-1488.png'),
        base_path('docs/evidence/product-shell/good-hours-public-booking-360.png'),
    ] as $image) {
        expect(filesize($image))->toBeLessThanOrEqual(550 * 1024);
    }
});
