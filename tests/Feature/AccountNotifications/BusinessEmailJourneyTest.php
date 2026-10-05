<?php

use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Domain\AccountNotifications\Models\AccountLoginActivity;
use App\Domain\AccountNotifications\Models\EmailNotificationDelivery;
use App\Domain\Billing\Models\OwnerRegistrationIntent;
use App\Domain\PlatformAccess\Enums\BusinessStatus;
use App\Domain\PlatformAccess\Enums\PlatformRole;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\PlatformRoleAssignment;
use App\Domain\PlatformAccess\Services\PlatformBusinessLifecycleService;
use App\Domain\PlatformAccess\Services\SupportAccessService;
use App\Models\User;
use App\Notifications\BusinessStatusChangedNotification;
use App\Notifications\EmailAddressChangedNotification;
use App\Notifications\NewSignInNotification;
use App\Notifications\PasswordChangedNotification;
use App\Notifications\SupportAccessNotification;
use App\Notifications\TwoFactorSecurityNotification;
use App\Notifications\VerifyEmailNotification;
use App\Notifications\WelcomeNotification;
use Aws\Ses\SesClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Laravel\Fortify\Events\RecoveryCodeReplaced;
use Laravel\Fortify\Events\RecoveryCodesGenerated;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Jetstream\Jetstream;

uses(RefreshDatabase::class);

it('sends the verified owner journey from registration through workspace welcome', function (): void {
    Notification::fake();

    $this->post('/register', [
        'name' => 'Journey Owner',
        'business_name' => 'North Street Studio',
        'email' => 'journey@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature(),
    ])->assertRedirect(route('verification.notice'));

    $user = User::query()->where('email', 'journey@example.com')->firstOrFail();
    Notification::assertSentTo($user, VerifyEmailNotification::class);
    Notification::assertNotSentTo($user, WelcomeNotification::class);

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->getKey(), 'hash' => sha1($user->email)],
    );
    $this->actingAs($user)->get($verificationUrl)->assertRedirect();

    $intent = OwnerRegistrationIntent::query()->whereBelongsTo($user)->firstOrFail();
    $business = Business::query()->findOrFail($intent->business_id);
    Notification::assertSentTo($user, WelcomeNotification::class, fn (WelcomeNotification $mail): bool => $mail->businessId() === $business->getKey()
        && $mail->businessName === 'North Street Studio'
    );
});

it('alerts once for a known browser and again when a different browser signs in', function (): void {
    Notification::fake();
    $user = User::factory()->create(['email' => 'signin@example.com']);

    $this->withHeader('User-Agent', 'ClipperDeskTest Chrome/140 Windows')
        ->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect();
    Notification::assertSentToTimes($user, NewSignInNotification::class, 1);

    $this->post('/logout');
    $this->withHeader('User-Agent', 'ClipperDeskTest Chrome/140 Windows')
        ->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect();
    Notification::assertSentToTimes($user, NewSignInNotification::class, 1);

    $this->post('/logout');
    $this->withHeader('User-Agent', 'ClipperDeskTest Safari/20 Macintosh')
        ->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect();
    Notification::assertSentToTimes($user, NewSignInNotification::class, 2);

    $this->assertDatabaseCount('account_login_activities', 2);
});

it('notifies both sides of an email change without exposing the full new address', function (): void {
    Notification::fake();
    $user = User::factory()->create([
        'name' => 'Secure Owner',
        'email' => 'old-owner@example.com',
    ]);

    app(UpdateUserProfileInformation::class)->update($user, [
        'name' => 'Secure Owner',
        'email' => 'new-owner@example.com',
    ]);

    Notification::assertSentTo($user, VerifyEmailNotification::class);
    Notification::assertSentOnDemand(EmailAddressChangedNotification::class, function (EmailAddressChangedNotification $mail, array $channels, object $notifiable): bool {
        return $notifiable->routes['mail'] === 'old-owner@example.com'
            && $mail->newEmailMasked === 'ne*******@example.com';
    });
});

it('sends dedicated two-factor security notices for critical changes', function (): void {
    Notification::fake();
    $user = User::factory()->create();

    TwoFactorAuthenticationConfirmed::dispatch($user);
    TwoFactorAuthenticationDisabled::dispatch($user);
    RecoveryCodesGenerated::dispatch($user);
    RecoveryCodeReplaced::dispatch($user, 'used-code-is-never-emailed');

    foreach (['enabled', 'disabled', 'recovery_codes_regenerated', 'recovery_code_used'] as $change) {
        Notification::assertSentTo($user, TwoFactorSecurityNotification::class, fn (TwoFactorSecurityNotification $mail): bool => $mail->change === $change);
    }
});

it('records accepted mail without retaining the full recipient address or body', function (): void {
    config()->set('mail.default', 'array');
    config()->set('queue.default', 'sync');
    $user = User::factory()->create(['email' => 'private-recipient@example.com']);

    $user->notify(new PasswordChangedNotification);

    $delivery = EmailNotificationDelivery::query()->sole();
    expect($delivery->status)->toBe('accepted')
        ->and($delivery->user_id)->toBe($user->getKey())
        ->and($delivery->recipient_masked)->toBe('pr********@example.com')
        ->and($delivery->recipient_hash)->toHaveLength(64)
        ->and($delivery->getAttributes())->not->toHaveKey('body');
});

it('renders the ClipperDesk mail theme and exposes the SES configuration contract', function (): void {
    $user = User::factory()->make(['name' => 'Theme Owner']);
    $html = (string) (new PasswordChangedNotification)->toMail($user)->render();

    expect($html)->toContain('ClipperDesk')
        ->toContain('Your whole day. Beautifully run.')
        ->toContain('Review account security')
        ->toContain('Security notice')
        ->toContain('The ClipperDesk team')
        ->toContain('clipperdesk-mark-CDKNGozz.svg')
        ->toContain('<title>ClipperDesk</title>')
        ->not->toContain('Barber_app')
        ->and(class_exists(SesClient::class))->toBeTrue()
        ->and(config('mail.mailers.ses.transport'))->toBe('ses');
});

it('prunes expired browser activity using the configured retention boundary', function (): void {
    $user = User::factory()->create();
    AccountLoginActivity::query()->create([
        'user_id' => $user->getKey(),
        'fingerprint' => str_repeat('a', 64),
        'device_label' => 'Old browser',
        'first_seen_at' => now()->subDays(200),
        'last_seen_at' => now()->subDays(181),
        'sign_in_count' => 1,
    ]);

    $this->artisan('account-email:prune-login-activity', ['--days' => 180])->assertSuccessful();

    $this->assertDatabaseCount('account_login_activities', 0);
});

it('notifies owners about platform status and approved support access', function (): void {
    Notification::fake();
    [$owner, $business] = createTenantMembership();
    $support = User::factory()->create();
    $approver = User::factory()->create();
    PlatformRoleAssignment::query()->create([
        'user_id' => $support->getKey(),
        'role' => PlatformRole::SupportOperator,
        'reason' => 'Account email journey test.',
    ]);
    PlatformRoleAssignment::query()->create([
        'user_id' => $approver->getKey(),
        'role' => PlatformRole::Administrator,
        'reason' => 'Account email journey test.',
    ]);

    app(SupportAccessService::class)->grant(
        $business,
        $support,
        $approver,
        'CD-EMAIL-1001',
        'Investigate a verified account email issue.',
        ['account_summary'],
        now()->addHour(),
    );
    app(PlatformBusinessLifecycleService::class)->changeStatus(
        $business,
        BusinessStatus::Suspended,
        $approver,
        'Security review requires a temporary suspension.',
    );

    Notification::assertSentTo($owner, SupportAccessNotification::class);
    Notification::assertSentTo($owner, BusinessStatusChangedNotification::class);
});
