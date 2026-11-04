<?php

use App\Domain\Marketing\Services\SafeArticleRenderer;
use App\Models\Article;
use App\Models\User;
use App\Services\OgImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function contentRenderingArticle(array $overrides = []): Article
{
    return Article::create(array_merge([
        'user_id' => User::factory()->create(['name' => 'Article editor'])->id,
        'title' => 'Prepare a working day', 'content' => '<p>Start with the appointment diary.</p>',
        'excerpt' => 'Practical preparation for the working day.', 'topic' => 'Operations',
        'thumbnail' => 'articles/working-day.png', 'seo_title' => 'Prepare a working day',
        'seo_description' => 'Practical preparation for an appointment-led working day.',
        'active' => true, 'status' => 'published', 'content_owner' => 'Editorial team',
        'published_at' => now()->subDay(), 'reviewed_at' => now()->subDay(),
    ], $overrides));
}

it('preserves rich-editor content and tables while keeping article headings below the page title', function () {
    $html = app(SafeArticleRenderer::class)->render(
        '<h1 id="opening">Opening checklist</h1><h2>Prepare</h2>'
        .'<p><strong>Keep</strong> <em>your diary</em> <u>ready</u> and <s>avoid duplication</s>.</p>'
        .'<ul><li>Check appointments</li></ul><blockquote>Plan the day.</blockquote>'
        .'<p><a href="/features/online-booking">Online booking</a></p>'
        .'<table><tbody><tr><th>Time</th><td colspan="2">Morning</td></tr></tbody></table>'
        .'<img src="/storage/articles/reception.png" alt="Reception" width="1200" height="675">'
    );

    expect($html)->toContain('<h2 id="opening">Opening checklist</h2>', '<h3>Prepare</h3>',
        '<strong>Keep</strong>', '<em>your diary</em>', '<u>ready</u>', '<s>avoid duplication</s>',
        '<ul><li>Check appointments</li></ul>', '<blockquote>Plan the day.</blockquote>',
        'href="/features/online-booking"', '<table>', '<th>Time</th>', '<td colspan="2">Morning</td>',
        'src="/storage/articles/reception.png"', 'alt="Reception"')
        ->not->toContain('<h1');
});

it('continues rendering Markdown including escaped code without treating it as executable markup', function () {
    $html = app(SafeArticleRenderer::class)->render("# Working day\n\n## Appointments\n\n**Start here** with [booking](/features/online-booking).\n\n![Reception](/storage/articles/reception.png)\n\n`<script>alert(1)</script>`");

    expect($html)->toContain('<h2>Working day</h2>', '<h3>Appointments</h3>', '<strong>Start here</strong>',
        'href="/features/online-booking"', 'src="/storage/articles/reception.png"',
        '<code>&lt;script&gt;alert(1)&lt;/script&gt;</code>')
        ->not->toContain('<script>');
});

it('sanitizes rich-editor HTML against executable elements URLs events and CSS', function (string $unsafe) {
    $html = app(SafeArticleRenderer::class)->render('<p>Keep this text.</p>'.$unsafe);

    expect($html)->toContain('Keep this text.')
        ->not->toMatch('/<\s*(?:script|iframe|object|embed|svg|math|style|form)\b/i')
        ->not->toMatch('/\s(?:on\w+|style)\s*=/i')
        ->not->toMatch('/(?:javascript|vbscript|data):/i');
})->with([
    '<script>alert(document.cookie)</script><iframe src="https://example.test"></iframe>',
    '<img src="/storage/articles/photo.png" onerror="alert(1)"><p onclick="alert(2)">Text</p>',
    '<a href="javascript:alert(1)">Link</a><a href="jav&#x61;script:alert(2)">Encoded</a>',
    '<img src="data:image/svg+xml;base64,PHN2ZyBvbmxvYWQ9YWxlcnQoMSk+"><img src="javascript:alert(3)">',
    '<svg onload="alert(1)"><a href="javascript:alert(2)">SVG</a></svg><math><mtext>Math</mtext></math>',
    '<p style="position:fixed;inset:0;z-index:999999;background:url(javascript:alert(1))">Overlay</p>',
    '<object data="https://example.test"><embed src="https://example.test"></object><form action="https://example.test"><input name="secret"></form>',
]);

it('uses the configured public article disk for thumbnails and public social metadata', function () {
    config(['filesystems.default' => 'local', 'articles.disk' => 'public']);
    Storage::fake('local');
    Storage::fake('public');
    Storage::disk('public')->put('articles/working-day.png', 'Synthetic image');
    $article = contentRenderingArticle();
    $imageUrl = Storage::disk('public')->url('articles/working-day.png');

    expect($article->icon)->toBe($imageUrl);
    $this->get(route('blog.article', $article))->assertOk()->assertInertia(fn ($page) => $page
        ->where('article.thumbnail', $imageUrl)
        ->where('article.html', fn (string $html) => str_contains($html, '<p>Start with the appointment diary.</p>'))
        ->where('seo.image', url($imageUrl)));
    Storage::disk('public')->assertExists('articles/working-day.png');
    Storage::disk('local')->assertMissing('articles/working-day.png');
});

it('saves and updates articles without generating unused browser screenshots', function () {
    $this->mock(OgImageService::class)->shouldNotReceive('saveImage');
    $article = contentRenderingArticle(['status' => 'draft']);
    $article->update(['title' => 'Review the working day']);

    expect($article->fresh()->title)->toBe('Review the working day')
        ->and($article->fresh()->status)->toBe('draft');
});

it('deletes only unshared managed thumbnails from the article disk', function () {
    config(['filesystems.default' => 'local', 'articles.disk' => 'public']);
    Storage::fake('local');
    Storage::fake('public');
    Storage::disk('public')->put('articles/working-day.png', 'Shared image');
    Storage::disk('local')->put('articles/working-day.png', 'Unrelated private file');
    $first = contentRenderingArticle();
    $second = contentRenderingArticle(['title' => 'Another working day']);

    $first->delete();
    Storage::disk('public')->assertExists('articles/working-day.png');
    $second->delete();
    Storage::disk('public')->assertMissing('articles/working-day.png');
    Storage::disk('local')->assertExists('articles/working-day.png');
});

it('does not delete unrelated or traversing storage paths from article records', function (string $path) {
    config(['articles.disk' => 'public']);
    Storage::fake('public');
    Storage::disk('public')->put('client-files/protected.png', 'Unrelated image');
    contentRenderingArticle(['thumbnail' => $path])->delete();

    Storage::disk('public')->assertExists('client-files/protected.png');
})->with(['client-files/protected.png', 'articles/../client-files/protected.png']);
