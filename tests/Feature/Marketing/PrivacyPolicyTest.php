<?php

use App\Models\User;
use App\Support\Legal\PrivacyNotice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Ssr\Gateway;
use Inertia\Ssr\Response;

uses(RefreshDatabase::class);

function approvePrivacyFixture(): void
{
    config([
        'privacy.approved' => true,
        'privacy.approval_reference' => 'test-only-review',
        'privacy.operations_verified' => true,
        'privacy.effective_at' => now('Asia/Kolkata')->toDateString(),
        'privacy.canonical_url' => 'https://clipperdesk.com/privacy-policy',
        'privacy.processing_countries' => 'Canada (test fixture)',
        'privacy.providers.hosting' => 'Test hosting',
        'privacy.providers.storage' => 'Test storage',
        'privacy.providers.account_email' => 'Amazon SES (test fixture)',
        'privacy.providers.client_email' => 'Amazon SES (test fixture)',
        'privacy.providers.sms' => 'Twilio (test fixture)',
    ]);
}

it('returns a meaningful policy as guest-readable HTML without a JavaScript renderer', function () {
    $this->mock(Gateway::class)->shouldReceive('dispatch')->once()->andReturnNull();
    $response = $this->get('/privacy-policy')->assertOk()
        ->assertHeader('Content-Type', 'text/html; charset=utf-8')
        ->assertSee('<h1>Privacy Policy</h1>', false)
        ->assertSee('Google Sign-In and Third-Party Authentication')
        ->assertSee('support@clipperdesk.com')->assertSee('111 Galileo Dr')
        ->assertSee('cd-marketing-header')->assertSee('cd-marketing-footer');
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    expect($document->getElementsByTagName('article'))->toHaveCount(1)
        ->and($document->getElementsByTagName('h1'))->toHaveCount(1);
    $this->assertGuest();
});

it('avoids rendering a duplicate policy or title when the optional SSR service responds', function () {
    $this->mock(Gateway::class)->shouldReceive('dispatch')->once()->andReturnUsing(
        fn ($page) => new Response('<title data-inertia="">Clipperdesk Privacy Policy</title>', '<div id="app"><main>'.$page['props']['content'].'</main></div>')
    );
    $html = $this->get('/privacy-policy')->assertOk()->getContent();
    $document = new DOMDocument;
    @$document->loadHTML($html);
    expect($document->getElementsByTagName('h1'))->toHaveCount(1)
        ->and($document->getElementsByTagName('article'))->toHaveCount(1)
        ->and($document->getElementsByTagName('title'))->toHaveCount(1);
});

it('keeps unapproved content out of indexing without showing internal review language', function () {
    $this->get('/privacy-policy')->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, follow, noarchive')
        ->assertDontSee('Review preview.')->assertDontSee('Effective date')
        ->assertDontSee('Good Hours')
        ->assertDontSee('OPEN-')->assertDontSee('blocked_policy')
        ->assertDontSee('Privacy/DPO')->assertDontSee('Approval owner');
    $html = app(PrivacyNotice::class)->content()['html'];
    expect($html)->not->toContain('proposed')->not->toContain('review draft')->not->toContain('{{');
});

it('makes an approved notice indexable with effective metadata and sitemap inclusion', function () {
    approvePrivacyFixture();
    $this->get('/privacy-policy?email=private@example.test')
        ->assertOk()->assertHeader('X-Robots-Tag', 'index, follow, max-image-preview:large')
        ->assertSee('<meta name="robots" content="index, follow, max-image-preview:large" data-inertia="robots">', false)
        ->assertSee('<link rel="canonical" href="https://clipperdesk.com/privacy-policy" data-inertia="canonical">', false)
        ->assertSee('<title data-inertia="">Clipperdesk Privacy Policy</title>', false)
        ->assertSee('Effective date')->assertSee('Operating service providers')
        ->assertDontSee('Review preview')->assertDontSee('private@example.test');
    $this->get('/sitemap.xml')->assertOk()->assertSee('https://clipperdesk.com/privacy-policy', false);
    $this->get('/robots.txt')->assertOk()->assertDontSee('Disallow: /privacy-policy', false);
});

it('fails closed when an approved flag is set without complete publication facts', function (string $key, mixed $value) {
    approvePrivacyFixture();
    config(['privacy.'.$key => $value]);
    expect(app(PrivacyNotice::class)->isPublished())->toBeFalse();
    $this->get('/privacy-policy')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, follow, noarchive')->assertDontSee('Review preview');
})->with([
    ['operator_name', null], ['operator_address', null], ['privacy_email', 'invalid'],
    ['effective_at', '2099-01-01'], ['effective_at', '2026-02-30'],
    ['approval_reference', null], ['operations_verified', false], ['processing_countries', null],
    ['providers.hosting', null], ['canonical_url', 'http://clipperdesk.com/privacy-policy'],
]);

it('redirects only the configured www policy alias to the canonical URL', function () {
    config(['privacy.canonical_url' => 'https://clipperdesk.com/privacy-policy']);
    $this->get('https://www.clipperdesk.com/privacy-policy?utm_source=test')->assertStatus(301)->assertRedirect('https://clipperdesk.com/privacy-policy');
    $this->get('https://clipperdesk.com/privacy-policy')->assertOk();
    $this->get('https://other.example/privacy-policy')->assertOk()
        ->assertSee('https://clipperdesk.com/privacy-policy', false);
});

it('supports existing Inertia links by visiting the policy with its own server metadata', function () {
    $version = $this->get('/privacy-policy')->viewData('page')['version'];
    $this->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => $version])->get('/privacy-policy')->assertStatus(409)
        ->assertHeader('X-Inertia-Location', url('/privacy-policy'));
});

it('is readable by authenticated users as well as guests', function () {
    $this->actingAs(User::factory()->create())->get('/privacy-policy')->assertOk()
        ->assertSee('Google Sign-In and Third-Party Authentication')
        ->assertHeader('Cache-Control', 'no-store, private');
});

it('has unique working anchors and public internal destinations', function () {
    $html = $this->get('/privacy-policy')->assertOk()->getContent();
    $document = new DOMDocument;
    @$document->loadHTML($html);
    $xpath = new DOMXPath($document);
    $ids = [];
    foreach ($xpath->query('//*[@id]') as $node) {
        $ids[] = $node->getAttribute('id');
    }
    expect(array_unique($ids))->toHaveCount(count($ids));
    foreach ($xpath->query('//a[@href]') as $link) {
        $href = $link->getAttribute('href');
        if (str_starts_with($href, '#')) {
            expect($ids)->toContain(substr($href, 1));
        } elseif (parse_url($href, PHP_URL_HOST) === parse_url(url('/'), PHP_URL_HOST)) {
            $this->get($href)->assertOk();
        }
    }
});

it('discloses only supported permissions and honestly distinguishes revocation and deletion', function () {
    $this->get('/privacy-policy')->assertOk()
        ->assertSee('openid')->assertSee('profile')->assertSee('email')
        ->assertSee('does not save or display the Google profile picture')
        ->assertSee('does not persist Google OAuth access or refresh tokens')
        ->assertSee('does not currently offer a self-service Google unlink control')
        ->assertSee('does not automatically erase or anonymize')
        ->assertSee('does not erase customer records')
        ->assertSee('do not give Clipperdesk access to Gmail');
});

it('escapes configured contacts and lists only confirmed provider names', function () {
    config(['privacy.operator_name' => '<script>alert(1)</script>', 'privacy.providers.hosting' => 'Hosting <test>']);
    $this->get('/privacy-policy')->assertOk()->assertDontSee('<script>alert(1)</script>', false)
        ->assertSee('&lt;script&gt;', false)->assertSee('Hosting &lt;test&gt;', false)
        ->assertDontSee('Resend')->assertDontSee('Sentry');
});

it('provides a release check that fails without approval and passes a complete reviewed configuration', function () {
    $this->artisan('legal:check-privacy')->assertFailed();
    approvePrivacyFixture();
    $this->artisan('legal:check-privacy')->assertSuccessful();
});
