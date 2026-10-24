<?php

use App\Domain\HelpCenter\HelpCatalog;
use App\Domain\HelpCenter\HelpSearch;
use App\Domain\PlatformAccess\Enums\StarterRole;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\Location;
use App\Domain\PlatformAccess\Models\Membership;
use App\Domain\PlatformAccess\Services\MembershipAccessManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Ssr\Gateway;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function helpArticleFixture(string $slug, array $overrides = []): array
{
    return array_replace([
        'slug' => $slug, 'title' => 'Add a team member', 'summary' => 'Invite a colleague and choose their access.',
        'category' => 'team', 'keywords' => ['staff', 'invite', 'employee'], 'updated_at' => now()->toDateString(),
        'status' => 'published', 'roles' => ['Owner', 'Manager'], 'related' => [],
        'sections' => [['id' => 'steps', 'title' => 'Add your colleague', 'body' => "1. Open Team.\n2. Select Add team member.\n3. Review the saved profile."]],
        'action' => ['key' => 'team', 'label' => 'Go to Team'],
    ], $overrides);
}

beforeEach(function () {
    $this->withoutVite();
    $this->helpDirectory = sys_get_temp_dir().'/clipperdesk-help-test-'.bin2hex(random_bytes(8));
    mkdir($this->helpDirectory);
    config(['help.content_path' => $this->helpDirectory, 'help.media_manifest' => $this->helpDirectory.'/media.json']);
    file_put_contents($this->helpDirectory.'/add-team-member.json', json_encode(helpArticleFixture('add-team-member')));
    file_put_contents($this->helpDirectory.'/add-location.json', json_encode(helpArticleFixture('add-location', [
        'title' => 'Add another location', 'summary' => 'Prepare a new branch and its hours.', 'category' => 'business',
        'keywords' => ['branch', 'location', 'opening'], 'action' => ['key' => 'locations', 'label' => 'Go to Locations'],
        'sections' => [['id' => 'steps', 'title' => 'Prepare your branch', 'body' => 'Open Locations and review the branch address and hours.']],
    ])));
});

afterEach(function () {
    foreach (glob($this->helpDirectory.'/*') ?: [] as $path) {
        unlink($path);
    }
    rmdir($this->helpDirectory);
});

it('serves public index category and complete article HTML without optional SSR', function () {
    $this->mock(Gateway::class)->shouldReceive('dispatch')->andReturnNull();
    $this->get('/help')->assertOk()->assertSee('How can we help?')->assertSee('Team &amp; availability', false)
        ->assertHeader('X-Robots-Tag', 'index, follow, max-image-preview:large');
    $this->get('/help/category/team')->assertOk()->assertSee('Add a team member');
    $response = $this->get('/help/articles/add-team-member')->assertOk()
        ->assertSee('<h1>Add a team member</h1>', false)->assertSee('<li>Open Team.</li>', false)
        ->assertSee('TechArticle')->assertSee('BreadcrumbList')->assertSee('Go to Team');
    $dom = new DOMDocument;
    @$dom->loadHTML($response->getContent());
    expect($dom->getElementsByTagName('h1'))->toHaveCount(1)
        ->and($dom->getElementsByTagName('title'))->toHaveCount(1)
        ->and($dom->getElementsByTagName('article'))->toHaveCount(1);
    $this->assertGuest();
});

it('shares bounded article summaries and contextual topics without tenant records', function () {
    $this->get('/help')->assertInertia(fn (Assert $page) => $page->component('Help/Index', false)
        ->has('articles', 2)->where('articles.0.slug', 'add-team-member')->where('articles.0.url', route('help.article', 'add-team-member'))
        ->where('help.category.slug', 'getting-started'));
    [$owner, $business] = createTenantMembership();
    $this->actingAs($owner)->get(route('business.team.index', $business))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('help.category.slug', 'team')->has('help.articles', 1));
});

it('supports natural questions synonyms partial words and misspellings with useful ranking', function (string $query, string $expected) {
    $results = app(HelpSearch::class)->query($query);
    expect($results)->not->toBeEmpty()->and($results[0]['slug'])->toBe($expected);
})->with([
    ['How do I add another location?', 'add-location'],
    ['add worker', 'add-team-member'],
    ['employee', 'add-team-member'],
    ['sta', 'add-team-member'],
    ['employe', 'add-team-member'],
    ['locaton', 'add-location'],
    ['new branch', 'add-location'],
]);

it('bounds search inputs results and returns an honest empty response', function () {
    $this->getJson('/help/search?q=unfindablexyz')->assertOk()->assertJsonPath('results', []);
    $this->getJson('/help/search?q[]=staff')->assertOk()->assertJsonPath('query', '')->assertJsonPath('results', []);
    $this->getJson('/help/search?q='.str_repeat('x', 500))->assertOk()->assertJson(fn ($json) => $json->where('query', str_repeat('x', 180))->has('results', 0));
    $this->get('/help?q=employee')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
        ->assertSee('<link rel="canonical" href="'.route('help.index').'"', false);
    $this->get('/help/articles/does-not-exist')->assertNotFound();
    $this->get('/help/category/does-not-exist')->assertNotFound();
});

it('excludes draft future invalid and missing content from public routes search and sitemap', function () {
    foreach ([['draft-article', ['status' => 'draft']], ['future-article', ['updated_at' => now()->addYear()->toDateString()]], ['invalid-article', ['category' => 'unknown']]] as [$slug, $override]) {
        file_put_contents($this->helpDirectory.'/'.$slug.'.json', json_encode(helpArticleFixture($slug, $override)));
        $this->get('/help/articles/'.$slug)->assertNotFound();
    }
    $this->get('/sitemap.xml')->assertOk()->assertSee('/help/articles/add-team-member', false)
        ->assertDontSee('/draft-article', false)->assertDontSee('/future-article', false)->assertDontSee('/invalid-article', false)
        ->assertDontSee('/help/search', false)->assertDontSee('/help/open', false);
    expect(app(HelpCatalog::class)->articles())->toHaveCount(2);
});

it('sanitizes authored markdown and never includes an unverified media asset', function () {
    $article = helpArticleFixture('unsafe', [
        'sections' => [['id' => 'safe', 'title' => 'Safe section', 'body' => '<script>alert("xss")</script><iframe src="https://example.test"></iframe> [Unsafe](javascript:alert(1))']],
        'screenshots' => [['key' => 'missing', 'alt' => 'Demo screen', 'caption' => 'Example', 'section' => 'safe']],
    ]);
    file_put_contents($this->helpDirectory.'/unsafe.json', json_encode($article));
    $rendered = app(HelpCatalog::class)->find('unsafe');
    expect($rendered['sections'][0]['html'])->not->toContain('<script')->not->toContain('<iframe')->not->toContain('href="javascript:')
        ->and($rendered['screenshots'])->toBe([]);
    expect(app(HelpCatalog::class)->validMedia(['path' => '/images/help/../../private.png', 'width' => 1, 'height' => 1]))->toBeFalse();
    expect(app(HelpCatalog::class)->validate())->toContain('unsafe.json: Missing or invalid screenshot missing');
});

it('validates published relationships duplicate sections and registered deep routes', function () {
    file_put_contents($this->helpDirectory.'/duplicate-sections.json', json_encode(helpArticleFixture('duplicate-sections', [
        'sections' => [
            ['id' => 'duplicate', 'title' => 'First', 'body' => '[Bad](/help/articles/missing)'],
            ['id' => 'duplicate', 'title' => 'Second', 'body' => 'Text'],
        ],
    ])));
    file_put_contents($this->helpDirectory.'/bad-links.json', json_encode(helpArticleFixture('bad-links', [
        'related' => ['missing'], 'sections' => [['id' => 'links', 'title' => 'Links', 'body' => '[Bad](/help/articles/missing)']],
    ])));
    $errors = app(HelpCatalog::class)->validate();
    expect($errors)->toContain('duplicate-sections.json: Invalid or duplicate article section')
        ->toContain('bad-links.json: Missing published related article missing')->toContain('bad-links.json: Broken article link missing');
});

it('protects deep links behind sign in and redirects the eligible owner through real routes', function () {
    $this->get('/help/open/team')->assertRedirect(route('login'));
    [$owner, $business] = createTenantMembership();
    $this->actingAs($owner)->get('/help/open/team')->assertRedirect(route('business.team.index', $business));
    $this->actingAs($owner)->get('/help/open/profile')->assertRedirect(route('profile.show'));
    $this->actingAs($owner)->get('/help/open/made-up')->assertNotFound();
    $other = Business::factory()->create();
    $this->actingAs($owner)->get('/help/open/team?business='.$other->public_id)->assertNotFound();
});

it('explains permission and subscription restrictions without exposing unauthorized links', function () {
    [$receptionist, $business] = createTenantMembership(StarterRole::Receptionist);
    activateTestSubscription($business, 'starter');
    $this->actingAs($receptionist)->get('/help/open/billing')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Help/Workspaces', false)
        ->where('workspaces.0.allowed', false)->where('workspaces.0.status', 'permission_denied')->where('workspaces.0.href', null));
    [$owner, $starterBusiness] = createTenantMembership();
    activateTestSubscription($starterBusiness, 'starter');
    Location::factory()->create(['business_id' => $starterBusiness->id]);
    $this->actingAs($owner)->get('/help/open/inventory')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('workspaces.0.status', 'upgrade_required')->where('workspaces.0.href', null));
});

it('asks members of several businesses to choose and excludes revoked memberships', function () {
    [$owner, $first] = createTenantMembership();
    $second = Business::factory()->create();
    $membership = Membership::factory()->create(['business_id' => $second->id, 'user_id' => $owner->id]);
    app(MembershipAccessManager::class)->assignStarterRole($membership, StarterRole::Owner, $owner, 'Second test business');
    $this->actingAs($owner)->get('/help/open/team')->assertInertia(fn (Assert $page) => $page->component('Help/Workspaces', false)->has('workspaces', 2));
    $this->get('/help/open/team?business='.$second->public_id)->assertRedirect(route('business.team.index', $second));
    $membership->update(['revoked_at' => now()]);
    $this->get('/help/open/team')->assertRedirect(route('business.team.index', $first));
});

it('stores one privacy-minimal vote per session and revision while allowing a correction', function () {
    $this->postJson('/help/articles/add-team-member/feedback', ['helpful' => true])->assertOk()->assertJsonPath('saved', true);
    $this->postJson('/help/articles/add-team-member/feedback', ['helpful' => false])->assertOk();
    expect(DB::table('help_article_feedback')->count())->toBe(1)
        ->and((bool) DB::table('help_article_feedback')->value('helpful'))->toBeFalse();
    $record = (array) DB::table('help_article_feedback')->first();
    expect($record)->not->toHaveKeys(['user_id', 'business_id', 'ip_address', 'query'])
        ->and(strlen($record['visitor_hash']))->toBe(64);
    $this->postJson('/help/articles/add-team-member/feedback', ['helpful' => 'not-a-vote'])->assertUnprocessable();
    $this->postJson('/help/articles/missing/feedback', ['helpful' => true])->assertNotFound();
});

it('enforces a bounded feedback request budget', function () {
    for ($i = 0; $i < 15; $i++) {
        $this->postJson('/help/articles/add-team-member/feedback', ['helpful' => true])->assertOk();
    }
    $this->postJson('/help/articles/add-team-member/feedback', ['helpful' => true])->assertTooManyRequests()->assertHeader('Retry-After');
    expect(DB::table('help_article_feedback')->count())->toBe(1);
});

it('fails closed on malformed article shapes without leaking editorial metadata', function () {
    file_put_contents($this->helpDirectory.'/bad-shapes.json', json_encode(helpArticleFixture('bad-shapes', [
        'sections' => [['id' => [], 'title' => 'Bad', 'body' => 'text'], 7],
        'screenshots' => 'bad', 'action' => ['key' => [], 'label' => 'Bad'],
    ])));
    file_put_contents($this->helpDirectory.'/editorial.json', json_encode(helpArticleFixture('editorial', ['internal_evidence' => 'private-review-note'])));
    $this->get('/help/articles/bad-shapes')->assertNotFound();
    expect(app(HelpCatalog::class)->find('editorial'))->not->toHaveKey('internal_evidence');
    expect(app(HelpCatalog::class)->validate())->not->toBeEmpty();
});

it('keeps search suggestions independent from the feedback budget', function () {
    for ($i = 0; $i < 20; $i++) {
        $this->getJson('/help/search?q=staff')->assertOk();
    }
    $this->postJson('/help/articles/add-team-member/feedback', ['helpful' => true])->assertOk();
});

it('verifies decoded media dimensions and hashes and permits incomplete draft media', function () {
    $relative = '/images/help/validation-test-'.bin2hex(random_bytes(6)).'.png';
    $file = public_path(ltrim($relative, '/'));
    if (! is_dir(dirname($file))) {
        mkdir(dirname($file), 0755, true);
    }
    file_put_contents($file, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a0fsAAAAASUVORK5CYII='));
    try {
        $asset = ['path' => $relative, 'width' => 1, 'height' => 1, 'sha256' => hash_file('sha256', $file)];
        $catalog = app(HelpCatalog::class);
        expect($catalog->validMedia($asset))->toBeTrue()
            ->and($catalog->validMedia([...$asset, 'width' => 2]))->toBeFalse()
            ->and($catalog->validMedia([...$asset, 'sha256' => str_repeat('0', 64)]))->toBeFalse();
        file_put_contents($this->helpDirectory.'/draft-screen.json', json_encode(helpArticleFixture('draft-screen', [
            'status' => 'draft', 'screenshots' => [['key' => 'not-captured', 'alt' => 'Demo', 'caption' => 'Demo', 'section' => 'steps']],
        ])));
        expect($catalog->validate())->toBe([]);
    } finally {
        unlink($file);
    }
});

it('provides aggregate feedback for maintainers without exposing session identifiers', function () {
    $this->postJson('/help/articles/add-team-member/feedback', ['helpful' => true])->assertOk();
    $hash = DB::table('help_article_feedback')->value('visitor_hash');
    $this->artisan('help:feedback')->expectsOutputToContain('add-team-member')->doesntExpectOutputToContain($hash)->assertSuccessful();
    $this->artisan('help:feedback', ['--article' => 'missing'])->expectsOutputToContain('No feedback')->assertSuccessful();
});
