<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('explains supported record keeping without claiming clinical systems', function () {
    $this->get('/features/client-management')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('feature.faqs.0.question', 'What should salon record keeping software keep in one client profile?')
        ->where('feature.faqs.0.answer', fn ($answer) => str_contains($answer, 'role-based access') && str_contains($answer, 'not a medical record')));
});

it('connects future-opening evaluation to the real customer help journey', function () {
    $this->get('/features/calendar-and-walk-ins')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('feature.definition', fn ($text) => str_contains($text, 'future request is never mistaken for a confirmed appointment'))
        ->where('feature.faqs.0.answer', fn ($text) => str_contains($text, 'does not provide a general owner-facing waitlist dashboard'))
        ->where('reading', fn ($items) => collect($items)->contains('href', route('help.article', 'appointments-waitlist'))));
    $this->get('/help/articles/appointments-waitlist')->assertOk();
});

it('keeps salon CRM claims scoped to actual client history', function () {
    $this->get('/solutions/salons')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('solution.faqs.0.question', 'What does salon client management include between appointments?')
        ->where('solution.faqs.0.answer', fn ($text) => str_contains($text, 'does not provide memberships')));
});
