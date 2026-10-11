<?php

use App\Domain\PlatformAccess\Enums\PlatformRole;
use App\Domain\PlatformAccess\Models\PlatformRoleAssignment;
use App\Filament\Auth\AdminEmailVerificationPrompt;
use App\Filament\Auth\AdminLogin;
use App\Models\User;
use App\Notifications\AdminVerifyEmailNotification;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Fortify;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

function bootstrapInternalAdmin(): User
{
    $user = User::factory()->unverified()->create();
    PlatformRoleAssignment::query()->create([
        'user_id' => $user->id,
        'role' => PlatformRole::Administrator,
        'reason' => 'Internal administrator login fixture.',
    ]);

    return $user;
}

it('creates an administrator that can sign in and reach email verification', function () {
    $this->artisan('make:admin', [
        '--name' => 'Platform operator',
        '--email' => 'operator@example.test',
        '--password' => 'Synthetic-admin-password-123',
    ])
        ->expectsOutputToContain('Sign in at '.Filament::getPanel('admin')->getLoginUrl().'. Verify your email to enter internal administration.')
        ->assertSuccessful();

    $user = User::query()->where('email', 'operator@example.test')->firstOrFail();
    expect(Hash::check('Synthetic-admin-password-123', $user->password))->toBeTrue()
        ->and($user->hasPlatformRole(PlatformRole::Administrator))->toBeTrue()
        ->and($user->hasVerifiedEmail())->toBeFalse()
        ->and($user->two_factor_confirmed_at)->toBeNull()
        ->and($user->memberships()->exists())->toBeFalse();

    Livewire::test(AdminLogin::class)
        ->fillForm(['email' => $user->email, 'password' => 'Synthetic-admin-password-123'])
        ->call('authenticate')
        ->assertHasNoErrors()
        ->assertRedirect(Filament::getUrl());
    $this->assertAuthenticatedAs($user);
    $this->get('/admin')->assertRedirect(Filament::getEmailVerificationPromptUrl());
    $this->get('/admin/articles')->assertRedirect(Filament::getEmailVerificationPromptUrl());
    $this->get(Filament::getEmailVerificationPromptUrl())->assertOk();
});

it('verifies an admin signed email link then enters the dashboard without MFA setup', function () {
    $user = bootstrapInternalAdmin();
    $this->actingAs($user);
    Livewire::test(AdminEmailVerificationPrompt::class)
        ->callAction('resendNotification')
        ->assertHasNoErrors();
    Notification::assertSentTo($user, AdminVerifyEmailNotification::class, function ($notification) use ($user) {
        $mail = $notification->toMail($user);
        expect($mail->actionUrl)->toContain('/admin/email-verification/verify/')
            ->and($notification->notificationStream())->toBe('security');

        return true;
    });

    $signedUrl = Filament::getVerifyEmailUrl($user);
    $this->get($signedUrl.'&tampered=1')->assertForbidden();
    $this->get($signedUrl)->assertRedirect(Filament::getUrl());
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue()
        ->and($user->fresh()->two_factor_confirmed_at)->toBeNull()
        ->and(Filament::hasMultiFactorAuthentication())->toBeFalse();
    $this->get('/admin')->assertOk();
    $this->get('/admin/articles')->assertOk();
    $this->get('/admin/users')->assertOk();
    expect($user->memberships()->exists())->toBeFalse();
});

it('signs a verified admin directly in without changing existing Fortify enrollment', function (bool $enrolled) {
    $user = bootstrapInternalAdmin();
    $user->markEmailAsVerified();
    if ($enrolled) {
        $user->forceFill([
            'two_factor_secret' => Fortify::currentEncrypter()->encrypt('JBSWY3DPEHPK3PXP'),
            'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt(json_encode(['synthetic-recovery-code'])),
            'two_factor_confirmed_at' => now(),
        ])->save();
    }
    $before = $user->only(['two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at']);

    Livewire::test(AdminLogin::class)
        ->fillForm(['email' => $user->email, 'password' => 'password'])
        ->call('authenticate')
        ->assertHasNoErrors()
        ->assertRedirect(Filament::getUrl());
    $this->assertAuthenticatedAs($user);
    $this->get('/admin')->assertOk();
    expect($user->fresh()->only(array_keys($before)))->toEqual($before);

    if (! $enrolled) {
        $this->get(route('platform.overview'))->assertForbidden();
    }
})->with([false, true]);

it('redirects the retired admin authenticator setup URL to the dashboard', function () {
    $user = bootstrapInternalAdmin();
    $user->markEmailAsVerified();
    $this->actingAs($user)->get('/admin/multi-factor-authentication/set-up')->assertRedirect('/admin');
    $this->get('/admin')->assertOk();
});

it('denies ordinary users revoked roles expired roles and incorrect passwords at admin login', function (string $scenario) {
    $user = $scenario === 'ordinary' ? User::factory()->create() : bootstrapInternalAdmin();
    if (in_array($scenario, ['revoked', 'expired'])) {
        $user->platformRoleAssignments()->update($scenario === 'revoked'
            ? ['revoked_at' => now()]
            : ['expires_at' => now()->subMinute()]);
    }
    Livewire::test(AdminLogin::class)
        ->fillForm(['email' => $user->email, 'password' => $scenario === 'password' ? 'wrong-password' : 'password'])
        ->call('authenticate')
        ->assertHasErrors(['data.email']);
    $this->assertGuest();
    $this->actingAs($user)->get('/admin/email-verification/prompt')
        ->assertStatus($scenario === 'password' ? 200 : 403);
})->with(['ordinary', 'revoked', 'expired', 'password']);

it('expires internal administration sessions and returns to admin login without requiring MFA', function (string $expiry) {
    $user = bootstrapInternalAdmin();
    $user->markEmailAsVerified();
    $this->actingAs($user)->withSession([
        'platform_session_started_at' => ($expiry === 'absolute' ? now()->subHours(9) : now()->subHour())->timestamp,
        'platform_session_last_used_at' => ($expiry === 'idle' ? now()->subMinutes(16) : now())->timestamp,
    ])->get('/admin')->assertRedirect(Filament::getLoginUrl());
    $this->assertGuest();
})->with(['idle', 'absolute']);

it('shows admin sign-in to a signed-in salon user without granting admin access', function () {
    $owner = User::factory()->create();
    $this->actingAs($owner)->get('/admin/login')->assertOk()->assertSee('Sign in');
    $this->assertAuthenticatedAs($owner);
    $this->get('/admin')->assertForbidden();
});

it('returns a signed-in administrator to the panel despite an old salon intended URL', function () {
    $user = bootstrapInternalAdmin();
    $this->actingAs($user)->withSession(['url.intended' => url('/dashboard')])
        ->get('/admin/login')->assertRedirect(Filament::getUrl());
    $this->get('/admin')->assertRedirect(Filament::getEmailVerificationPromptUrl());
});
