<?php

it('keeps the canonical ClipperDesk definition aligned in visible and machine-readable content', function () {
    $home = file_get_contents(resource_path('js/Pages/Home.vue'));
    $schema = file_get_contents(app_path('Support/Seo/StructuredDataGraph.php'));
    expect($home)->toContain('ClipperDesk is appointment scheduling and service business management software')
        ->and(config('brand.description'))->toContain('appointment scheduling and service business management software')
        ->and(config('brand.description'))->toContain('fitness, recovery, health and pet-care')
        ->and($schema)->toContain("config('brand.description')");
});

it('contains no machine-only or unsupported answer-engine tactics', function () {
    $publicCopy = collect([
        resource_path('js/Pages/Home.vue'), resource_path('js/Pages/Marketing/Company.vue'),
        resource_path('js/Pages/Marketing/Security.vue'), config_path('frontsite.php'),
    ])->map(fn ($file) => file_get_contents($file))->implode("\n");

    expect($publicCopy)->not->toContain('AI-powered')->not->toContain('AI receptionist')
        ->not->toContain('best salon software')->not->toContain('guaranteed revenue')
        ->not->toContain('SOC 2 certified')->not->toContain('HIPAA compliant');
    expect(file_exists(public_path('llms.txt')))->toBeFalse();
});

it('keeps generic acquisition pages broad while allowing industry pages to be specific', function () {
    $genericCopy = collect([
        resource_path('js/Pages/Home.vue'),
        resource_path('js/Pages/Marketing/Features/Index.vue'),
        resource_path('js/Pages/Marketing/UseCases/Index.vue'),
        resource_path('js/Pages/Marketing/Resources/Index.vue'),
        resource_path('js/Pages/Marketing/Pricing.vue'),
        resource_path('js/Pages/Marketing/Company.vue'),
        resource_path('js/Pages/Blog.vue'),
    ])->map(fn ($file) => file_get_contents($file))->implode("\n");

    expect($genericCopy)
        ->toContain('appointment-led service businesses')
        ->toContain('fitness')
        ->toContain('health')
        ->toContain('pet');
});
