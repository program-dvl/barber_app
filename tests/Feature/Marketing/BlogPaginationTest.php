<?php

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function paginationArticle(User $author, int $number, array $overrides = []): Article
{
    return Article::withoutEvents(fn () => Article::query()->create(array_merge([
        'user_id' => $author->id,
        'title' => 'Reception planning article '.$number,
        'slug' => 'reception-planning-'.$number,
        'content' => 'Review the appointments and staff working hours before opening.',
        'excerpt' => 'A practical note for preparing the reception desk.',
        'topic' => 'Daily operations',
        'thumbnail' => 'articles/reception.webp',
        'seo_title' => 'Reception planning article '.$number,
        'seo_description' => 'Practical appointment and reception planning for service businesses.',
        'active' => true,
        'status' => 'published',
        'content_owner' => 'Publication reviewer',
        'published_at' => now()->subDay()->startOfSecond(),
        'reviewed_at' => now()->subDay()->startOfSecond(),
    ], $overrides)));
}

it('keeps all eligible articles reachable in stable pages without leaking unpublished records', function () {
    $author = User::factory()->create(['name' => 'Publication author']);
    $articles = collect(range(1, 25))->map(fn ($number) => paginationArticle($author, $number));
    paginationArticle($author, 26, ['status' => 'draft']);
    paginationArticle($author, 27, ['active' => false]);
    paginationArticle($author, 28, ['published_at' => now()->addDay()]);
    paginationArticle($author, 29, ['reviewed_at' => null]);
    paginationArticle($author, 30, ['content' => 'Internal ADR-024 reference.']);
    $orderedIds = $articles->reverse()->pluck('id')->values();

    foreach ([1, 2, 3] as $number) {
        $expectedIds = $orderedIds->slice(($number - 1) * 12, 12)->values()->all();
        $url = route('blog.index').($number > 1 ? '?page='.$number : '');
        $this->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Blog')->has('articles.data', count($expectedIds))
            ->where('articles.data', fn ($rows) => collect($rows)->pluck('id')->all() === $expectedIds)
            ->where('articles.current_page', $number)->where('articles.last_page', 3)
            ->where('articles.prev_page_url', $number === 1 ? null : route('blog.index').($number === 2 ? '' : '?page='.($number - 1)))
            ->where('articles.next_page_url', $number === 3 ? null : route('blog.index').'?page='.($number + 1))
            ->where('seo.canonical', $url));
    }
    $this->get(route('blog.index').'?page=4')->assertNotFound()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
});

it('gives later production blog pages their own primary-host canonical schema and links', function () {
    $author = User::factory()->create(['name' => 'Publication author']);
    foreach (range(1, 25) as $number) {
        paginationArticle($author, $number);
    }
    $previousEnvironment = app()->environment();
    app()->instance('env', 'production');
    config(['brand.website_url' => 'https://clipperdesk.com']);

    try {
        $response = $this->get('https://clipperdesk.com/blog?page=2&utm_source=example&email=private%40example.test');
        $response->assertOk()->assertHeader('X-Robots-Tag', 'index, follow, max-image-preview:large')
            ->assertInertia(fn (Assert $page) => $page
                ->where('seo.canonical', 'https://clipperdesk.com/blog?page=2')
                ->where('seo.title', 'Service business operations blog: page 2 | ClipperDesk')
                ->where('articles.prev_page_url', 'https://clipperdesk.com/blog')
                ->where('articles.next_page_url', 'https://clipperdesk.com/blog?page=3')
                ->where('structuredData.@graph.2.url', 'https://clipperdesk.com/blog?page=2'));
        $response->assertSee('<link rel="canonical" href="https://clipperdesk.com/blog?page=2"', false)
            ->assertDontSee('private@example.test', false);
        $this->get('https://clipperdesk.com/blog?page=1&utm_source=example')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('seo.canonical', 'https://clipperdesk.com/blog'));
    } finally {
        app()->instance('env', $previousEnvironment);
    }
});

it('keeps the empty first page useful without inventing later archive pages', function () {
    $this->get(route('blog.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('articles.data', 0)->where('articles.current_page', 1)->where('articles.last_page', 1)
        ->where('articles.prev_page_url', null)->where('articles.next_page_url', null));
    $this->get(route('blog.index').'?page=2')->assertNotFound();
});

it('rejects invalid archive page input as a non-indexable missing page', function (string $query) {
    $this->get(route('blog.index').'?'.$query)->assertNotFound()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
})->with(['page=0', 'page=-1', 'page=abc', 'page=1.5', 'page=01', 'page=', 'page[]=2', 'page=99999999999999999999999']);
