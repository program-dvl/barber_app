<?php

use App\Domain\Billing\Models\OwnerRegistrationIntent;
use App\Domain\Billing\Services\OwnerOnboardingService;
use App\Domain\BusinessConfiguration\Models\OnboardingSession;
use App\Models\Article;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function seoReviewedArticle(array $overrides = []): Article
{
    return Article::create(array_merge([
        'user_id' => User::factory()->create(['name' => 'Guide editor'])->id,
        'title' => 'Prepare salon notes', 'slug' => 'salon-consultation-notes-a-practical-template-and-example',
        'content' => '<p>Prepare useful preferences and review the client history before the visit.</p>',
        'excerpt' => 'A practical client handoff guide.', 'topic' => 'Client records',
        'thumbnail' => 'articles/salon-notes.webp', 'seo_title' => 'Prepare salon notes',
        'seo_description' => 'Prepare useful preferences and review the client history before the visit.',
        'active' => true, 'status' => 'published', 'content_owner' => 'Editor',
        'published_at' => now()->subHour(), 'reviewed_at' => now()->subHour(),
    ], $overrides));
}

it('measures only reviewed published article routes without sending query data or author identity', function (array $override, bool $eligible) {
    $article = seoReviewedArticle($override);
    $previous = app()->environment();
    app()->instance('env', 'production');
    config(['brand.website_url' => 'https://clipperdesk.com', 'analytics.measurement_id' => 'G-TEST123456']);
    try {
        $response = $this->get('https://clipperdesk.com/blog/'.$article->slug.'?email=private@example.test&signature=private');
        if (! $eligible) {
            $response->assertNotFound()->assertInertia(fn (Assert $page) => $page->where('publicAnalytics', null));
        } else {
            $response->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('publicAnalytics.page.location', 'https://clipperdesk.com/blog/'.$article->slug));
            $analytics = json_encode($response->viewData('page')['props']['publicAnalytics']);
            expect($analytics)->not->toContain('private')->not->toContain('Guide editor')->not->toContain('email');
            $this->actingAs(User::factory()->create())->get('https://clipperdesk.com/blog/'.$article->slug)
                ->assertOk()->assertInertia(fn (Assert $page) => $page->where('publicAnalytics', null));
        }
    } finally {
        app()->instance('env', $previous);
    }
})->with([
    [[], true], [['status' => 'draft'], false], [['active' => false], false],
    [['published_at' => now()->addDay()], false], [['reviewed_at' => null], false],
    [['content' => 'TODO: article draft'], false],
]);

it('projects truthful article images and dates without rewriting stored publication history', function () {
    $published = CarbonImmutable::parse('2026-11-06T17:51:04Z');
    $earlier = $published->subSeconds(11);
    $article = seoReviewedArticle(['published_at' => $published, 'reviewed_at' => $published, 'materially_updated_at' => $earlier]);
    $response = $this->get(route('blog.article', $article))->assertOk();
    $props = $response->viewData('page')['props'];
    $schema = collect($props['structuredData']['@graph'])->firstWhere('@type', 'Article');
    expect($props['article']['materially_updated_at'])->toBeNull()
        ->and($schema['dateModified'])->toBe($published->toIso8601String())
        ->and($schema['image'])->toBe($props['seo']['image'])
        ->and($article->fresh()->materially_updated_at->equalTo($earlier))->toBeTrue();
    $article->update(['materially_updated_at' => $published->addMinute()]);
    expect($article->publicUpdatedAt())->toBe($published->addMinute()->toIso8601String());
});

it('links relevant guides from the product journey and withdraws draft articles automatically', function () {
    $article = seoReviewedArticle();
    $this->get(route('marketing.features.show', 'client-management'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('reading', 2)
            ->where('reading.0.href', route('blog.article', $article)));
    $this->get(route('marketing.use-cases.show', 'keep-client-history-together'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('reading', 2)->where('solution.slug', 'salons'));
    $article->update(['status' => 'draft']);
    $this->get(route('marketing.features.show', 'client-management'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('reading', 1)
            ->where('reading.0.href', route('marketing.guides.show', 'switch-from-paper-bookings')));
});

it('reports verified trials separately from registrations with exact local-date boundaries and replay safety', function () {
    try {
        $this->travelTo(CarbonImmutable::parse('2026-11-14T18:29:59.999999Z'));
        $before = User::factory()->create();
        OwnerRegistrationIntent::create(['user_id' => $before->id, 'business_name' => 'Before boundary', 'status' => 'pending']);
        app(OwnerOnboardingService::class)->complete($before);
        $this->travelTo(CarbonImmutable::parse('2026-11-14T18:30:00Z'));
        $owner = User::factory()->create();
        OwnerRegistrationIntent::create(['user_id' => $owner->id, 'business_name' => 'Private test business', 'status' => 'pending']);
        $business = app(OwnerOnboardingService::class)->complete($owner);
        app(OwnerOnboardingService::class)->complete($owner);
        $business->update(['configuration_published_at' => now()]);
        $unverified = User::factory()->unverified()->create();
        OwnerRegistrationIntent::create(['user_id' => $unverified->id, 'business_name' => 'Pending verification', 'status' => 'pending']);
        $this->travelTo(CarbonImmutable::parse('2026-11-15T18:30:00Z'));
        $after = User::factory()->create();
        OwnerRegistrationIntent::create(['user_id' => $after->id, 'business_name' => 'After boundary', 'status' => 'pending']);
        app(OwnerOnboardingService::class)->complete($after);
        Artisan::call('analytics:acquisition-report', ['--from' => '2026-11-15', '--to' => '2026-11-15']);
        $output = Artisan::output();
        $report = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
        expect($report['owner_registrations'])->toBe(2)->and($report['verified_trial_activations'])->toBe(1)
            ->and($report['trial_cohort_booking_setup_published'])->toBe(1)
            ->and($output)->not->toContain($owner->email)->not->toContain($business->name);
        OnboardingSession::query()->updateOrCreate(['business_id' => $business->id], ['generated_data' => ['source' => 'pitch_demo'], 'started_at' => now(), 'last_saved_at' => now()]);
        Artisan::call('analytics:acquisition-report', ['--from' => '2026-11-15', '--to' => '2026-11-15']);
        expect(json_decode(Artisan::output(), true)['verified_trial_activations'])->toBe(0);
    } finally {
        $this->travelBack();
    }
});

it('rejects invalid report ranges without exporting data', function (array $options) {
    $this->artisan('analytics:acquisition-report', $options)->assertFailed();
})->with([
    [['--from' => '2026-02-30', '--to' => '2026-03-01']],
    [['--from' => '2026-11-15', '--to' => '2026-11-14']],
    [['--from' => '2026-11-15', '--to' => '2026-11-15', '--timezone' => 'invalid']],
]);
