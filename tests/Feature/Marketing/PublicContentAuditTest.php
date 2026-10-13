<?php

use App\Domain\Marketing\Services\PublicContentPolicy;
use App\Domain\Marketing\Services\SafeArticleRenderer;
use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('keeps requirement evidence out of every public content payload and metadata', function () {
    foreach (['features' => 'marketing.features.show', 'solutions' => 'marketing.solutions.show', 'use_cases' => 'marketing.use-cases.show', 'guides' => 'marketing.guides.show'] as $family => $route) {
        foreach (array_keys(config('frontsite.'.$family)) as $slug) {
            $response = $this->get(route($route, $slug))->assertOk();
            $props = $response->viewData('page')['props'];
            $content = collect($props)->only(['feature', 'solution', 'useCase', 'guide', 'seo', 'structuredData'])->toJson();
            expect(app(PublicContentPolicy::class)->containsInternalCopy($content))->toBeFalse($route.' '.$slug);
            expect($content)->not->toContain('"requirements":');
        }
    }
    foreach (['/', '/features', '/solutions', '/use-cases', '/pricing', '/company', '/security', '/resources', '/blog', '/terms-of-service', '/refund-policy', '/privacy-policy'] as $path) {
        $response = $this->get($path)->assertOk();
        expect($response->getContent())->not->toMatch('/\b(?:FR|NFR|ADR|OPEN)-\d+/');
    }
});

it('retires boilerplate routes without exposing or changing retained data', function () {
    foreach (['/roadmap', '/changelog', '/coming-soon'] as $path) {
        $this->get($path)->assertStatus(410)->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')->assertDontSee('Larafast');
    }
    $this->post('/coming-soon', ['email' => 'do-not-store@example.test'])->assertStatus(410);
    $this->assertDatabaseCount('coming_soon_emails', 0);
});

it('excludes incomplete or internal articles from all public discovery paths', function (array $override) {
    $author = User::factory()->create(['name' => 'ClipperDesk editor']);
    $article = Article::withoutEvents(fn () => Article::create(array_merge([
        'user_id' => $author->id, 'title' => 'Manage the working day', 'slug' => 'working-day',
        'content' => 'Practical booking guidance.', 'excerpt' => 'Useful booking guidance for your team.', 'topic' => 'Operations',
        'thumbnail' => 'articles/working-day.webp', 'seo_title' => 'Manage the working day', 'seo_description' => 'Practical booking guidance for your service business.',
        'active' => true, 'status' => 'published', 'content_owner' => 'Editorial team', 'published_at' => now()->subDay(), 'reviewed_at' => now()->subDay(),
    ], $override)));
    $this->get(route('blog.article', $article))->assertNotFound();
    $this->get('/blog')->assertInertia(fn ($page) => $page->has('articles.data', 0));
    $this->get('/resources')->assertInertia(fn ($page) => $page->where('articleCount', 0));
    $this->get('/sitemap.xml')->assertDontSee('/blog/working-day');
    expect($article->fresh()->status)->toBe('published');
})->with([
    [['content' => 'Evidence: FR-03 and FR-07']],
    [['excerpt' => 'TODO: write the introduction']],
    [['seo_title' => 'Internal implementation notes']],
    [['seo_description' => 'Lorem ipsum dolor sit amet']],
    [['content' => 'Acceptance criteria for the booking module']],
    [['content_owner' => '  ']],
    [['thumbnail' => '']],
    [['title' => '  ']],
    [['reviewed_at' => now()->addDay()]],
]);

it('keeps author supplied headings below the page title', function () {
    $html = app(SafeArticleRenderer::class)->render("# Article heading\n\n## Section\n\n### Detail");
    expect($html)->not->toContain('<h1>')->toContain('<h2>Article heading</h2>')->toContain('<h3>Section</h3>')->toContain('<h4>Detail</h4>');
});

it('provides one complete canonical metadata set without requiring the SSR service', function () {
    config(['inertia.ssr.enabled' => false]);
    $html = $this->get('/features?utm_source=test')->assertOk()->getContent();
    $doc = new DOMDocument;
    @$doc->loadHTML($html);
    $xpath = new DOMXPath($doc);
    foreach (['//title', '//link[@rel="canonical"]', '//meta[@name="description"]', '//meta[@name="robots"]', '//meta[@property="og:url"]', '//script[@type="application/ld+json"]'] as $query) {
        expect($xpath->query($query))->toHaveCount(1);
    }
    expect($xpath->query('//link[@rel="canonical"]')->item(0)->getAttribute('href'))->toBe(route('marketing.features'));
    expect($xpath->query('//title')->item(0)->textContent)->toBe('Service business management software features | ClipperDesk');
});

it('keeps error metadata fail closed even without route middleware', function () {
    $response = $this->get('/unmapped-page')->assertNotFound()->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
    $seo = $response->viewData('page')['props']['seo'];
    expect($seo['robots'])->toBe('noindex, nofollow, noarchive')
        ->and($seo['title'])->toBe('Page not found | ClipperDesk');
    expect($response->viewData('page')['props']['structuredData'])->toBe([]);
    $error = $this->get('/features/unapproved-feature')->assertNotFound();
    expect($error->viewData('page')['props']['structuredData'])->toBe([]);
});

it('rejects internal and placeholder copy in the active public templates including collapsed FAQs', function () {
    $files = [
        resource_path('js/Pages/Home.vue'), resource_path('js/Pages/Blog.vue'), resource_path('js/Pages/Article.vue'),
        resource_path('js/Components/Marketing/MarketingFooter.vue'), resource_path('js/Components/Marketing/MarketingHeader.vue'),
        ...glob(resource_path('js/Pages/Marketing/*.vue')),
        ...glob(resource_path('js/Pages/Marketing/*/*.vue')),
    ];
    foreach ($files as $file) {
        expect(app(PublicContentPolicy::class)->containsInternalCopy(file_get_contents($file)))->toBeFalse(basename($file));
    }
});

it('uses the configured primary production host for canonicals sitemap and schema', function () {
    $this->app->detectEnvironment(fn () => 'production');
    config(['brand.website_url' => 'https://clipperdesk.com']);
    $response = $this->get('https://www.clipperdesk.com/features?utm_source=alias')->assertOk();
    $props = $response->viewData('page')['props'];
    expect($props['seo']['canonical'])->toBe('https://clipperdesk.com/features');
    expect(collect($props['structuredData']['@graph'])->firstWhere('@type', 'WebSite')['url'])->toBe('https://clipperdesk.com');
    $this->get('https://www.clipperdesk.com/sitemap.xml')->assertOk()->assertSee('https://clipperdesk.com/features', false)->assertDontSee('https://www.clipperdesk.com');
    $this->get('https://www.clipperdesk.com/robots.txt')->assertOk()->assertSee('Sitemap: https://clipperdesk.com/sitemap.xml', false);
});

it('keeps legal trial descriptions aligned with current subscription configuration', function () {
    config(['billing.trial_days' => 21]);
    foreach (['/terms-of-service' => 'terms', '/refund-policy' => 'refund'] as $path => $prop) {
        $response = $this->get($path)->assertOk();
        $html = $response->viewData('page')['props'][$prop];
        expect($html)->toContain('21-day trial')->not->toContain('{{trial_days}}');
    }
});
