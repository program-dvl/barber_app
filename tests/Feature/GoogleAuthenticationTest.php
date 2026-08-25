<?php

namespace Tests\Feature;

use App\Domain\Billing\Models\OwnerRegistrationIntent;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Jetstream\Jetstream;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class GoogleAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.google.client_id' => 'google-client-id',
            'services.google.client_secret' => 'google-client-secret',
            'services.google.redirect' => '/auth/callback/google',
        ]);
    }

    public function test_google_redirect_is_stateful_google_only_and_remembers_signup_intent(): void
    {
        $provider = Mockery::mock();
        $provider->shouldReceive('with')->once()->with(['prompt' => 'select_account'])->andReturnSelf();
        $provider->shouldReceive('redirect')->once()->andReturn(new RedirectResponse('https://accounts.google.test/oauth'));
        Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);

        $this->get(route('auth.google.redirect', ['intent' => 'register']))
            ->assertRedirect('https://accounts.google.test/oauth')
            ->assertSessionHas('auth.google.context.intent', 'register');

    }

    public function test_existing_user_can_sign_in_and_google_identity_is_linked_without_storing_oauth_token(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);
        $this->mockGoogleUser('google-owner-1', 'owner@example.com', 'Salon Owner');

        $this->withSession(['auth.google.context' => ['intent' => 'login', 'started_at' => now()->timestamp]])
            ->get(route('auth.google.callback'))
            ->assertRedirect(RouteServiceProvider::HOME);

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('social_accounts', [
            'user_id' => $user->getKey(),
            'provider' => 'google',
            'account_id' => 'google-owner-1',
            'token' => null,
        ]);
    }

    public function test_verified_google_identity_completes_an_existing_pending_owner_onboarding_once(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'pending@example.com']);
        OwnerRegistrationIntent::query()->create([
            'user_id' => $user->getKey(),
            'business_name' => 'Pending Studio',
            'status' => 'pending',
        ]);
        $this->mockGoogleUser('google-pending-1', 'pending@example.com', 'Pending Owner');

        $this->get(route('auth.google.callback'))->assertRedirect(RouteServiceProvider::HOME);

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->assertDatabaseHas('businesses', ['name' => 'Pending Studio']);
        $this->assertDatabaseCount('businesses', 1);
        $this->assertDatabaseCount('memberships', 1);
        $this->assertDatabaseCount('business_subscriptions', 1);
    }

    public function test_new_google_user_finishes_business_details_before_verified_owner_workspace_is_created(): void
    {
        $this->mockGoogleUser('google-new-1', 'new.owner@example.com', 'New Owner');

        $this->withSession(['auth.google.context' => ['intent' => 'register', 'selection' => null, 'started_at' => now()->timestamp]])
            ->get(route('auth.google.callback'))
            ->assertRedirect(route('register'))
            ->assertSessionHas('auth.google.pending_registration.email', 'new.owner@example.com');

        $this->get(route('register'))->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Register')
            ->where('googleAuth.pending_registration.email', 'new.owner@example.com')
            ->where('googleAuth.pending_registration.name', 'New Owner')
        );

        $response = $this->post(route('auth.google.register'), [
            'name' => 'New Owner',
            'business_name' => 'North and Main Studio',
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature(),
        ]);

        $user = User::query()->where('email', 'new.owner@example.com')->firstOrFail();
        $response->assertRedirect(RouteServiceProvider::HOME);
        $this->assertAuthenticatedAs($user);
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertDatabaseHas('social_accounts', ['user_id' => $user->id, 'provider' => 'google', 'account_id' => 'google-new-1']);
        $this->assertDatabaseHas('owner_registration_intents', ['user_id' => $user->id, 'business_name' => 'North and Main Studio', 'status' => 'completed']);
        $this->assertDatabaseHas('businesses', ['name' => 'North and Main Studio']);
        $this->assertDatabaseCount('businesses', 1);
    }

    public function test_google_callback_rejects_unverified_email_claims(): void
    {
        $this->mockGoogleUser('google-unverified-1', 'unverified@example.com', 'Unverified User', false);

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('google');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_google_cannot_replace_an_existing_account_link_for_the_same_email(): void
    {
        $user = User::factory()->create(['email' => 'linked@example.com']);
        $user->socialAccounts()->create([
            'provider' => 'google',
            'account_id' => 'original-google-account',
            'token' => null,
        ]);
        $this->mockGoogleUser('different-google-account', 'linked@example.com', 'Linked User');

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('google');

        $this->assertGuest();
        $this->assertDatabaseCount('social_accounts', 1);
    }

    public function test_google_sign_in_preserves_the_existing_two_factor_challenge(): void
    {
        $user = User::factory()->create([
            'email' => 'two-factor@example.com',
            'two_factor_secret' => 'encrypted-secret-placeholder',
            'two_factor_confirmed_at' => now(),
        ]);
        $this->mockGoogleUser('google-two-factor-1', 'two-factor@example.com', 'Protected User');

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('two-factor.login'))
            ->assertSessionHas('login.id', $user->id);

        $this->assertGuest();
    }

    private function mockGoogleUser(string $id, string $email, string $name, bool $verified = true): void
    {
        $googleUser = (new SocialiteUser)->setRaw([
            'sub' => $id,
            'email' => $email,
            'email_verified' => $verified,
            'name' => $name,
        ])->map([
            'id' => $id,
            'email' => $email,
            'name' => $name,
        ]);
        $provider = Mockery::mock();
        $provider->shouldReceive('user')->once()->andReturn($googleUser);
        Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);
    }
}
