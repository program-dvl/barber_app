<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('adds browser security and generated correlation headers', function () {
    $response = $this->get('/');

    $response->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(self)');

    expect(Str::isUuid((string) $response->headers->get('X-Correlation-ID')))->toBeTrue();
});

it('accepts only UUID correlation identifiers from callers', function () {
    $valid = (string) Str::uuid();

    $this->withHeader('X-Correlation-ID', $valid)->get('/')->assertHeader('X-Correlation-ID', $valid);

    $replacement = $this->withHeader('X-Correlation-ID', "unsafe\nvalue")->get('/')->headers->get('X-Correlation-ID');
    expect($replacement)->not->toBe("unsafe\nvalue")
        ->and(Str::isUuid((string) $replacement))->toBeTrue();
});

it('prevents browser storage of authenticated and token-protected responses', function () {
    $this->actingAs(User::factory()->create())->get('/')->assertHeader('Cache-Control', 'no-store, private');

    $token = str_repeat('a', 64);
    $this->get('/client-files/'.$token)->assertHeader('Cache-Control', 'no-store, private');
});
