<?php

use App\Models\Article;
use App\Models\User;
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
        ->has('solution.screenshots', 2)
        ->has('solution.checks', 3)
        ->where('solution.faqs', fn ($faqs) => count($faqs) >= 3)
        ->missing('solution.requirements')
        ->where('trialLabel', config('billing.trial_label'))
        ->where('solution.title', fn (string $title) => str_contains(strtolower($title), $phrase)));
})->with([
    ['barbershops', 'walk-ins'],
    ['salons', 'salon appointments'],
    ['independent-stylists', 'independent stylist'],
    ['spas', 'spa treatment'],
    ['nail-salons', 'nail salon appointments'],
    ['medspas', 'medspa appointments'],
    ['massage', 'massage appointments'],
    ['fitness-recovery', 'fitness and recovery appointments'],
    ['physical-therapy', 'physical therapy appointments'],
    ['health-practices', 'health practice'],
    ['tattoo-piercing', 'tattoo and piercing appointments'],
    ['pet-grooming', 'pet grooming appointments'],
    ['tanning-studios', 'tanning studio visits'],
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
    $author = User::factory()->create();
    $slugs = ['salon-consultation-notes-a-practical-template-and-example', 'how-to-increase-salon-rebooking-scripts-and-a-30-day-plan', 'how-to-reduce-salon-no-shows-10-practical-strategies-that-work'];
    foreach ($slugs as $index => $slug) {
        Article::create([
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
        ->has('guides', 3)->where('guides.0.title', 'Practical salon guide')
        ->where('guides.0.href', route('blog.article', $slugs[0])));
    foreach (['barbershops', 'spas'] as $industry) {
        $this->get(route('marketing.solutions.show', $industry))->assertOk()->assertInertia(fn (Assert $page) => $page->has('guides', 0));
    }
});

it('keeps solution decisions and all hub destinations readable when SSR is unavailable', function () {
    config(['inertia.ssr.enabled' => false]);
    $hub = $this->get('/solutions')->assertOk();
    foreach (config('frontsite.solutions') as $slug => $content) {
        $hub->assertSee('href="'.route('marketing.solutions.show', $slug).'"', false);
        $response = $this->get('/solutions/'.$slug)->assertOk();
        $headline = config('solution-experiences.'.$slug.'.headline');
        $response->assertSee('<h1>'.e($headline ? str_replace('\n', ' ', $headline) : $content['title']).'</h1>', false)
            ->assertSee(e($content['checks'][0]), false)
            ->assertSee(e($content['limits'][0]), false)
            ->assertSee(route('marketing.contact'), false);
    }
});

it('renders every rebuilt solution through the actual built SSR renderer', function () {
    if (! getenv('SOLUTIONS_LIVE_SSR')) {
        $this->markTestSkipped('Requires the built local SSR renderer.');
    }
    config(['inertia.ssr.enabled' => true, 'inertia.ssr.url' => 'http://127.0.0.1:13714']);
    foreach (array_keys(config('frontsite.solutions')) as $slug) {
        $html = $this->get('/solutions/'.$slug)->assertOk()->getContent();
        expect($html)->toContain(config('solution-experiences.'.$slug) ? 'class="experience-hero"' : 'class="solution-intro"')->toContain('id="fit-check"')
            ->toContain('fetchpriority="high"')->toContain('Enlarge screenshot:');
        $visible = preg_replace('/<script\b[^>]*>.*?<\/script>/s', '', $html);
        expect(preg_match_all('/<h1\b/', $visible))->toBe(1);
    }
    $this->get('/solutions')->assertOk()->assertSee('class="solution-hub-cards"', false);
});

it('covers every supported industry with editorial experiences and preserves sample content without JavaScript', function () {
    config(['inertia.ssr.enabled' => false]);
    expect(array_keys(config('solution-experiences')))->toEqualCanonicalizing(array_keys(config('frontsite.solutions')));
    foreach (config('solution-experiences') as $slug => $experience) {
        $response = $this->get('/solutions/'.$slug)->assertOk();
        $dom = new DOMDocument;
        @$dom->loadHTML($response->getContent());
        $main = (new DOMXPath($dom))->query('//main')->item(0)->textContent;
        expect($main)->toContain($experience['demo_title'])->toContain($experience['demo_body']);
        foreach ($experience['options'] as $option) {
            expect($main)->toContain($option['label']);
        }
    }
});

it('gives every industry a distinct rendered search title and description', function () {
    $solutions = collect(config('frontsite.solutions'));
    expect($solutions->pluck('seo_title')->unique())->toHaveCount(13);
    expect($solutions->pluck('seo_description')->unique())->toHaveCount(13);
    foreach ($solutions as $slug => $solution) {
        $this->get(route('marketing.solutions.show', $slug))->assertOk()
            ->assertSee(e($solution['seo_title']), false)
            ->assertSee('<meta name="description" content="'.e($solution['seo_description']).'"', false)
            ->assertSee('<link rel="canonical" href="'.route('marketing.solutions.show', $slug).'"', false);
    }
});


it('connects salon purchase evaluation to a real guide and preserves specialist limits', function () {
    config(['inertia.ssr.enabled' => false]);
    $this->get('/solutions/salons')->assertOk()->assertSee('href="'.route('marketing.guides.show', 'salon-software-demo-scorecard').'"', false)->assertInertia(fn (Assert $page) => $page
        ->where('guides.0.href', route('marketing.guides.show', 'salon-software-demo-scorecard')));
    $this->get('/guides/salon-software-demo-scorecard')->assertOk();
    expect(collect(config('frontsite.solutions.salons.faqs'))->pluck('question')->all())
        ->toContain('How should I test salon scheduling software before switching?');
    expect(collect(config('frontsite.solutions.salons.faqs'))->pluck('answer')->implode(' '))
        ->toContain('Online deposit collection, prepaid packages and memberships are unavailable');
});
