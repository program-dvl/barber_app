<?php

use App\Domain\Marketplace\Models\Customer;
use App\Domain\Marketplace\Models\CustomerBooking;
use App\Domain\Marketplace\Models\Message;
use App\Domain\Marketplace\Services\BookingMessages;
use App\Domain\Marketplace\Services\BookingOwnership;
use App\Domain\PlatformAccess\Enums\PlatformRole;
use App\Domain\PlatformAccess\Models\AuditEvent;
use App\Domain\PlatformAccess\Models\PlatformRoleAssignment;
use App\Domain\PublicBooking\Models\PublicBookingFlow;
use App\Domain\PublicBooking\Services\AppointmentSelfService;
use App\Domain\PublicBooking\Services\SecureAppointmentLinkService;
use App\Domain\SchedulingOperations\Models\Appointment;
use App\Mail\Marketplace\SignInLink;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

require_once __DIR__.'/../Support/MarketplaceFixture.php';
uses(RefreshDatabase::class);

beforeEach(function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-08T10:23:45.784321+05:30'));
    config(['inertia.ssr.enabled' => false, 'marketplace-growth.enabled' => false]);
    Mail::fake();
    $this->withoutVite();
});
afterEach(fn () => CarbonImmutable::setTestNow());
function marketplaceAs($test, Customer $customer)
{
    $test->actingAs($customer, 'marketplace');
    Auth::shouldUse('web');

    return $test;
}
function marketCustomer(string $email = 'jordan@example.test'): Customer
{
    return Customer::query()->create(['email' => $email, 'name' => 'Jordan', 'email_verified_at' => now()]);
}
function marketVisit(array $path, string $email = 'jordan@example.test'): Appointment
{
    return Appointment::query()->create(['business_id' => $path['business']->id, 'location_id' => $path['location']->id, 'status' => 'confirmed', 'source' => 'online', 'client_name' => 'Jordan', 'client_mobile' => '+919999999999', 'client_email' => $email, 'starts_at_utc' => now()->addDays(2), 'ends_at_utc' => now()->addDays(2)->addHour(), 'local_starts_at' => '2026-11-10 10:23:45', 'local_ends_at' => '2026-11-10 11:23:45', 'time_zone' => 'Asia/Kolkata', 'price_minor' => 3500, 'currency_code' => 'INR', 'idempotency_key' => (string) Str::uuid(), 'request_hash' => hash('sha256', (string) Str::uuid())]);
}

it('creates only verified customer accounts with single use sign in links and no business access', function () {
    $this->from('/marketplace/sign-in')->post('/marketplace/sign-in', ['email' => 'Jordan@Example.test'])->assertRedirect('/marketplace/sign-in');
    Mail::assertSent(SignInLink::class);
    expect(Customer::count())->toBe(0);
    $mail = Mail::sent(SignInLink::class)->first();
    $token = basename($mail->signInUrl);
    expect(DB::table('marketplace_login_links')->value('token_hash'))->toBe(hash('sha256', $token));
    $this->get('/marketplace/sign-in/link/'.$token)->assertRedirect('/marketplace/sign-in/confirm');
    expect(DB::table('marketplace_login_links')->value('used_at'))->toBeNull();
    $this->post('/marketplace/sign-in/confirm', ['accepted' => true])->assertRedirect('/marketplace/account');
    $this->assertAuthenticated('marketplace');
    $this->assertGuest('web');
    expect(Customer::first()->email)->toBe('jordan@example.test');
    $this->post('/marketplace/sign-out')->assertRedirect('/marketplace');
    $this->get('/marketplace/sign-in/link/'.$token);
    $this->post('/marketplace/sign-in/confirm', ['accepted' => true])->assertSessionHasErrors('email');
    expect(Customer::count())->toBe(1);
});

it('rejects expired sign in and protects account routes', function () {
    $this->get('/marketplace/account')->assertRedirect('/marketplace/sign-in');
    $token = bin2hex(random_bytes(32));
    DB::table('marketplace_login_links')->insert(['email' => 'expired@example.test', 'token_hash' => hash('sha256', $token), 'expires_at' => now()->subSecond(), 'created_at' => now(), 'updated_at' => now()]);
    $this->get('/marketplace/sign-in/link/'.$token);
    $this->post('/marketplace/sign-in/confirm', ['accepted' => true])->assertSessionHasErrors('email');
    $this->assertGuest('marketplace');
});

it('throttles sign in email and invalidates a link after delivery failure', function () {
    $this->post('/marketplace/sign-in', ['email' => 'wait@example.test']);
    $this->post('/marketplace/sign-in', ['email' => 'wait@example.test'])->assertSessionHasErrors('email');
    Mail::assertSentCount(1);
    Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('mail unavailable'));
    $this->post('/marketplace/sign-in', ['email' => 'outage@example.test'])->assertSessionHasErrors('email');
    expect(DB::table('marketplace_login_links')->where('email', 'outage@example.test')->value('used_at'))->not->toBeNull();
});

it('requires a private link and matching verified email to claim a booking with exact replay', function () {
    $path = marketplaceBookablePath();
    $a = marketVisit($path);
    $customer = marketCustomer();
    $token = app(SecureAppointmentLinkService::class)->issue($a, 'view')['token'];
    marketplaceAs($this, $customer)->post('/marketplace/account/bookings', ['booking_link' => 'https://example.test/appointments/secure/'.$token])->assertSessionHasNoErrors();
    $this->post('/marketplace/account/bookings', ['booking_link' => $token])->assertSessionHasNoErrors();
    expect(CustomerBooking::count())->toBe(1);
    $other = marketCustomer('other@example.test');
    marketplaceAs($this, $other)->post('/marketplace/account/bookings', ['booking_link' => $token])->assertSessionHasErrors('booking');
    $this->get('/marketplace/account')->assertInertia(fn (Assert $p) => $p->has('bookings.data', 0));
    $this->get('/marketplace/account/bookings/'.$a->public_id.'/messages')->assertNotFound();
    $this->post('/marketplace/account/bookings/'.$a->public_id.'/manage')->assertSessionHasErrors('marketplace');
});

it('keeps messages private and makes duplicate sending idempotent', function () {
    $path = marketplaceBookablePath();
    $a = marketVisit($path);
    $customer = marketCustomer();
    $b = app(BookingOwnership::class)->attach($customer, $a);
    $url = '/marketplace/account/bookings/'.$a->public_id.'/messages';
    $key = (string) Str::uuid();
    marketplaceAs($this, $customer)->from($url)->post($url, ['body' => 'Please use a quiet chair.', 'idempotency_key' => $key])->assertSessionHasNoErrors();
    $this->post($url, ['body' => 'Please use a quiet chair.', 'idempotency_key' => $key])->assertSessionHasNoErrors();
    expect(Message::count())->toBe(1)->and(DB::table('marketplace_messages')->value('body'))->not->toContain('quiet chair');
    $this->post($url, ['body' => 'Changed replay', 'idempotency_key' => $key])->assertSessionHasErrors('body');
    $this->patch($url, ['paused' => true])->assertSessionHasNoErrors();
    $this->post($url, ['body' => 'Another message', 'idempotency_key' => (string) Str::uuid()])->assertSessionHasErrors('body');
    expect(Message::count())->toBe(1);
    $this->patch($url, ['paused' => false]);
    $this->post($url, ['body' => 'Another message', 'idempotency_key' => (string) Str::uuid()])->assertSessionHasNoErrors();
    expect(Message::count())->toBe(2);
});

it('lets only authorized salon staff see and reply to a booking conversation', function () {
    $path = marketplaceBookablePath();
    $a = marketVisit($path);
    $b = app(BookingOwnership::class)->attach(marketCustomer(), $a);
    $url = route('business.marketplace.thread', [$path['business'], $a->public_id]);
    $this->actingAs($path['user'])->get($url)->assertOk();
    $this->post(route('business.marketplace.message', [$path['business'], $a->public_id]), ['body' => 'Of course. See you soon.', 'idempotency_key' => (string) Str::uuid()])->assertSessionHasNoErrors();
    expect(Message::first()->sender)->toBe('business');
    marketplaceAs($this, $b->customer)->get('/marketplace/account/bookings/'.$a->public_id.'/messages')->assertOk();
    expect(Message::first()->read_at)->not->toBeNull();
    $other = marketplaceBookablePath();
    $this->actingAs($other['user'])->get(route('business.marketplace.thread', [$other['business'], $a->public_id]))->assertNotFound();
});

it('lets owners explicitly list and pause without disabling direct bookings', function () {
    $path = marketplaceBookablePath();
    $url = route('business.marketplace.listing', $path['business']);
    $this->actingAs($path['user'])->patch($url, ['listed' => true, 'version' => 1, 'consent' => true])->assertSessionHasNoErrors();
    expect($path['business']->fresh()->marketplace_listed)->toBe(1);
    $this->get('/marketplace/salons/'.$path['business']->booking_slug)->assertOk();
    $this->patch($url, ['listed' => false, 'version' => 1])->assertSessionHasErrors('listed');
    $this->patch($url, ['listed' => false, 'version' => 2])->assertSessionHasNoErrors();
    $this->get('/marketplace/salons/'.$path['business']->booking_slug)->assertNotFound();
    expect($path['business']->fresh()->online_booking_enabled)->toBeTrue();
});

it('saves favourites without leaking hidden salons and records concerns encrypted', function () {
    $path = marketplaceBookablePath();
    $path['business']->forceFill(['marketplace_listed' => true])->save();
    $customer = marketCustomer();
    $a = marketVisit($path);
    app(BookingOwnership::class)->attach($customer, $a);
    $url = '/marketplace/salons/'.$path['business']->booking_slug.'/favourite';
    marketplaceAs($this, $customer)->post($url, ['saved' => true]);
    $this->post($url, ['saved' => true]);
    expect(DB::table('marketplace_favourites')->count())->toBe(1);
    $this->post('/marketplace/account/bookings/'.$a->public_id.'/report', ['reason' => 'unwanted_messages', 'details' => 'Please help with unwanted messages.'])->assertSessionHasNoErrors();
    expect(DB::table('marketplace_reports')->value('details'))->not->toContain('unwanted messages');
    $path['business']->forceFill(['marketplace_listed' => false, 'marketplace_consented_at' => now()])->save();
    $this->get('/marketplace/account')->assertInertia(fn (Assert $p) => $p->has('favourites', 0));
});

it('books through canonical availability with automatic account linking and manual payments', function () {
    $path = marketplaceBookablePath();
    $path['business']->forceFill(['marketplace_listed' => true])->save();
    $customer = marketCustomer();
    $slug = $path['business']->booking_slug;
    $base = '/marketplace/salons/'.$slug.'/booking/';
    $started = marketplaceAs($this, $customer)->postJson($base.'start')->assertOk()->json();
    expect(PublicBookingFlow::first()->marketplace_customer_id)->toBe($customer->id);
    $credentials = ['flow' => $started['flow'], 'secret' => $started['secret']];
    $selection = ['location' => $path['location']->public_id, 'services' => [$path['service']->public_id], 'staff' => null, 'client_eligibility' => 'auto'];
    $slots = $this->postJson($base.'search', [...$credentials, ...$selection, 'from_date' => '2026-11-10', 'until_date' => '2026-11-10'])->assertOk()->json('slots');
    expect($slots)->not->toBeEmpty();
    $this->postJson($base.'hold', [...$credentials, ...$selection, 'starts_at' => $slots[0]['starts_at_utc'], 'idempotency_key' => (string) Str::uuid()])->assertOk();
    $details = ['client_name' => 'Jordan', 'client_email' => $customer->email, 'client_mobile' => '+919999999999'];
    $this->postJson($base.'details', [...$credentials, ...$details])->assertOk();
    $confirm = [...$credentials, ...$details, 'policy_accepted' => true, 'communication_preferences' => ['email'], 'idempotency_key' => (string) Str::uuid()];
    $this->postJson($base.'confirm', $confirm)->assertOk();
    $this->postJson($base.'confirm', $confirm)->assertOk();
    expect(Appointment::count())->toBe(1)->and(CustomerBooking::count())->toBe(1);
    $this->get('/marketplace/account')->assertInertia(fn (Assert $p) => $p->has('bookings.data', 1));
    $a = Appointment::first();
    $link = app(SecureAppointmentLinkService::class)->issue($a, 'reschedule');
    $replacement = app(AppointmentSelfService::class)->reschedule(app(SecureAppointmentLinkService::class)->resolve($link['token'], 'reschedule'), CarbonImmutable::parse('2026-11-11T10:00:00+05:30')->utc(), 'market-reschedule');
    expect(CustomerBooking::where('appointment_id', $replacement->id)->value('customer_id'))->toBe($customer->id);
});

it('filters nearby salons using a short-lived precise session point', function () {
    $path = marketplaceBookablePath();
    $path['business']->forceFill(['marketplace_listed' => true])->save();
    $path['location']->forceFill(['address' => 'Toronto', 'entrance_latitude' => 43.65, 'entrance_longitude' => -79.38])->save();
    $this->post('/marketplace/nearby', ['latitude' => 43.65123456, 'longitude' => -79.38123456])->assertRedirect('/marketplace?nearby=1')->assertSessionHas('marketplace_nearby', [43.65123456, -79.38123456]);
    $this->get('/marketplace?nearby=1')->assertInertia(fn (Assert $p) => $p->has('results.data', 1)->where('filters.nearby', true)->where('results.data.0.distance', 0.2));
    $this->post('/marketplace/nearby', ['latitude' => 0, 'longitude' => 0]);
    $this->get('/marketplace?nearby=1')->assertInertia(fn (Assert $p) => $p->has('results.data', 0));
    $this->delete('/marketplace/nearby')->assertSessionMissing('marketplace_nearby');
});

it('checks starting prices and dates through the canonical public catalogue', function () {
    $path = marketplaceBookablePath();
    $path['business']->forceFill(['marketplace_listed' => true])->save();
    $this->get('/marketplace?max_price=34&currency=INR')->assertInertia(fn (Assert $p) => $p->has('results.data', 0));
    $this->get('/marketplace?max_price=35&currency=INR&date=2026-11-10')->assertInertia(fn (Assert $p) => $p->has('results.data', 1)->has('results.data.0.next_time.starts_at_utc'));
    $path['service']->staffAssignments()->update(['price_minor' => 5500]);
    $this->get('/marketplace?max_price=35&currency=INR')->assertInertia(fn (Assert $p) => $p->has('results.data', 0));
    $this->get('/marketplace?max_price=55&currency=USD')->assertInertia(fn (Assert $p) => $p->has('results.data', 0));
    $this->getJson('/marketplace?date=2026-02-31')->assertUnprocessable();
});

it('keeps deposits protected and cannot collect a booking payment', function () {
    $path = marketplaceBookablePath(true);
    $path['business']->forceFill(['marketplace_listed' => true])->save();
    $customer = marketCustomer();
    $base = '/marketplace/salons/'.$path['business']->booking_slug.'/booking/';
    $started = marketplaceAs($this, $customer)->postJson($base.'start')->assertOk()->json();
    $creds = ['flow' => $started['flow'], 'secret' => $started['secret']];
    $select = ['location' => $path['location']->public_id, 'services' => [$path['service']->public_id], 'staff' => null, 'client_eligibility' => 'auto'];
    $slots = $this->postJson($base.'search', [...$creds, ...$select, 'from_date' => '2026-11-10', 'until_date' => '2026-11-10'])->assertOk()->json('slots');
    $this->postJson($base.'hold', [...$creds, ...$select, 'starts_at' => $slots[0]['starts_at_utc'], 'idempotency_key' => (string) Str::uuid()])->assertOk();
    $details = ['client_name' => 'Jordan', 'client_email' => $customer->email, 'client_mobile' => '+919999999999'];
    $this->postJson($base.'details', [...$creds, ...$details])->assertOk();
    $this->postJson($base.'confirm', [...$creds, ...$details, 'policy_accepted' => true, 'idempotency_key' => (string) Str::uuid()])->assertConflict();
    expect(Appointment::count())->toBe(0)->and(CustomerBooking::count())->toBe(0);
});

it('preserves the business sign in when the customer signs out', function () {
    $path = marketplaceBookablePath();
    $this->actingAs($path['user']);
    marketplaceAs($this, marketCustomer());
    $this->post('/marketplace/sign-out')->assertRedirect('/marketplace');
    $this->assertAuthenticatedAs($path['user'], 'web');
    $this->assertGuest('marketplace');
});

it('stops mutations on persistent failures and gives safe cooldowns', function () {
    $path = marketplaceBookablePath();
    $a = marketVisit($path);
    $c = marketCustomer();
    app(BookingOwnership::class)->attach($c, $a);
    $url = '/marketplace/account/bookings/'.$a->public_id.'/messages';
    $this->mock(BookingMessages::class, fn ($mock) => $mock->shouldReceive('send')->once()->andThrow(new RuntimeException('private platform diagnostic')));
    marketplaceAs($this, $c)->from($url)->post($url, ['body' => 'Keep this message', 'idempotency_key' => (string) Str::uuid()])->assertRedirect($url)->assertSessionHasErrors('marketplace')->assertSessionHas('marketplace_blocked', true);
    expect(Message::count())->toBe(0);
});

it('pages message history without exposing another booking and revokes access after contact changes', function () {
    $path = marketplaceBookablePath();
    $a = marketVisit($path);
    $c = marketCustomer();
    $b = app(BookingOwnership::class)->attach($c, $a);
    for ($i = 0; $i < 52; $i++) {
        Message::create(['booking_id' => $b->id, 'sender' => 'business', 'staff_user_id' => $path['user']->id, 'body' => 'Hello '.$i, 'idempotency_key' => (string) Str::uuid()]);
    }
    $url = '/marketplace/account/bookings/'.$a->public_id.'/messages';
    $response = marketplaceAs($this, $c)->get($url)->assertInertia(fn (Assert $p) => $p->has('messages', 50)->has('olderUrl'));
    $cursor = Message::orderByDesc('public_id')->skip(49)->first()->public_id;
    $this->get($url.'?before='.$cursor)->assertInertia(fn (Assert $p) => $p->has('messages', 2)->where('olderUrl', null));
    $a->update(['client_email' => 'changed@example.test']);
    $this->get($url)->assertNotFound();
});

it('never shares a customer email with unrelated public or business pages', function () {
    marketplaceAs($this, marketCustomer())->get('/')->assertInertia(fn (Assert $p) => $p->where('marketplaceCustomer', null));
    $this->get('/marketplace/account')->assertInertia(fn (Assert $p) => $p->where('marketplaceCustomer.email', 'jordan@example.test'));
});

it('requires terms acceptance before creating a customer and remembers the accepted version', function () {
    $token = bin2hex(random_bytes(32));
    DB::table('marketplace_login_links')->insert(['email' => 'terms@example.test', 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addMinutes(15), 'created_at' => now(), 'updated_at' => now()]);
    $this->get('/marketplace/sign-in/link/'.$token);
    $this->post('/marketplace/sign-in/confirm', [])->assertSessionHasErrors('accepted');
    expect(Customer::count())->toBe(0);
    $this->post('/marketplace/sign-in/confirm', ['accepted' => true])->assertRedirect('/marketplace/account');
    expect(Customer::first()->terms_version)->toBe('marketplace-2026-11-08')->and(Customer::first()->terms_accepted_at)->not->toBeNull();
});

it('audits customer actions without attributing them to a simultaneously signed-in owner', function () {
    $path = marketplaceBookablePath();
    $a = marketVisit($path);
    $customer = marketCustomer();
    $this->actingAs($path['user']);
    marketplaceAs($this, $customer);
    $token = app(SecureAppointmentLinkService::class)->issue($a, 'view')['token'];
    $this->post('/marketplace/account/bookings', ['booking_link' => $token])->assertSessionHasNoErrors();
    $audit = AuditEvent::query()->where('action', 'marketplace.booking.linked')->firstOrFail();
    expect($audit->actor_user_id)->toBeNull()->and($audit->source)->toBe('marketplace_customer')->and($audit->metadata['customer_public_id'])->toBe($customer->public_id);
});

it('allows authorized support to review concerns and prevents salon customers from accessing them', function () {
    $path = marketplaceBookablePath();
    $a = marketVisit($path);
    $customer = marketCustomer();
    app(BookingOwnership::class)->attach($customer, $a);
    marketplaceAs($this, $customer)->post('/marketplace/account/bookings/'.$a->public_id.'/report', ['reason' => 'booking_problem', 'details' => 'Please help with this booking.'])->assertSessionHasNoErrors();
    $operator = User::factory()->create(['email_verified_at' => now(), 'two_factor_confirmed_at' => now()]);
    PlatformRoleAssignment::query()->create(['user_id' => $operator->id, 'role' => PlatformRole::Administrator, 'reason' => 'Marketplace support verification.']);
    $this->actingAs($operator)->get(route('platform.marketplace.reports'))->assertOk()->assertInertia(fn (Assert $p) => $p->has('reports.data', 1)->where('reports.data.0.details', 'Please help with this booking.'));
    $reference = DB::table('marketplace_reports')->value('public_id');
    $this->patch(route('platform.marketplace.reports.update', $reference), ['status' => 'resolved', 'note' => 'Reviewed and contacted the salon.'])->assertSessionHasNoErrors();
    expect(DB::table('marketplace_reports')->value('status'))->toBe('resolved');
    $this->actingAs($path['user'])->get(route('platform.marketplace.reports'))->assertForbidden();
});

it('uses the existing booking page directly for marketplace salons and preserves old entry choices', function () {
    $path = marketplaceBookablePath();
    $slug = $path['business']->booking_slug;
    $choice = ['service' => $path['service']->public_id, 'location' => $path['location']->public_id, 'date' => '2026-11-10'];
    $this->get('/marketplace/salons/'.$slug.'?'.http_build_query($choice))->assertInertia(fn (Assert $p) => $p->component('Booking/Welcome')->where('marketplace', true)->where('initialChoice.service', $choice['service'])->where('initialChoice.location', $choice['location']));
    $this->get('/marketplace/salons/'.$slug.'/book?'.http_build_query($choice))->assertRedirect(route('marketplace.salon', ['slug' => $slug, 'location' => $choice['location'], 'service' => $choice['service'], 'date' => $choice['date']]));
    expect(PublicBookingFlow::count())->toBe(0);
    $path['business']->forceFill(['marketplace_consented_at' => now(), 'marketplace_listed' => false])->save();
    $this->get('/marketplace/salons/'.$slug)->assertNotFound();
});

it('offers only real eligible public services and categories from discoverable salons', function () {
    $path = marketplaceBookablePath();
    $path['service']->update(['name' => 'Balayage colour']);
    $this->getJson('/marketplace/services')->assertOk()->assertExactJson(['services' => [['name' => 'Balayage colour', 'category' => 'Services']]]);
    $path['service']->staffAssignments()->update(['online_visible' => false]);
    $this->getJson('/marketplace/services')->assertExactJson(['services' => []]);
    $path['service']->staffAssignments()->update(['online_visible' => true]);
    $path['business']->forceFill(['marketplace_listed' => false, 'marketplace_consented_at' => now()])->save();
    $this->getJson('/marketplace/services')->assertExactJson(['services' => []]);
});

it('finds cross-specialty salons from their actual service categories and clears expired location searches', function () {
    $path = marketplaceBookablePath();
    $path['business']->update(['business_type' => 'beauty_salon']);
    $path['service']->update(['name' => 'Gel manicure']);
    $this->get('/marketplace?category=nails')->assertInertia(fn (Assert $p) => $p->has('results.data', 1)->where('filters.category', 'nails'));
    $this->get('/marketplace?category=hair')->assertInertia(fn (Assert $p) => $p->has('results.data', 0));
    $path['service']->update(['online_visible' => false]);
    $this->get('/marketplace?category=nails')->assertInertia(fn (Assert $p) => $p->has('results.data', 0));
    $this->post('/marketplace/nearby', ['latitude' => 43.65123456, 'longitude' => -79.38123456, 'q' => 'Gel', 'category' => 'nails'])->assertRedirect('/marketplace?nearby=1&q=Gel&category=nails');
    $this->travel(31)->minutes();
    $this->get('/marketplace?nearby=1')->assertInertia(fn (Assert $p) => $p->where('filters.nearby', false))->assertSessionMissing('marketplace_nearby');
});
