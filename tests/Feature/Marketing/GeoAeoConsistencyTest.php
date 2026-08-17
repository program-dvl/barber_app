<?php

it('keeps the canonical Good Hours definition aligned in visible and machine-readable content', function () {
    $home = file_get_contents(resource_path('js/Pages/Home.vue'));
    $schema = file_get_contents(app_path('Support/Seo/StructuredDataGraph.php'));
    expect($home)->toContain('Good Hours is the daily operating system for salons and barbershops')
        ->and($schema)->toContain('daily operating system for salons and barbershops');
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
