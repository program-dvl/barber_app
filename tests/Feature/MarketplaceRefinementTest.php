<?php

use App\Domain\Marketplace\Models\Customer;
use App\Domain\Marketplace\Models\CustomerBooking;
use App\Domain\Marketplace\Services\BookingOwnership;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\Location;
use App\Domain\SchedulingOperations\Models\Appointment;
use App\Mail\Marketplace\SignInLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;

uses(RefreshDatabase::class);
beforeEach(function () {
    $this->withoutVite();
    config(['inertia.ssr.enabled' => false, 'marketplace-growth.enabled' => false]);
});
function refinedCustomer(string $email = 'customer@example.test'): Customer
{
    return Customer::create(['email' => $email, 'name' => 'Before Name', 'email_verified_at' => now(), 'terms_version' => 'marketplace-2026-11-08', 'terms_accepted_at' => now()]);
}
function refinedAs($test, Customer $customer)
{
    $test->actingAs($customer, 'marketplace');
    Auth::shouldUse('web');

    return $test;
}
function refinedVisit(string $email, ?Business $business = null): Appointment
{
    $business ??= Business::factory()->create(['booking_slug' => 'refined-'.Str::lower(Str::ulid()), 'configuration_published_at' => now(), 'online_booking_enabled' => true]);
    $location = Location::factory()->create(['business_id' => $business->id, 'address' => 'Toronto', 'status' => 'active', 'is_active' => true]);

    return Appointment::create(['business_id' => $business->id, 'location_id' => $location->id, 'client_name' => 'Private Client', 'client_email' => $email, 'client_mobile' => '+14165550199', 'internal_notes' => 'Private salon-only note', 'starts_at_utc' => now()->addDays(2), 'ends_at_utc' => now()->addDays(2)->addHour(), 'time_zone' => 'America/Toronto', 'local_starts_at' => now()->addDays(2)->format('Y-m-d H:i:s'), 'local_ends_at' => now()->addDays(2)->addHour()->format('Y-m-d H:i:s'), 'status' => 'confirmed', 'source' => 'phone', 'price_minor' => 6500, 'currency_code' => 'CAD', 'idempotency_key' => (string) Str::uuid(), 'request_hash' => hash('sha256', (string) Str::uuid())]);
}
function refinedGoogle(string $id, string $email, bool $verified = true): void
{
    $google = (new GoogleUser)->map(['id' => $id, 'name' => 'Google Customer', 'email' => $email]);
    $google->user = ['email_verified' => $verified];
    Socialite::shouldReceive('driver')->with('google')->andReturn($provider = Mockery::mock());
    $provider->shouldReceive('user')->once()->andReturn($google);
}

it('keeps the customer signed in after saving a profile from a stale confirmation referrer', function () {
    $customer = refinedCustomer();
    refinedAs($this, $customer)->from('/marketplace/sign-in/confirm')->patch('/marketplace/profile', ['name' => 'Jordan New', 'phone' => '+14165550199'])->assertRedirect('/marketplace/profile')->assertSessionHasNoErrors();
    $this->assertAuthenticatedAs($customer, 'marketplace');
    $this->get('/marketplace/profile')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Marketplace/Profile')->where('profile.name', 'Jordan New'));
    $this->get('/marketplace/sign-in/confirm')->assertRedirect('/marketplace/account');
    $this->get('/marketplace/sign-in/link/'.str_repeat('a', 64))->assertRedirect('/marketplace/account');
    $this->get('/marketplace/sign-in')->assertRedirect('/marketplace/account');
});

it('automatically adds same verified email bookings across salons without exposing other records', function () {
    $one = refinedVisit('CUSTOMER@example.test');
    $two = refinedVisit('customer@example.test');
    refinedVisit('someone@example.test');
    $c = refinedCustomer();
    refinedAs($this, $c)->get('/marketplace/account')->assertInertia(fn (Assert $p) => $p->has('bookings.data', 2)->missing('bookings.data.0.internal_notes')->missing('bookings.data.0.client_mobile'));
    $this->get('/marketplace/account')->assertOk();
    expect(CustomerBooking::count())->toBe(2);
    $this->get('/marketplace/account/bookings/'.$two->public_id.'/messages')->assertOk();
    $other = refinedCustomer('someone@example.test');
    refinedAs($this, $other)->get('/marketplace/account/bookings/'.$one->public_id.'/messages')->assertNotFound();
});

it('never reassigns an already linked appointment to a different account', function () {
    $a = refinedVisit('customer@example.test');
    $original = refinedCustomer();
    app(BookingOwnership::class)->attach($original, $a);
    $a->update(['client_email' => 'new@example.test']);
    $new = refinedCustomer('new@example.test');
    refinedAs($this, $new)->get('/marketplace/account')->assertInertia(fn (Assert $p) => $p->has('bookings.data', 0));
    expect(CustomerBooking::first()->customer_id)->toBe($original->id);
});

it('shows active published salons without enrollment while respecting owner pauses and location searches', function () {
    $a = refinedVisit('customer@example.test');
    $this->get('/marketplace')->assertInertia(fn (Assert $p) => $p->has('results.data', 1));
    $this->get('/marketplace?location=London')->assertInertia(fn (Assert $p) => $p->has('results.data', 0));
    $a->business->forceFill(['marketplace_consented_at' => now(), 'marketplace_listed' => false])->save();
    $this->get('/marketplace')->assertInertia(fn (Assert $p) => $p->has('results.data', 0));
});

it('persists marketplace login using a separate remember cookie and keeps Business sign in', function () {
    $c = refinedCustomer();
    $owner = User::factory()->create();
    $this->actingAs($owner);
    $token = bin2hex(random_bytes(32));
    DB::table('marketplace_login_links')->insert(['email' => $c->email, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addMinutes(15), 'created_at' => now(), 'updated_at' => now()]);
    $response = $this->withSession(['marketplace_pending_login' => $token])->post('/marketplace/sign-in/confirm');
    $response->assertRedirect('/marketplace/account');
    expect($c->fresh()->remember_token)->not->toBeNull();
    $response->assertCookie(Auth::guard('marketplace')->getRecallerName());
    $this->assertAuthenticatedAs($owner, 'web');
    $this->post('/marketplace/sign-out')->assertRedirect('/marketplace');
    $this->assertGuest('marketplace');
    $this->assertAuthenticatedAs($owner, 'web');
});

it('routes a verified Google customer to Marketplace without creating a Business user', function () {
    $c = refinedCustomer();
    refinedGoogle('google-customer', $c->email);
    $this->withSession(['auth.google.context' => ['audience' => 'marketplace', 'started_at' => now()->timestamp]])->get('/auth/callback/google')->assertRedirect('/marketplace/account');
    $this->assertAuthenticatedAs($c, 'marketplace');
    $this->assertGuest('web');
    expect(User::count())->toBe(0)->and($c->fresh()->google_id)->toBe('google-customer');
});

it('requires terms for a new Google customer without sending an email', function () {
    refinedGoogle('new-google', 'new@example.test');
    $this->withSession(['auth.google.context' => ['audience' => 'marketplace', 'started_at' => now()->timestamp]])->get('/auth/callback/google')->assertRedirect('/marketplace/sign-in/confirm');
    expect(Customer::count())->toBe(0);
    $this->get('/marketplace/sign-in/confirm')->assertInertia(fn (Assert $p) => $p->where('needsTerms', true));
    $this->post('/marketplace/sign-in/confirm', [])->assertSessionHasErrors('accepted');
    $this->post('/marketplace/sign-in/confirm', ['accepted' => true])->assertRedirect('/marketplace/account');
    expect(Customer::first()->google_id)->toBe('new-google')->and(Customer::first()->name)->toBe('Google Customer');
});

it('rejects an unverified Google email and preserves email sign in as recovery', function () {
    refinedGoogle('unverified', 'no@example.test', false);
    $this->withSession(['auth.google.context' => ['audience' => 'marketplace', 'started_at' => now()->timestamp]])->get('/auth/callback/google')->assertRedirect('/marketplace/sign-in')->assertSessionHasErrors('google');
    expect(Customer::count())->toBe(0);
    $this->assertGuest('marketplace');
});

it('uses the existing shared Google callback even while a Business owner is signed in', function () {
    $owner = User::factory()->create();
    $this->actingAs($owner);
    $c = refinedCustomer();
    refinedGoogle('independent-google', $c->email);
    $this->withSession(['auth.google.context' => ['audience' => 'marketplace', 'started_at' => now()->timestamp]])->get('/auth/callback/google')->assertRedirect('/marketplace/account');
    $this->assertAuthenticatedAs($owner, 'web');
    $this->assertAuthenticatedAs($c, 'marketplace');
});

it('reuses the transactional email shell with the Marketplace palette', function () {
    $html = (new SignInLink('https://example.test/private-link'))->render();
    expect($html)->toContain('header-shell', 'inner-body', 'Marketplace', '15 minutes', 'https://example.test/private-link', '#202d29', 'data:image/png;base64,', 'Clipperdesk')->not->toContain('Repair credentials', 'clipperdesk-mark-CDKNGozz.svg');
});
