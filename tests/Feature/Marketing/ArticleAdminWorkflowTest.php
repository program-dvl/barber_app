<?php

use App\Domain\PlatformAccess\Enums\PlatformRole;
use App\Domain\PlatformAccess\Models\PlatformRoleAssignment;
use App\Filament\Resources\Articles\Pages\CreateArticle;
use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Models\Article;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Livewire;

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
        'active' => true, 'status' => 'published', 'content_owner' => 'Editorial team',
        'reviewed_at' => now()->subMinute()->format('Y-m-d H:i:s'),
        'published_at' => now()->subMinute()->format('Y-m-d H:i:s'),
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
    $this->actingAs(articleWorkflowAdministrator());
    Livewire::test(CreateArticle::class)
        ->fillForm([...articleWorkflowFields(), 'thumbnail' => UploadedFile::fake()->image('reception.png', 800, 450)])
        ->call('create')->assertHasNoFormErrors();

    $article = Article::sole();
    expect(Article::publishable()->whereKey($article)->exists())->toBeTrue();
    $this->get(route('blog.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('articles.data', 1)->where('articles.data.0.slug', $article->slug)
        ->where('articles.data.0.thumbnail', Storage::disk('public')->url($article->thumbnail)));
    $this->get(route('blog.article', $article))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('article.html', fn (string $html) => str_contains($html, 'Review the appointments') && str_contains($html, '<h2>Check the diary</h2>'))
        ->where('seo.description', $article->seo_description));
    $this->get('/sitemap.xml')->assertOk()->assertSee('/blog/'.$article->slug);
});

it('keeps a scheduled article hidden until its publish time has arrived', function () {
    $this->actingAs(articleWorkflowAdministrator());
    Livewire::test(CreateArticle::class)
        ->fillForm([...articleWorkflowFields(['published_at' => now()->addDay()->format('Y-m-d H:i:s')]),
            'thumbnail' => UploadedFile::fake()->image('scheduled.png', 800, 450)])
        ->call('create')->assertHasNoFormErrors();

    $article = Article::sole();
    $this->get(route('blog.index'))->assertInertia(fn (Assert $page) => $page->has('articles.data', 0));
    $this->get(route('blog.article', $article))->assertNotFound();
    $this->get('/sitemap.xml')->assertDontSee('/blog/'.$article->slug);
    $this->travel(2)->days();
    $this->get(route('blog.article', $article))->assertOk();
    $this->get('/sitemap.xml')->assertSee('/blog/'.$article->slug);
});

it('reports missing publication details without creating an incomplete published article', function () {
    $this->actingAs(articleWorkflowAdministrator());
    Livewire::test(CreateArticle::class)
        ->fillForm(['title' => 'A publication to review', 'content' => '<p>Practical advice.</p>', 'status' => 'published'])
        ->call('create')->assertHasFormErrors(['thumbnail', 'excerpt', 'topic', 'seo_title', 'seo_description', 'content_owner', 'reviewed_at', 'published_at']);
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
