<?php

use App\Domain\Marketing\Services\ArticlePublication;
use App\Domain\PlatformAccess\Enums\PlatformRole;
use App\Domain\PlatformAccess\Models\PlatformRoleAssignment;
use App\Filament\Resources\Articles\Pages\CreateArticle;
use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Models\Article;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

function articleWorkflowAdministrator(): User
{
    $user = User::factory()->create(['name' => 'Article workflow editor']);
    PlatformRoleAssignment::create([
        'user_id' => $user->id, 'role' => PlatformRole::Administrator,
        'reason' => 'Review the synthetic article publishing workflow.',
    ]);

    return $user;
}

function articleWorkflowFields(array $overrides = []): array
{
    return array_merge([
        'title' => 'Prepare the reception desk',
        'content' => '<h2>Check the diary</h2><p>Review the appointments before the working day begins.</p>',
        'excerpt' => 'A practical guide to preparing the reception desk for the working day.',
        'topic' => 'Daily operations',
        'seo_title' => 'Prepare the reception desk',
        'seo_description' => 'Prepare appointments and arrival information before the working day begins.',
        'active' => true, 'status' => 'published',
    ], $overrides);
}

/** A real, decodable 2.3 MiB PNG, rather than a small image with a fake size. */
function articleWorkflowLargePng(): UploadedFile
{
    $chunk = fn (string $type, string $data): string => pack('N', strlen($data)).$type.$data.hash('crc32b', $type.$data, true);
    $rows = '';
    for ($row = 0; $row < 768; $row++) {
        $rows .= "\0".random_bytes(1024 * 3);
    }
    $png = "\x89PNG\r\n\x1a\n"
        .$chunk('IHDR', pack('NNCCCCC', 1024, 768, 8, 2, 0, 0, 0))
        .$chunk('IDAT', gzcompress($rows))
        .$chunk('IEND', '');

    return UploadedFile::fake()->createWithContent('reception.png', $png);
}

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    config(['filesystems.default' => 'local', 'articles.disk' => 'public']);
    Storage::fake('local');
    Storage::fake('private');
    Storage::fake('public');
});

it('saves an unfinished draft without a featured image or search details', function () {
    $editor = articleWorkflowAdministrator();
    $this->actingAs($editor);

    Livewire::test(CreateArticle::class)
        ->fillForm(['title' => 'An unfinished reception note', 'content' => '<p>Work in progress.</p>'])
        ->call('create')->assertHasNoFormErrors();

    $article = Article::sole();
    expect($article->user_id)->toBe($editor->id)
        ->and($article->status)->toBe('draft')
        ->and($article->published_at)->toBeNull()
        ->and($article->reviewed_at)->toBeNull()
        ->and($article->content_owner)->toBeNull()
        ->and($article->thumbnail)->toBe('')
        ->and($article->icon)->toBe('');
    $this->get(route('blog.article', $article))->assertNotFound();
    $this->get('/sitemap.xml')->assertDontSee('/blog/'.$article->slug);
});

it('stores a real image larger than two MiB publicly and retains it when editing text', function () {
    $this->actingAs(articleWorkflowAdministrator());
    $image = articleWorkflowLargePng();
    expect($image->getSize())->toBeGreaterThan(2 * 1024 * 1024)
        ->and(getimagesize($image->getRealPath())[0])->toBe(1024);

    Livewire::test(CreateArticle::class)
        ->fillForm(['title' => 'A reception draft', 'content' => '<p>Keep the diary ready.</p>', 'thumbnail' => $image])
        ->call('create')->assertHasNoFormErrors();

    $article = Article::sole();
    $path = $article->thumbnail;
    expect($path)->toStartWith('articles/')->toEndWith('.png')
        ->and($article->icon)->toBe(Storage::disk('public')->url($path));
    Storage::disk('public')->assertExists($path);
    Storage::disk('local')->assertMissing($path);
    Storage::disk('private')->assertMissing($path);

    Livewire::test(EditArticle::class, ['record' => $article->id])
        ->fillForm(['title' => 'A revised reception draft', 'content' => '<p>Review tomorrow’s diary.</p>'])
        ->call('save')->assertHasNoFormErrors();

    expect($article->fresh()->thumbnail)->toBe($path)
        ->and($article->fresh()->content)->toBe('<p>Review tomorrow’s diary.</p>')
        ->and($article->fresh()->materially_updated_at)->not->toBeNull();
    Storage::disk('public')->assertExists($path);
});

it('publishes a complete reviewed article through the real admin form to the blog and sitemap', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-04T16:27:34.627915+05:30'));
    $editor = articleWorkflowAdministrator();
    $this->actingAs($editor);
    Livewire::test(CreateArticle::class)
        ->fillForm([...articleWorkflowFields(), 'thumbnail' => UploadedFile::fake()->image('reception.png', 800, 450)])
        ->call('create')->assertHasNoFormErrors();

    $article = Article::sole();
    expect(Article::publishable()->whereKey($article)->exists())->toBeTrue()
        ->and($article->content_owner)->toBe($editor->name)
        ->and($article->published_at->toDateTimeString())->toBe('2026-11-04 10:57:34')
        ->and($article->reviewed_at->equalTo($article->published_at))->toBeTrue();
    $this->get(route('blog.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('articles.data', 1)->where('articles.data.0.slug', $article->slug)
        ->where('articles.data.0.thumbnail', Storage::disk('public')->url($article->thumbnail)));
    $this->get(route('blog.article', $article))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('article.html', fn (string $html) => str_contains($html, 'Review the appointments') && str_contains($html, '<h2>Check the diary</h2>'))
        ->where('seo.description', $article->seo_description));
    $this->get('/sitemap.xml')->assertOk()->assertSee('/blog/'.$article->slug);
});

it('publishes an old future schedule now when the administrator explicitly saves Published', function () {
    $editor = articleWorkflowAdministrator();
    $this->actingAs($editor);
    Storage::disk('public')->put('articles/scheduled.png', UploadedFile::fake()->image('scheduled.png')->get());
    $article = Article::create([...articleWorkflowFields(), 'user_id' => $editor->id,
        'thumbnail' => 'articles/scheduled.png', 'content_owner' => 'Previous reviewer',
        'reviewed_at' => now()->subDay(), 'published_at' => now()->addDay()]);
    $this->get(route('blog.index'))->assertInertia(fn (Assert $page) => $page->has('articles.data', 0));
    $this->get(route('blog.article', $article))->assertNotFound();
    $this->get('/sitemap.xml')->assertDontSee('/blog/'.$article->slug);
    Livewire::test(EditArticle::class, ['record' => $article->id])
        ->call('save')->assertHasNoFormErrors();
    $article->refresh();
    expect($article->published_at->toDateTimeString())->toBe(now('UTC')->toDateTimeString())
        ->and($article->reviewed_at->equalTo($article->published_at))->toBeTrue()
        ->and($article->content_owner)->toBe($editor->name);
    $this->get(route('blog.article', $article))->assertOk();
    $this->get('/sitemap.xml')->assertSee('/blog/'.$article->slug);
});

it('reports missing publication details without creating an incomplete published article', function () {
    $this->actingAs(articleWorkflowAdministrator());
    Livewire::test(CreateArticle::class)
        ->fillForm(['title' => 'A publication to review', 'content' => '<p>Practical advice.</p>', 'status' => 'published'])
        ->call('create')->assertHasFormErrors(['thumbnail', 'excerpt', 'topic', 'seo_title', 'seo_description']);
    expect(Article::count())->toBe(0);
});

it('enforces the stored summary and topic limits in the admin form', function (string $field, int $length) {
    $this->actingAs(articleWorkflowAdministrator());
    Livewire::test(CreateArticle::class)
        ->fillForm(['title' => 'A draft within storage limits', 'content' => '<p>Practical advice.</p>', $field => str_repeat('a', $length)])
        ->call('create')->assertHasFormErrors([$field => 'max']);
    expect(Article::count())->toBe(0);
})->with([['excerpt', 321], ['topic', 65]]);

it('rejects featured SVG and oversized image uploads without saving an article', function (string $kind) {
    $this->actingAs(articleWorkflowAdministrator());
    $image = $kind === 'svg'
        ? UploadedFile::fake()->createWithContent('unsafe.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')
        : UploadedFile::fake()->image('oversized.png', 800, 450)->size(5121);
    Livewire::test(CreateArticle::class)
        ->fillForm(['title' => 'A draft image to check', 'content' => '<p>Practical advice.</p>', 'thumbnail' => $image])
        ->call('create')->assertHasFormErrors(['thumbnail']);
    expect(Article::count())->toBe(0);
    expect(Storage::disk('public')->allFiles('articles'))->toBe([]);
})->with(['svg', 'oversized']);

it('rejects a featured image path copied from another article while preserving both files', function () {
    $editor = articleWorkflowAdministrator();
    $this->actingAs($editor);
    Storage::disk('public')->put('articles/original.png', UploadedFile::fake()->image('original.png')->get());
    Storage::disk('public')->put('articles/other.png', UploadedFile::fake()->image('other.png')->get());
    $article = Article::create(['user_id' => $editor->id, 'title' => 'Original article', 'content' => '<p>Keep this text.</p>', 'thumbnail' => 'articles/original.png', 'status' => 'draft']);

    Livewire::test(EditArticle::class, ['record' => $article->id])
        ->fillForm(['thumbnail' => ['untrusted' => 'articles/other.png']])
        ->call('save')->assertHasFormErrors(['thumbnail']);

    expect($article->fresh()->thumbnail)->toBe('articles/original.png');
    Storage::disk('public')->assertExists(['articles/original.png', 'articles/other.png']);
});

it('returns a useful publication error for unfinished internal content and preserves the draft', function () {
    $editor = articleWorkflowAdministrator();
    $this->actingAs($editor);
    Storage::disk('public')->put('articles/review.png', UploadedFile::fake()->image('review.png')->get());
    $article = Article::create(['user_id' => $editor->id, 'title' => 'Article awaiting review', 'content' => '<p>Draft text.</p>', 'thumbnail' => 'articles/review.png', 'status' => 'draft']);

    Livewire::test(EditArticle::class, ['record' => $article->id])
        ->fillForm(articleWorkflowFields(['content' => '<p>Implementation notes: ADR-065 needs review.</p>']))
        ->call('save')->assertHasFormErrors(['content']);

    expect($article->fresh()->status)->toBe('draft')->and($article->fresh()->content)->toBe('<p>Draft text.</p>');
    $this->get(route('blog.article', $article))->assertNotFound();
});

it('denies article administration to ordinary support and auditor users', function (string $role) {
    $user = User::factory()->create();
    if ($role !== 'ordinary') {
        PlatformRoleAssignment::create(['user_id' => $user->id,
            'role' => $role === 'support' ? PlatformRole::SupportOperator : PlatformRole::SecurityAuditor,
            'reason' => 'Synthetic non-editor access check.']);
    }
    $this->actingAs($user)->get('/admin/articles')->assertForbidden();
    $this->get('/admin/articles/create')->assertForbidden();
    expect(Article::count())->toBe(0);
})->with(['ordinary', 'support', 'auditor']);

it('keeps entered text after a transport upload failure and allows saving the draft', function () {
    $this->actingAs(articleWorkflowAdministrator());
    Livewire::test(CreateArticle::class)
        ->fillForm(['title' => 'Keep this article', 'content' => '<p>This work must stay available.</p>'])
        ->call('_uploadErrored', 'data.thumbnail.2b8a94d0', null, false)
        ->assertHasFormErrors(['thumbnail'])
        ->assertSet('data.title', 'Keep this article')
        ->call('create')->assertHasNoFormErrors();
    expect(Article::sole()->content)->toBe('<p>This work must stay available.</p>');
});

it('offers only Status in the article section without manual reviewer or date fields', function () {
    $this->actingAs(articleWorkflowAdministrator());
    Livewire::test(CreateArticle::class)
        ->assertFormFieldExists('status', fn (Select $field) => $field->getLabel() === 'Status'
            && $field->getOptions() === ['draft' => 'Draft', 'published' => 'Published'])
        ->assertFormFieldDoesNotExist('content_owner')
        ->assertFormFieldDoesNotExist('reviewed_at')
        ->assertFormFieldDoesNotExist('published_at');
});

it('publishes a saved draft with the current reviewer and keeps its first publication time through edits', function () {
    $original = articleWorkflowAdministrator();
    $this->actingAs($original);
    Livewire::test(CreateArticle::class)
        ->fillForm([...articleWorkflowFields(['status' => 'draft']),
            'thumbnail' => UploadedFile::fake()->image('reception.png')])
        ->call('create')->assertHasNoFormErrors();
    $article = Article::sole();
    expect($article->published_at)->toBeNull()->and($article->reviewed_at)->toBeNull();

    $reviewer = articleWorkflowAdministrator();
    $reviewer->update(['name' => 'The publishing administrator']);
    $this->actingAs($reviewer);
    $this->travel(1)->hour();
    Livewire::test(EditArticle::class, ['record' => $article->id])
        ->fillForm(['status' => 'published'])->call('save')->assertHasNoFormErrors();
    $article->refresh();
    $firstPublishedAt = $article->published_at;
    $firstReviewedAt = $article->reviewed_at;
    expect($article->user_id)->toBe($original->id)
        ->and($article->content_owner)->toBe($reviewer->name)
        ->and($firstPublishedAt->toDateTimeString())->toBe(now('UTC')->toDateTimeString());

    $this->travel(1)->hour();
    Livewire::test(EditArticle::class, ['record' => $article->id])
        ->call('save')->assertHasNoFormErrors();
    expect($article->fresh()->published_at->equalTo($firstPublishedAt))->toBeTrue()
        ->and($article->fresh()->reviewed_at->equalTo($firstReviewedAt))->toBeTrue();

    $this->actingAs($original);
    $this->travel(1)->hour();
    Livewire::test(EditArticle::class, ['record' => $article->id])
        ->fillForm(['content' => '<p>The revised working day guidance.</p>'])
        ->call('save')->assertHasNoFormErrors();
    expect($article->fresh()->published_at->equalTo($firstPublishedAt))->toBeTrue()
        ->and($article->fresh()->reviewed_at->toDateTimeString())->toBe(now('UTC')->toDateTimeString())
        ->and($article->fresh()->materially_updated_at->equalTo($article->fresh()->reviewed_at))->toBeTrue()
        ->and($article->fresh()->content_owner)->toBe($original->name);

    $this->travel(1)->hour();
    Livewire::test(EditArticle::class, ['record' => $article->id])
        ->fillForm(['status' => 'draft', 'active' => true])->call('save')->assertHasNoFormErrors();
    $this->get(route('blog.article', $article))->assertNotFound();
    expect($article->fresh()->published_at->equalTo($firstPublishedAt))->toBeTrue();
    $this->travel(1)->hour();
    Livewire::test(EditArticle::class, ['record' => $article->id])
        ->fillForm(['status' => 'published'])->call('save')->assertHasNoFormErrors();
    expect($article->fresh()->published_at->equalTo($firstPublishedAt))->toBeTrue()
        ->and($article->fresh()->reviewed_at->toDateTimeString())->toBe(now('UTC')->toDateTimeString());
});

it('derives publication history from the administrator and clock instead of supplied hidden state', function () {
    $editor = articleWorkflowAdministrator();
    $this->travelTo(CarbonImmutable::parse('2026-11-04T18:47:29.843201+05:30'));
    $data = app(ArticlePublication::class)->prepare([...articleWorkflowFields(),
        'thumbnail' => 'articles/reception.png', 'content_owner' => 'An invented reviewer',
        'reviewed_at' => '2099-01-01', 'published_at' => '2000-01-01',
        'materially_updated_at' => '2099-01-01'], $editor);

    expect($data['content_owner'])->toBe($editor->name)
        ->and($data['published_at']->toDateTimeString())->toBe('2026-11-04 13:17:29')
        ->and($data['reviewed_at']->equalTo($data['published_at']))->toBeTrue()
        ->and($data['materially_updated_at'])->toBeNull();
    $draft = app(ArticlePublication::class)->prepare([...$data, 'status' => 'draft'], $editor);
    expect($draft['content_owner'])->toBeNull()->and($draft['reviewed_at'])->toBeNull()
        ->and($draft['published_at'])->toBeNull();

    $article = Article::create([...articleWorkflowFields(), 'user_id' => $editor->id,
        'thumbnail' => 'articles/reception.png', 'content_owner' => 'The original reviewer',
        'reviewed_at' => now()->subDay(), 'published_at' => now()->subDays(2),
        'materially_updated_at' => now()->subDay()]);
    $saved = app(ArticlePublication::class)->prepare([...$data,
        'content_owner' => 'Another invented reviewer', 'published_at' => '2099-01-01',
        'reviewed_at' => '2099-01-01', 'materially_updated_at' => '2099-01-01'], $editor, $article);
    expect($saved['content_owner'])->toBe($article->content_owner)
        ->and($saved['published_at']->equalTo($article->published_at))->toBeTrue()
        ->and($saved['reviewed_at']->equalTo($article->reviewed_at))->toBeTrue()
        ->and($saved['materially_updated_at']->equalTo($article->materially_updated_at))->toBeTrue();
});

it('refreshes the named review for public-facing article changes without replacing its first publication date', function (string $field, string $value) {
    $editor = articleWorkflowAdministrator();
    $article = Article::create([...articleWorkflowFields(), 'user_id' => $editor->id,
        'thumbnail' => 'articles/reception.png', 'content_owner' => 'Original reviewer',
        'reviewed_at' => now()->subDay(), 'published_at' => now()->subDays(2)]);
    $data = app(ArticlePublication::class)->prepare([...articleWorkflowFields(),
        'thumbnail' => 'articles/reception.png', $field => $value], $editor, $article);
    expect($data['content_owner'])->toBe($editor->name)
        ->and($data['reviewed_at']->toDateTimeString())->toBe(now('UTC')->toDateTimeString())
        ->and($data['materially_updated_at']->equalTo($data['reviewed_at']))->toBeTrue()
        ->and($data['published_at']->equalTo($article->published_at))->toBeTrue();
})->with([
    ['title', 'Prepare the busy reception desk'],
    ['content', '<p>Review the reception diary carefully.</p>'],
    ['excerpt', 'Practical reception advice for a busy working day.'],
    ['topic', 'Reception'],
    ['thumbnail', 'articles/replacement.png'],
    ['seo_title', 'Prepare the reception desk for a busy day'],
    ['seo_description', 'Practical advice for keeping reception appointments organized on a busy working day.'],
    ['seo_keywords', 'reception, appointments'],
]);

it('requires a verified administrator with an account name to publish', function () {
    $ordinary = User::factory()->create();
    expect(fn () => app(ArticlePublication::class)->prepare([...articleWorkflowFields(),
        'thumbnail' => 'articles/reception.png'], $ordinary))->toThrow(HttpException::class);

    $unverified = articleWorkflowAdministrator();
    $unverified->forceFill(['email_verified_at' => null])->save();
    expect(fn () => app(ArticlePublication::class)->prepare([...articleWorkflowFields(),
        'thumbnail' => 'articles/reception.png'], $unverified))->toThrow(HttpException::class);

    $editor = articleWorkflowAdministrator();
    $editor->update(['name' => '   ']);
    $this->actingAs($editor);
    Livewire::test(CreateArticle::class)
        ->fillForm([...articleWorkflowFields(), 'thumbnail' => UploadedFile::fake()->image('reception.png')])
        ->call('create')->assertHasFormErrors(['status']);
    expect(Article::count())->toBe(0);
});

it('keeps review attribution and publication history when only Active changes', function () {
    $editor = articleWorkflowAdministrator();
    $article = Article::create([...articleWorkflowFields(), 'user_id' => $editor->id,
        'thumbnail' => 'articles/reception.png', 'content_owner' => 'Original reviewer',
        'reviewed_at' => now()->subDay(), 'published_at' => now()->subDays(2)]);
    $data = app(ArticlePublication::class)->prepare([...articleWorkflowFields(['active' => false]),
        'thumbnail' => 'articles/reception.png'], $editor, $article);
    expect($data['content_owner'])->toBe('Original reviewer')
        ->and($data['reviewed_at']->equalTo($article->reviewed_at))->toBeTrue()
        ->and($data['published_at']->equalTo($article->published_at))->toBeTrue()
        ->and($data['materially_updated_at'])->toBeNull();
});

it('clears the upload error after a successful retry while retaining entered text', function () {
    $this->actingAs(articleWorkflowAdministrator());
    $component = Livewire::test(CreateArticle::class)
        ->fillForm(['content' => '<p>Keep this article text.</p>'])
        ->call('create')->assertHasFormErrors(['title'])
        ->call('_uploadErrored', 'data.thumbnail.retry', null, false)
        ->assertHasFormErrors(['thumbnail']);
    $component->upload('data.thumbnail', [UploadedFile::fake()->image('recovered.png')])
        ->assertHasNoFormErrors(['thumbnail'])
        ->assertSet('data.content', fn ($content) => str_contains(json_encode($content), 'Keep this article text.'))
        ->fillForm(['title' => 'A recovered draft'])
        ->call('create')->assertHasNoFormErrors();
    expect(Article::sole()->status)->toBe('draft')
        ->and(Article::sole()->content)->toContain('Keep this article text.');
    Storage::disk('public')->assertExists(Article::sole()->thumbnail);
});
