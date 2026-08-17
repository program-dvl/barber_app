<?php

namespace Tests\Feature;

use App\Domain\Billing\Enums\SubscriptionStatus;
use App\Domain\Billing\Models\BusinessSubscription;
use App\Domain\Billing\Models\OwnerRegistrationIntent;
use App\Domain\PlatformAccess\Enums\StarterRole;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\Membership;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Laravel\Fortify\Features;
use Laravel\Jetstream\Jetstream;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        if (! Features::enabled(Features::registration())) {
            $this->markTestSkipped('Registration support is not enabled.');
        }

        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_registration_screen_cannot_be_rendered_if_support_is_disabled(): void
    {
        if (Features::enabled(Features::registration())) {
            $this->markTestSkipped('Registration support is enabled.');
        }

        $response = $this->get('/register');

        $response->assertStatus(404);
    }

    public function test_new_users_can_register(): void
    {
        if (! Features::enabled(Features::registration())) {
            $this->markTestSkipped('Registration support is not enabled.');
        }

        $response = $this->post('/register', [
            'name' => 'Test User',
            'business_name' => 'Test Barber Shop',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature(),
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(RouteServiceProvider::HOME);
    }

    public function test_owner_can_register_verify_complete_tenant_onboarding_and_sign_back_in(): void
    {
        if (! Features::enabled(Features::registration()) || ! Features::enabled(Features::emailVerification())) {
            $this->markTestSkipped('Registration and email verification support must be enabled.');
        }

        $registration = $this->post('/register', [
            'name' => 'Salon Owner',
            'business_name' => 'Kshem Salon',
            'email' => 'owner@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature(),
        ]);

        $user = User::query()->where('email', 'owner@example.com')->firstOrFail();
        $registration->assertRedirect(RouteServiceProvider::HOME);
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('owner_registration_intents', [
            'user_id' => $user->getKey(),
            'business_name' => 'Kshem Salon',
            'status' => 'pending',
        ]);
        $this->assertDatabaseCount('businesses', 0);

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->getKey(), 'hash' => sha1($user->email)]
        );

        $this->get($verificationUrl)->assertRedirect(RouteServiceProvider::HOME.'?verified=1');
        $this->get($verificationUrl);

        $intent = OwnerRegistrationIntent::query()->whereBelongsTo($user)->firstOrFail();
        $business = Business::query()->findOrFail($intent->business_id);
        $membership = Membership::query()->whereBelongsTo($business)->whereBelongsTo($user)->firstOrFail();
        $subscription = BusinessSubscription::query()->whereBelongsTo($business)->firstOrFail();

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->assertSame('completed', $intent->status);
        $roleName = DB::table('business_model_has_roles')
            ->join('business_roles', 'business_roles.id', '=', 'business_model_has_roles.role_id')
            ->where('business_model_has_roles.business_id', $business->getKey())
            ->where('business_model_has_roles.model_type', Membership::class)
            ->where('business_model_has_roles.model_id', $membership->getKey())
            ->value('business_roles.name');

        $this->assertSame(StarterRole::Owner->value, $roleName);
        $this->assertSame(SubscriptionStatus::Trialing, $subscription->status);
        $this->assertNotNull($subscription->trial_started_at);
        $this->assertTrue($subscription->trial_ends_at->greaterThan($subscription->trial_started_at));
        $this->assertDatabaseCount('businesses', 1);
        $this->assertDatabaseCount('memberships', 1);
        $this->assertDatabaseCount('business_subscriptions', 1);

        $this->post('/logout');
        $this->assertGuest();

        $login = $this->post('/login', [
            'email' => 'owner@example.com',
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        $login->assertRedirect(RouteServiceProvider::HOME);
    }
}
