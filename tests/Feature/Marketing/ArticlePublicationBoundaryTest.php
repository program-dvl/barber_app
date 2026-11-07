<?php

use App\Domain\Marketing\Services\ArticlePublication;
use App\Domain\PlatformAccess\Enums\PlatformRole;
use App\Domain\PlatformAccess\Models\PlatformRoleAssignment;
use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Models\Article;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function publicationBoundaryFields(array $overrides = []): array
{
    return array_merge([
        'title' => 'Prepare a working day', 'content' => '<p>Review the diary.</p>',
        'excerpt' => 'Practical guidance for the working day.', 'topic' => 'Operations',
        'thumbnail' => 'articles/reception.png', 'seo_title' => 'Prepare a working day',
        'seo_description' => 'Practical guidance for the working day.', 'active' => true,
        'status' => 'published', 'content_owner' => 'Editorial team',
        'reviewed_at' => now()->subMinute()->format('Y-m-d H:i:s'),
        'published_at' => now()->subMinute()->format('Y-m-d H:i:s'),
    ], $overrides);
}

beforeEach(function () {
    config(['app.url' => 'https://clipperdesk.com', 'brand.website_url' => 'https://clipperdesk.com',
        'booking.host' => 'book.clipperdesk.com', 'articles.disk' => 'public']);
    $this->articleReviewer = User::factory()->create(['name' => 'Boundary administrator']);
    PlatformRoleAssignment::create(['user_id' => $this->articleReviewer->id, 'role' => PlatformRole::Administrator,
        'reason' => 'Check the article publication boundary.']);
});

it('reports blocked markers in raw stored attributes as well as decoded visible text', function (string $content) {
    try {
        app(ArticlePublication::class)->prepare(publicationBoundaryFields(['content' => $content]), $this->articleReviewer);
        $this->fail('A query-blocked article must not be saved as published.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('data.content');
    }
})->with([
    '<p>Useful advice.</p><a href="https://example.com/open-house">More</a>',
    '<p data-note="TODO">Useful advice.</p>',
    '<p>Implementation notes for the team.</p>',
    '<p>&#65;DR-065 needs review.</p>',
]);

it('rejects private and temporary URLs in HTML and Markdown before publication', function (string $content) {
    expect(fn () => app(ArticlePublication::class)->prepare(publicationBoundaryFields(['content' => $content]), $this->articleReviewer))
        ->toThrow(ValidationException::class);
})->with([
    '<p>Read this.</p><a href="/appointments/secure/abc123">Appointment</a>',
    'Read the [client file](/client-files/abc123).',
    '<p>Read this.</p><a href="https://www.clipperdesk.com/client-forms/secure/abc123">Form</a>',
    '<p>Read this.</p><a href="https://book.clipperdesk.com/waitlist-offers/abc123">Offer</a>',
    'Use [your appointment](../manage-appointment?token=abc123).',
    '<p>Read this.</p><a href="/communications/actions/abc123">Message choice</a>',
    '<p>Read this.</p><a href="/s/AbCdEfGhIjKlMnOpQrStUv">Appointment</a>',
    '<p>Read this.</p><a href="/staff-invitations/abc123">Join team</a>',
    '<p>Private preview.</p><img src="/livewire/preview-file/photo.png?expires=123">',
    '<p>Private preview.</p><img src="/storage/private/client.png">',
    '<p>Private preview.</p><img src="/storage/livewire-tmp/photo.png">',
    '<p>Private record.</p><a href="/businesses/workspace/clients/123">Client</a>',
    '<p>Private record.</p><a href="/features/../client-files/abc123">Client file</a>',
    '<p>Private record.</p><a href="/%2563lient-files/abc123">Client file</a>',
    '<p>Preview image.</p><img src="https://images.example.test/photo.png?X-Amz-Signature=abc123">',
    '<p>Preview image.</p><img src="https://images.example.test/photo.png?X-Goog-Signature=abc123">',
    '<p>Preview image.</p><img src="https://clipperdesk.com/storage/articles/photo.png?expires=123&amp;signature=abc123">',
    '<p>Preview image.</p><img srcset="/storage/articles/photo.png 1x, /client-files/abc123 2x">',
]);

it('allows reviewed public links images and legacy Markdown', function () {
    $content = "## Prepare the day\n\nRead [our guide](/blog/prepare-the-day) and [booking help](https://clipperdesk.com/help/articles/appointments-add).\n\n![Reception](/storage/articles/reception.png)\n\n<a href=\"https://example.com/client-files-public-guide\">External guide</a>";
    $data = app(ArticlePublication::class)->prepare(publicationBoundaryFields(['content' => $content]), $this->articleReviewer);

    expect($data['content'])->toBe($content)->and($data['status'])->toBe('published');
});

it('retains unfinished private links in a saved draft without making it public', function () {
    $content = '<p>Draft notes.</p><a href="/client-files/abc123">Replace before publishing</a>';
    $data = app(ArticlePublication::class)->prepare(publicationBoundaryFields(['status' => 'draft', 'content' => $content]), $this->articleReviewer);

    expect($data['content'])->toBe($content)->and($data['status'])->toBe('draft');
});

it('requires visible article text after stripping unsafe HTML and blank entities', function (string $content) {
    expect(fn () => app(ArticlePublication::class)->prepare(publicationBoundaryFields(['content' => $content]), $this->articleReviewer))
        ->toThrow(ValidationException::class);
})->with(['<p>&nbsp; &#160; </p>', '<script>alert(1)</script>', '<p><img src="/storage/articles/reception.png"></p>']);

it('rejects content beyond the editor limit instead of silently truncating a public article', function () {
    try {
        app(ArticlePublication::class)->prepare(publicationBoundaryFields(['content' => str_repeat('a', 100001)]), $this->articleReviewer);
        $this->fail('Oversized content must be rejected.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('data.content');
    }
});

it('preserves bounded multibyte content without mistaking bytes for the editor character count', function () {
    $content = str_repeat('😀', 99000)."\n\nFinal sentence.";
    expect(mb_strlen($content))->toBeLessThanOrEqual(100000);
    $data = app(ArticlePublication::class)->prepare(publicationBoundaryFields(['content' => $content]), $this->articleReviewer);
    expect($data['content'])->toBe($content);
});

it('keeps a saved draft and entered work when a private link blocks publication', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Storage::fake('public');
    Storage::disk('public')->put('articles/reception.png', UploadedFile::fake()->image('reception.png')->get());
    $editor = User::factory()->create(['name' => 'Boundary reviewer']);
    PlatformRoleAssignment::create(['user_id' => $editor->id, 'role' => PlatformRole::Administrator,
        'reason' => 'Check the article publication boundary.']);
    $article = Article::create([...publicationBoundaryFields(['status' => 'draft', 'content' => '<p>Saved draft text.</p>']),
        'user_id' => $editor->id]);
    $content = '<p>Entered work remains.</p><a href="/client-files/abc123">Replace link</a>';
    $fields = publicationBoundaryFields(['content' => $content]);
    unset($fields['thumbnail']);

    $this->actingAs($editor);
    Livewire::test(EditArticle::class, ['record' => $article->id])
        ->fillForm($fields)
        ->call('save')->assertHasFormErrors(['content'])
        ->assertSet('data.content', fn ($actual) => str_contains(json_encode($actual), 'Entered work remains.'));

    expect($article->fresh()->status)->toBe('draft')->and($article->fresh()->content)->toBe('<p>Saved draft text.</p>');
    $this->get(route('blog.article', $article))->assertNotFound();
});
