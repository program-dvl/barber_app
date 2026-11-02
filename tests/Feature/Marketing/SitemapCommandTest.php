<?php

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

it('prints the current curated sitemap without creating or overwriting a public file', function (bool $existingFile) {
    $this->app->detectEnvironment(fn () => 'production');
    config(['brand.website_url' => 'https://clipperdesk.com']);
    $expected = $this->get('https://alias.example.test/sitemap.xml')->assertOk()->getContent();

    $originalPublicPath = public_path();
    $temporaryPublicPath = sys_get_temp_dir().'/clipperdesk-sitemap-'.str()->uuid();
    File::makeDirectory($temporaryPublicPath);
    $this->app->usePublicPath($temporaryPublicPath);
    if ($existingFile) {
        File::put(public_path('sitemap.xml'), 'Existing file is preserved for operator review.');
    }

    try {
        expect(Artisan::call('generate:sitemap'))->toBe(0);
        $xml = Artisan::output();
        expect($xml)->toBe($expected)
            ->toContain('https://clipperdesk.com/features', 'https://clipperdesk.com/help')
            ->not->toContain('/register', '/book/', '/roadmap', '/terms-of-service', '/refund-policy', '/privacy-policy', 'alias.example.test');
        expect(File::exists(public_path('sitemap.xml')))->toBe($existingFile);
        if ($existingFile) {
            expect(File::get(public_path('sitemap.xml')))->toBe('Existing file is preserved for operator review.');
        }
    } finally {
        $this->app->usePublicPath($originalPublicPath);
        File::deleteDirectory($temporaryPublicPath);
    }
})->with([false, true]);

it('reflects changed article publication on each command invocation', function () {
    $author = User::factory()->create(['name' => 'ClipperDesk editor']);
    $article = Article::withoutEvents(fn () => Article::create([
        'user_id' => $author->id, 'title' => 'Plan a working day', 'slug' => 'plan-working-day',
        'content' => 'Practical booking guidance.', 'excerpt' => 'Useful booking guidance for your team.',
        'topic' => 'Operations', 'thumbnail' => 'articles/working-day.webp',
        'seo_title' => 'Plan a working day', 'seo_description' => 'Practical booking guidance for your service business.',
        'active' => true, 'status' => 'published', 'content_owner' => 'Editorial team',
        'published_at' => now()->subDay(), 'reviewed_at' => now()->subDay(),
    ]));

    expect(Artisan::call('generate:sitemap'))->toBe(0);
    expect(Artisan::output())->toContain('/blog/plan-working-day');

    Article::withoutEvents(fn () => $article->update(['status' => 'draft']));

    expect(Artisan::call('generate:sitemap'))->toBe(0);
    expect(Artisan::output())->not->toContain('/blog/plan-working-day');
    $this->get('/sitemap.xml')->assertOk()->assertDontSee('/blog/plan-working-day');
});
