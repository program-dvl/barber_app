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

it('keeps public font and marketing-image files within component budgets', function () {
    foreach (glob(public_path('fonts/clipperdesk/*.{ttf,woff2}'), GLOB_BRACE) as $font) {
        expect(filesize($font))->toBeLessThanOrEqual(130 * 1024);
    }
    $images = glob(public_path('images/marketing/industries/*.webp'));
    $editorialImages = glob(public_path('images/marketing/editorial/*.webp'));

    expect($images)->toHaveCount(13);
    expect($editorialImages)->toHaveCount(6);

    foreach ([...$images, ...$editorialImages] as $image) {
        expect(filesize($image))->toBeLessThanOrEqual(550 * 1024);
    }
});
