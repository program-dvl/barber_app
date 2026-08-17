<?php

use App\Domain\Marketing\Services\SafeArticleRenderer;
use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('publishes a focused resources hub and two maintained guides', function () {
    $this->get(route('marketing.resources'))->assertOk()->assertInertia(fn (Assert $page) => $page->has('guides', 2)->where('articleCount', 0));
    $this->get(route('marketing.guides.show', 'booking-policy-basics'))->assertOk()->assertInertia(fn (Assert $page) => $page->has('guide.sections', 4)->has('guide.checklist', 7));
    $this->get('/guides/instant-growth-hacks')->assertNotFound();
});

it('keeps legacy active articles quarantined until every publication gate passes', function () {
    $article = editorialArticle(['status' => 'draft', 'active' => true]);
    $this->get(route('blog.index'))->assertInertia(fn (Assert $page) => $page->has('articles.data', 0));
    $this->get(route('blog.article', $article))->assertNotFound();
});

it('publishes reviewed article metadata and sanitized markdown without stored xss', function () {
    $article = editorialArticle(['content' => "## Safe heading\n\nUseful [link](https://example.com).\n\n<script>alert('xss')</script>\n\n<img src=x onerror=alert(1)>\n\n[bad](javascript:alert(2))"]);
    $expectedUrl = route('blog.article', $article);

    $this->get($expectedUrl)->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Article')->where('article.slug', $article->slug)
        ->where('article.html', fn (string $html) => str_contains($html, '<h2>Safe heading</h2>') && ! str_contains($html, '<script') && ! str_contains($html, 'onerror') && ! str_contains($html, 'javascript:'))
        ->where('seo.canonical', $expectedUrl));
});

it('strips unsafe html and unsafe url protocols in the renderer', function () {
    $html = app(SafeArticleRenderer::class)->render("<iframe src='https://bad.test'></iframe>\n\n[click](javascript:alert(1))");
    expect($html)->not->toContain('iframe')->not->toContain('javascript:');
});

function editorialArticle(array $overrides = []): Article
{
    $user = User::factory()->create(['name' => 'Approved Editor']);

    return Article::withoutEvents(fn () => Article::query()->create(array_merge([
        'user_id' => $user->id, 'title' => 'A reviewed operating note', 'slug' => 'reviewed-operating-note',
        'content' => 'Useful reviewed content.', 'excerpt' => 'A concise reviewed answer for salon operators.', 'topic' => 'Operations',
        'thumbnail' => 'articles/synthetic.webp', 'seo_title' => 'A reviewed operating note', 'seo_description' => 'A reviewed Good Hours operating article.',
        'active' => true, 'status' => 'published', 'content_owner' => 'Good Hours Product', 'published_at' => now()->subDay(), 'reviewed_at' => now()->subDay(),
    ], $overrides)));
}
