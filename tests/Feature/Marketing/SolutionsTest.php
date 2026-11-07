<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('publishes the supported solution hub', function () {
    $this->get(route('marketing.solutions'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Marketing/Solutions/Index')->has('solutions', 13)->where('seo.canonical', route('marketing.solutions')));
});

it('publishes differentiated supported business pages', function (string $slug, string $phrase) {
    $this->get(route('marketing.solutions.show', $slug))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Marketing/Solutions/Show')
        ->where('solution.slug', $slug)
        ->has('solution.challenges', 3)
        ->has('solution.day', 4)
        ->has('solution.limits', 2)
        ->has('features', 3)
        ->where('solution.title', fn (string $title) => str_contains(strtolower($title), $phrase)));
})->with([
    ['barbershops', 'walk-ins'],
    ['salons', 'salon appointments'],
    ['independent-stylists', 'independent stylist'],
    ['spas', 'practitioners and rooms'],
    ['nail-salons', 'nail salon appointments'],
    ['medspas', 'medspa appointments'],
    ['massage', 'massage appointments'],
    ['fitness-recovery', 'fitness and recovery appointments'],
    ['physical-therapy', 'physical therapy appointments'],
    ['health-practices', 'health practice'],
    ['tattoo-piercing', 'tattoo and piercing appointments'],
    ['pet-grooming', 'pet grooming appointments'],
    ['tanning-studios', 'tanning studio appointments'],
]);

it('keeps unsupported keyword permutations out and regulated claims bounded', function () {
    $this->get('/solutions/hair-salons')->assertNotFound();
    $this->get('/solutions/beauty-salons')->assertNotFound();
    $this->get('/solutions/medical-spas')->assertNotFound();
    $this->get('/solutions/veterinary-clinics')->assertNotFound();

    expect(json_encode(config('frontsite.solutions')))
        ->not->toContain('guaranteed revenue')
        ->not->toContain('customer story')
        ->not->toContain('HIPAA compliant')
        ->toContain('not an electronic medical record system');
});


it('links only eligible audience articles and keeps unrelated industries separate', function () {
    $author = \App\Models\User::factory()->create();
    $slugs = ['salon-consultation-notes-a-practical-template-and-example', 'how-to-increase-salon-rebooking-scripts-and-a-30-day-plan', 'how-to-reduce-salon-no-shows-10-practical-strategies-that-work'];
    foreach ($slugs as $index => $slug) {
        \App\Models\Article::create([
            'user_id' => $author->id, 'title' => 'Practical salon guide', 'slug' => $slug,
            'content' => 'A useful routine for your team.', 'excerpt' => 'A working salon guide.',
            'topic' => 'Operations', 'thumbnail' => 'articles/guide.webp',
            'seo_title' => 'Salon working guide', 'seo_description' => 'Prepare a working salon day.',
            'active' => true, 'status' => $index === 1 ? 'draft' : 'published',
            'content_owner' => 'Editorial team', 'reviewed_at' => now()->subDay(),
            'published_at' => $index === 2 ? now()->addDay() : now()->subDay(),
        ]);
    }
    $this->get(route('marketing.solutions.show', 'salons'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('guides', 1)->where('guides.0.title', 'Practical salon guide')
        ->where('guides.0.href', route('blog.article', $slugs[0])));
    foreach (['barbershops', 'spas'] as $industry) {
        $this->get(route('marketing.solutions.show', $industry))->assertOk()->assertInertia(fn (Assert $page) => $page->has('guides', 0));
    }
});
