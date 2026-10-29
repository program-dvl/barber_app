<?php

use App\Domain\BusinessConfiguration\Models\LocationHour;
use App\Domain\BusinessConfiguration\Models\Service;
use App\Domain\BusinessConfiguration\Models\StaffAvailabilityRule;
use App\Domain\BusinessConfiguration\Models\StaffServiceAssignment;
use App\Domain\BusinessConfiguration\Services\BookingSlugManager;
use App\Domain\ClientRecords\Models\Client;
use App\Domain\ClientRecords\Services\ClientIdentityService;
use App\Domain\MoneyCommerce\Services\DepositPolicyService;
use App\Domain\PlatformAccess\Enums\StarterRole;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\Location;
use App\Domain\PlatformAccess\Models\Membership;
use App\Domain\PlatformAccess\Models\StaffProfile;
use App\Domain\PlatformAccess\Services\MembershipAccessManager;
use App\Domain\PublicBooking\Models\PublicBookingEvent;
use App\Domain\PublicBooking\Models\PublicBookingFlow;
use App\Domain\PublicBooking\Models\WaitlistMatch;
use App\Domain\PublicBooking\Models\WaitlistRequest;
use App\Domain\PublicBooking\Services\AppointmentSelfService;
use App\Domain\PublicBooking\Services\PublicBookingService;
use App\Domain\PublicBooking\Services\SecureAppointmentLinkService;
use App\Domain\PublicBooking\Services\WaitlistService;
use App\Domain\SchedulingOperations\Contracts\AppointmentLifecycleCommand;
use App\Domain\SchedulingOperations\Contracts\BookingCommitCommand;
use App\Domain\SchedulingOperations\Data\BookingLineRequest;
use App\Domain\SchedulingOperations\Data\BookingRequest;
use App\Domain\SchedulingOperations\Exceptions\BookingRuleViolation;
use App\Domain\SchedulingOperations\Models\Appointment;
use App\Domain\SchedulingOperations\Models\CapacityHold;
use App\Models\User;
use App\Support\BookingUrl;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

/** @return array{business:Business,location:Location,service:Service,staff:StaffProfile,user:User,membership:Membership} */
function publicBookingPath(bool $deposit = false): array
{
    static $sequence = 0;
    $sequence++;
    $business = Business::factory()->create([
        'name' => 'Pine & Palm Studio', 'booking_slug' => 'pine-palm-public-'.$sequence, 'status' => 'active',
        'configuration_published_at' => now(), 'time_zone' => 'Asia/Kolkata', 'currency_code' => 'INR',
        'default_cancellation_policy' => 'Change or cancel at least 24 hours before your appointment.',
        'terms_url' => 'https://example.test/terms', 'privacy_url' => 'https://example.test/privacy',
        'online_booking_enabled' => true, 'cancellation_cutoff_minutes' => 60, 'waitlist_offer_batch_size' => 2,
    ]);
    activateTestSubscription($business);
    $location = Location::factory()->create(['business_id' => $business->id, 'name' => 'Indiranagar Studio', 'time_zone' => 'Asia/Kolkata', 'is_active' => true, 'status' => 'active']);
    for ($day = 1; $day <= 7; $day++) {
        LocationHour::query()->create(['business_id' => $business->id, 'location_id' => $location->id, 'day_of_week' => $day, 'opens_at' => '09:00', 'closes_at' => '18:00', 'sequence' => 1]);
    }
    $user = User::factory()->create(['email_verified_at' => now()]);
    $membership = Membership::factory()->create(['business_id' => $business->id, 'user_id' => $user->id]);
    app(MembershipAccessManager::class)->assignStarterRole($membership, StarterRole::Owner, $user, 'Public booking test.');
    $membership->locations()->syncWithPivotValues([$location->id], ['business_id' => $business->id]);
    $staff = StaffProfile::factory()->create(['business_id' => $business->id, 'membership_id' => $membership->id, 'user_id' => $user->id, 'display_name' => 'Avery', 'email' => 'private-staff@example.test', 'mobile' => '+919000000000', 'online_visible' => true]);
    $staff->locations()->syncWithPivotValues([$location->id], ['business_id' => $business->id]);
    for ($day = 1; $day <= 7; $day++) {
        StaffAvailabilityRule::query()->create(['business_id' => $business->id, 'staff_profile_id' => $staff->id, 'location_id' => $location->id, 'kind' => 'working', 'day_of_week' => $day, 'starts_at' => '09:00', 'ends_at' => '18:00']);
    }
    $service = Service::query()->create([
        'business_id' => $business->id, 'kind' => 'service', 'name' => 'Signature Cut', 'description' => 'Consultation and cut.',
        'price_type' => 'fixed', 'price_minor' => 3500, 'currency_code' => 'INR', 'duration_minutes' => 60,
        'minimum_notice_minutes' => 0, 'maximum_advance_days' => 60, 'client_eligibility' => 'all',
        'deposit_type' => $deposit ? 'fixed' : 'none', 'deposit_value' => $deposit ? 1000 : 0,
        'is_active' => true, 'online_visible' => true,
    ]);
    $service->locations()->attach($location->id, ['business_id' => $business->id, 'is_eligible' => true]);
    StaffServiceAssignment::query()->create(['business_id' => $business->id, 'staff_profile_id' => $staff->id, 'service_id' => $service->id, 'is_qualified' => true, 'is_active' => true, 'online_visible' => true]);

    return compact('business', 'location', 'service', 'staff', 'user', 'membership');
}

/** @return array{flow:PublicBookingFlow,secret:string,slot:array<string,mixed>} */
function heldPublicFlow(array $path, string $time = '2026-08-18T10:00:00+05:30'): array
{
    $service = app(PublicBookingService::class);
    $started = $service->start($path['business']);
    $slots = $service->search($path['business'], $path['location']->public_id, [$path['service']->public_id], $path['staff']->public_id, '2026-08-18', '2026-08-18', 'new');
    $slot = collect($slots)->first(fn (array $candidate) => $candidate['local_starts_at'] === $time) ?? $slots[0];
    $flow = $service->hold($path['business'], $started['flow'], [
        'location' => $path['location']->public_id, 'services' => [$path['service']->public_id],
        'staff' => $path['staff']->public_id, 'starts_at' => $slot['starts_at_utc'], 'client_eligibility' => 'new',
    ], 'public-hold-'.$started['flow']->public_id);

    return ['flow' => $flow, 'secret' => $started['secret'], 'slot' => $slot];
}

it('presents a complete no-photo salon with public biographies and local usual hours', function () {
    $this->withoutVite();
    config(['inertia.ssr.enabled' => false]);
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-29T00:30:21.784321+05:30')->utc());
    $path = publicBookingPath();
    $path['staff']->update(['title' => 'Senior stylist', 'biography' => 'Precision cuts and thoughtful consultations.']);
    LocationHour::query()->create(['business_id' => $path['business']->id, 'location_id' => $path['location']->id,
        'day_of_week' => 4, 'opens_at' => '19:00', 'closes_at' => '20:00', 'sequence' => 2,
        'effective_from' => '2026-10-29', 'effective_until' => '2026-10-29']);
    LocationHour::query()->create(['business_id' => $path['business']->id, 'location_id' => $path['location']->id,
        'day_of_week' => 4, 'opens_at' => '21:00', 'closes_at' => '22:00', 'sequence' => 3, 'effective_from' => '2026-10-30']);
    $catalog = app(PublicBookingService::class)->catalog($path['business']);
    expect($catalog['locations'][0]['today'])->toBe('2026-10-29')
        ->and(collect($catalog['locations'][0]['opening_hours'])->where('day', 0)->count())->toBe(1)
        ->and(collect($catalog['locations'][0]['opening_hours'])->pluck('opens_at'))->toContain('19:00')->not->toContain('21:00')
        ->and($catalog['staff'][0])->toMatchArray(['title' => 'Senior stylist', 'biography' => 'Precision cuts and thoughtful consultations.', 'photo_url' => null])
        ->and($catalog['staff'][0])->not->toHaveKeys(['email', 'mobile', 'photo_path']);
    $this->get(route('booking.business.fallback', $path['business']->booking_slug))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('business.cover_url', null)->where('business.has_cover_image', false)
            ->where('business.gallery', [])->where('business.reviews.count', 0)->where('business.reviews.average', null));
});

it('books passwordlessly through one shared hold and commit engine with policy and conversion evidence', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-12 09:00', 'Asia/Kolkata')->utc());
    $path = publicBookingPath();
    $booking = app(PublicBookingService::class);
    $catalog = $booking->catalog($path['business']);
    expect($catalog['staff'][0])->not->toHaveKeys(['email', 'mobile'])
        ->and($catalog['services'][0]['price_minor'])->toBe(3500);
    $held = heldPublicFlow($path);
    expect($held['flow']->status)->toBe('held')
        ->and($held['flow']->state['policy']['services'][0]['deposit_minor'])->toBe(0)
        ->and($held['flow']->state['policy']['cancellation_policy'])->toContain('24 hours');

    $details = [
        'client_name' => 'Jordan Lee', 'client_mobile' => '+919999999999', 'client_email' => 'jordan@example.test',
        'client_date_of_birth' => '1992-03-05', 'referral_source' => 'Friend', 'special_request' => 'Quiet chair, please.',
        'communication_preferences' => ['email'], 'marketing_opt_in' => false,
    ];
    $result = $booking->confirm($path['business'], $held['flow'], $details, 'public-confirm-'.$held['flow']->public_id);
    $replay = $booking->confirm($path['business'], $held['flow']->fresh(), $details, 'public-confirm-'.$held['flow']->public_id);
    expect($replay['appointment']->id)->toBe($result['appointment']->id)
        ->and($result['appointment']->booking_reference)->toStartWith('GH-')
        ->and($result['appointment']->client_email)->toBe('jordan@example.test')
        ->and($result['appointment']->client_id)->not->toBeNull()
        ->and(Client::query()->where('business_id', $path['business']->id)->where('normalized_email', 'jordan@example.test')->count())->toBe(1)
        ->and($result['appointment']->special_request)->toContain('Quiet chair')
        ->and($result['appointment']->public_policy_snapshot['version'])->toBe(1)
        ->and(PublicBookingEvent::query()->where('event_name', 'booking_completed')->count())->toBe(1)
        ->and(PublicBookingEvent::query()->where('public_booking_flow_id', $held['flow']->id)->distinct('session_hash')->count('session_hash'))->toBe(1)
        ->and($result['view_url'])->toContain('/appointments/secure/');
    CarbonImmutable::setTestNow();
});

it('confirms an unchanged hold after the service snapshot observation clock advances', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-12 09:00', 'Asia/Kolkata')->utc());
    $path = publicBookingPath();
    $held = heldPublicFlow($path);
    CarbonImmutable::setTestNow(CarbonImmutable::now()->addMinutes(2));

    $result = app(PublicBookingService::class)->confirm($path['business'], $held['flow'], [
        'client_name' => 'Clock Safe', 'client_mobile' => '+911', 'client_email' => 'clock@example.test',
    ], 'clock-safe-confirm');

    expect($result['appointment']->status)->toBe('confirmed')
        ->and($result['appointment']->public_policy_snapshot['version'])->toBe(1);
    CarbonImmutable::setTestNow();
});

it('fails safely for changed policy expired flow and an unconnected required deposit', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-12 09:00', 'Asia/Kolkata')->utc());
    $path = publicBookingPath();
    $held = heldPublicFlow($path);
    $path['business']->update(['public_booking_policy_version' => 2]);
    expect(fn () => app(PublicBookingService::class)->confirm($path['business']->fresh(), $held['flow'], ['client_name' => 'A', 'client_mobile' => '1', 'client_email' => 'a@example.test'], 'policy-changed'))
        ->toThrow(BookingRuleViolation::class, 'policies changed');

    $expired = app(PublicBookingService::class)->start($path['business']->fresh());
    $expired['flow']->update(['expires_at' => now()->subMinute()]);
    expect(fn () => app(PublicBookingService::class)->resolve($path['business']->fresh(), $expired['flow']->public_id, $expired['secret']))
        ->toThrow(BookingRuleViolation::class, 'session expired');

    $depositPath = publicBookingPath(deposit: true);
    $depositHeld = heldPublicFlow($depositPath, '2026-08-18T11:00:00+05:30');
    expect(fn () => app(PublicBookingService::class)->confirm($depositPath['business'], $depositHeld['flow'], ['client_name' => 'A', 'client_mobile' => '1', 'client_email' => 'a@example.test'], 'deposit-blocked'))
        ->toThrow(BookingRuleViolation::class, 'Complete the displayed deposit payment');
    expect($depositHeld['flow']->fresh()->appointment_id)->toBeNull();
    CarbonImmutable::setTestNow();
});

it('returns an actionable conflict when availability is requested with an expired flow', function () {
    $path = publicBookingPath();
    $started = app(PublicBookingService::class)->start($path['business']);
    $started['flow']->update(['expires_at' => now()->subMinute()]);

    $this->postJson(route('public.booking.search', $path['business']->booking_slug), [
        'flow' => $started['flow']->public_id,
        'secret' => $started['secret'],
        'location' => $path['location']->public_id,
        'services' => [$path['service']->public_id],
        'staff' => $path['staff']->public_id,
        'from_date' => now()->addDay()->toDateString(),
        'until_date' => now()->addDay()->toDateString(),
        'client_eligibility' => 'new',
    ])->assertConflict()
        ->assertJsonPath('code', 'BOOKING_FLOW_EXPIRED')
        ->assertJsonPath('message', 'This booking session expired. Start again to see current availability.');
});

it('records a selected client cancellation reason through secure self service', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-12 09:00', 'Asia/Kolkata')->utc());
    $path = publicBookingPath();
    $held = heldPublicFlow($path);
    $appointment = app(PublicBookingService::class)->confirm($path['business'], $held['flow'], [
        'client_name' => 'Jordan', 'client_mobile' => '+911', 'client_email' => 'j@example.test',
    ], 'reasoned-cancel-confirm')['appointment'];
    $cancel = app(SecureAppointmentLinkService::class)->issue($appointment->loadMissing('business'), 'cancel');

    $this->post(route('public.appointment.mutate', [$cancel['token'], 'cancel']), [
        'reason_code' => 'unwell', 'confirmed' => true, 'idempotency_key' => 'reasoned-client-cancel',
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($appointment->fresh()->status)->toBe('cancelled_by_client')
        ->and($appointment->changes()->latest('id')->value('reason'))->toBe('Client cancelled because they are unwell.');
    CarbonImmutable::setTestNow();
});

it('keeps secure links purpose-bound expiring revocable and versioned for self-service changes', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-12 09:00', 'Asia/Kolkata')->utc());
    $path = publicBookingPath();
    $held = heldPublicFlow($path);
    $appointment = app(PublicBookingService::class)->confirm($path['business'], $held['flow'], ['client_name' => 'Jordan', 'client_mobile' => '+911', 'client_email' => 'j@example.test'], 'secure-confirm')['appointment'];
    $links = app(SecureAppointmentLinkService::class);
    $contactToken = $links->issue($appointment->loadMissing('business'), 'contact');
    expect(fn () => $links->resolve($contactToken['token'], 'cancel'))->toThrow(HttpException::class);
    $contactLink = $links->resolve($contactToken['token'], 'contact');
    $updated = app(AppointmentSelfService::class)->updateContact($contactLink, ['name' => 'Jordan Lee', 'mobile' => '+912', 'email' => 'new@example.test'], 'contact-change');
    expect($updated->client_email)->toBe('new@example.test')
        ->and($updated->client->email)->toBe('new@example.test')
        ->and($contactLink->fresh()->used_at)->not->toBeNull()
        ->and($contactLink->fresh()->revoked_at)->not->toBeNull();

    $expired = $links->issue($updated->loadMissing('business'), 'cancel', now()->subMinute()->toImmutable());
    expect(fn () => $links->resolve($expired['token'], 'cancel'))->toThrow(HttpException::class);
    $cancel = $links->issue($updated, 'cancel');
    $links->revokeAppointment($updated);
    expect(fn () => $links->resolve($cancel['token'], 'cancel'))->toThrow(HttpException::class);

    $activeCancel = $links->issue($updated, 'cancel');
    $cancelled = app(AppointmentSelfService::class)->cancel($links->resolve($activeCancel['token'], 'cancel'), 'secure-cancel');
    expect($cancelled->status)->toBe('cancelled_by_client')->and($cancelled->changes->last()->source)->toBe('self_service');
    CarbonImmutable::setTestNow();
});

it('deduplicates matching waitlist requests and awards one opening to the first valid atomic claim', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-12 09:00', 'Asia/Kolkata')->utc());
    $path = publicBookingPath();
    $booking = app(BookingCommitCommand::class)->commit(new BookingRequest(
        $path['business']->id, $path['location']->id, CarbonImmutable::parse('2026-08-18 10:00', 'Asia/Kolkata')->utc(),
        [new BookingLineRequest($path['service']->id, $path['staff']->id, [], false)], 'online', 'existing', null, null, null, null, 'Original', '+910', null, [], null, 'original@example.test',
    ), 'waitlist-opening');
    $waitlist = app(WaitlistService::class);
    $base = ['client_name' => 'First', 'client_mobile' => '+911', 'client_email' => 'first@example.test', 'acceptable_from' => '2026-08-18', 'acceptable_until' => '2026-08-18', 'time_from' => '09:00', 'time_until' => '12:00', 'notification_method' => 'email'];
    $first = $waitlist->create($path['business'], $path['location'], $path['service'], $path['staff'], $base);
    $duplicate = $waitlist->create($path['business'], $path['location'], $path['service'], $path['staff'], $base);
    $second = $waitlist->create($path['business'], $path['location'], $path['service'], $path['staff'], [...$base, 'client_name' => 'Second', 'client_mobile' => '+912', 'client_email' => 'second@example.test']);
    expect($duplicate->id)->toBe($first->id)->and(WaitlistRequest::query()->count())->toBe(2);
    $cancelled = app(AppointmentLifecycleCommand::class)->transition($booking, 'cancelled_by_shop', 'open-waitlist-slot', 1, 'calendar', 'user', $path['user']->id, 'Client requested a different day.');
    $offers = $waitlist->offerForOpening($cancelled);
    expect($offers)->toHaveCount(2)->and($offers[0]['match']->batch_id)->toBe($offers[1]['match']->batch_id);
    $claimed = $waitlist->claim($offers[0]['token']);
    expect($claimed->source)->toBe('waitlist')
        ->and($first->fresh()->status)->toBe('booked')
        ->and($second->fresh()->status)->toBe('active')
        ->and(WaitlistMatch::query()->where('batch_id', $offers[0]['match']->batch_id)->where('status', 'claimed')->count())->toBe(1)
        ->and(WaitlistMatch::query()->where('batch_id', $offers[0]['match']->batch_id)->where('status', 'lost')->count())->toBe(1);
    expect(fn () => $waitlist->claim($offers[1]['token']))->toThrow(BookingRuleViolation::class);
    expect(Appointment::query()->where('business_id', $path['business']->id)->where('starts_at_utc', $booking->starts_at_utc)->whereIn('status', ['confirmed', 'pending_confirmation'])->count())->toBe(1);
    CarbonImmutable::setTestNow();
});

it('expires an offer while preserving its request for a later eligible opening', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-12 09:00', 'Asia/Kolkata')->utc());
    $path = publicBookingPath();
    $path['business']->update(['waitlist_offer_batch_size' => 1]);
    $booking = app(BookingCommitCommand::class)->commit(new BookingRequest(
        $path['business']->id, $path['location']->id, CarbonImmutable::parse('2026-08-18 10:00', 'Asia/Kolkata')->utc(),
        [new BookingLineRequest($path['service']->id, $path['staff']->id, [], false)], 'online', 'existing', null,
    ), 'expiring-waitlist-opening');
    $waitlist = app(WaitlistService::class);
    $request = $waitlist->create($path['business'], $path['location'], $path['service'], $path['staff'], [
        'client_name' => 'Later Client', 'client_mobile' => '+913', 'client_email' => 'later@example.test',
        'acceptable_from' => '2026-08-18', 'acceptable_until' => '2026-08-19',
        'time_from' => '09:00', 'time_until' => '12:00', 'notification_method' => 'email',
    ]);
    $cancelled = app(AppointmentLifecycleCommand::class)->transition($booking, 'cancelled_by_shop', 'expire-waitlist-slot', 1, 'calendar', reason: 'Opening expiry test.');
    $offer = $waitlist->offerForOpening($cancelled, 1)[0]['match'];
    CarbonImmutable::setTestNow(CarbonImmutable::now()->addMinutes(2));

    expect($waitlist->expireOffers())->toBe(1)
        ->and($offer->fresh()->status)->toBe('expired')
        ->and($request->fresh()->status)->toBe('active')
        ->and($request->fresh()->active_dedupe_key)->not->toBeNull();
    CarbonImmutable::setTestNow();
});

it('serves the mobile booking surface while resisting tenant and identifier tampering', function () {
    $path = publicBookingPath();
    $other = publicBookingPath();
    $this->get(route('booking.business', $path['business']->booking_slug))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Booking/Welcome')->where('business.name', 'Pine & Palm Studio')->has('catalog.locations', 1)->has('catalog.services', 1));
    $started = $this->postJson(route('public.booking.start', $path['business']->booking_slug))->assertOk()->json();
    $this->postJson(route('public.booking.search', $other['business']->booking_slug), [
        'flow' => $started['flow'], 'secret' => $started['secret'], 'location' => $other['location']->public_id,
        'services' => [$other['service']->public_id], 'from_date' => '2026-08-18', 'until_date' => '2026-08-18', 'client_eligibility' => 'new',
    ])->assertNotFound();
    $this->get('/appointments/secure/'.str_repeat('0', 64))->assertNotFound();

    $this->actingAs($path['user'])->patch(route('business.configuration.public-booking-policy.update', $path['business']), [
        'online_booking_enabled' => true, 'online_staff_preference' => 'any_only', 'online_price_display' => 'from',
        'online_new_client_rule' => 'existing_only', 'staff_gender_request_enabled' => false,
        'cancellation_cutoff_minutes' => 120, 'waitlist_offer_batch_size' => 1, 'public_link_ttl_minutes' => 1440,
    ])->assertRedirect();
    expect($path['business']->fresh()->public_booking_policy_version)->toBe(2)
        ->and($path['business']->fresh()->online_staff_preference)->toBe('any_only');
});

it('lets a secure client join and leave a waitlist without reusing consumed action links', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-12 09:00', 'Asia/Kolkata')->utc());
    $path = publicBookingPath();
    $held = heldPublicFlow($path);
    $appointment = app(PublicBookingService::class)->confirm($path['business'], $held['flow'], [
        'client_name' => 'Jordan', 'client_mobile' => '+911', 'client_email' => 'j@example.test',
    ], 'secure-waitlist-confirm')['appointment'];
    $links = app(SecureAppointmentLinkService::class);
    $join = $links->issue($appointment->loadMissing('business'), 'waitlist');
    $joinLink = $links->resolve($join['token'], 'waitlist');
    $response = $this->post(route('public.appointment.mutate', [$join['token'], 'waitlist']), [
        'operation' => 'join', 'acceptable_from' => '2026-08-18', 'acceptable_until' => '2026-08-19',
        'time_from' => '09:00', 'time_until' => '12:00', 'notification_method' => 'email',
    ]);
    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('/appointments/secure/')
        ->and($joinLink->fresh()->used_at)->not->toBeNull();

    $entry = WaitlistRequest::query()->where('business_id', $path['business']->id)->firstOrFail();
    expect($entry->origin_appointment_id)->toBe($appointment->id);
    $leave = $links->issue($appointment, 'waitlist');
    $this->get(route('public.appointment.action', [$leave['token'], 'waitlist']))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Booking/Manage')->has('activeWaitlists', 1)->where('activeWaitlists.0.public_id', $entry->public_id));
    $this->post(route('public.appointment.mutate', [$leave['token'], 'waitlist']), [
        'operation' => 'leave', 'waitlist_request' => $entry->public_id, 'version' => $entry->version,
    ])->assertRedirect();
    expect($entry->fresh()->status)->toBe('cancelled')
        ->and($entry->fresh()->active_dedupe_key)->toBeNull();
    CarbonImmutable::setTestNow();
});

it('rate limits repeated public booking session creation', function () {
    $path = publicBookingPath();
    $last = null;
    for ($attempt = 0; $attempt < 31; $attempt++) {
        $last = $this->postJson(route('public.booking.start', $path['business']->booking_slug));
    }

    $last->assertTooManyRequests();
});

it('shows visit time separately from reserved cleanup time without changing capacity', function () {
    $path = publicBookingPath();
    $path['service']->update(['duration_minutes' => 40, 'processing_minutes' => 10, 'cleanup_minutes' => 15]);
    $catalog = app(PublicBookingService::class)->catalog($path['business']);
    expect($catalog['services'][0]['visit_minutes'])->toBe(50)
        ->and($catalog['services'][0]['duration_minutes'])->toBe(65);
});

it('renders both booking URL formats with one primary canonical and no redirect for the fallback', function () {
    $path = publicBookingPath();
    $canonical = BookingUrl::forSlug($path['business']->booking_slug);
    foreach ([route('booking.business', $path['business']->booking_slug), route('booking.business.fallback', $path['business']->booking_slug)] as $url) {
        $this->get($url)->assertOk()->assertHeader('X-Robots-Tag', 'noindex, follow, noarchive')
            ->assertInertia(fn (Assert $page) => $page->component('Booking/Welcome')->where('seo.canonical', $canonical)->where('business.booking_url', $canonical));
    }
    expect($canonical)->toBe('https://book.clipperdesk.com/'.$path['business']->booking_slug);
});

it('keeps aliases working in both URL formats and handles paused inactive and unknown businesses safely', function () {
    $path = publicBookingPath();
    $old = $path['business']->booking_slug;
    $business = app(BookingSlugManager::class)->change($path['business'], 'new-public-name');
    foreach ([route('booking.business', $old), route('booking.business.fallback', $old)] as $url) {
        $this->get($url)->assertRedirect(BookingUrl::forSlug('new-public-name'));
    }
    $business->update(['online_booking_enabled' => false]);
    $this->get(route('booking.business', $old))->assertNotFound();
    $this->get(route('booking.business.fallback', 'new-public-name'))->assertNotFound();
    $business->update(['online_booking_enabled' => true, 'status' => 'suspended']);
    $this->get(route('booking.business', 'new-public-name'))->assertNotFound();
    $this->get(route('booking.business', 'unknown-public-name'))->assertNotFound();
});

it('releases and replaces held times atomically without leaking internal availability data', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-12 09:00', 'Asia/Kolkata')->utc());
    $path = publicBookingPath();
    $booking = app(PublicBookingService::class);
    $held = heldPublicFlow($path);
    $oldHold = $held['flow']->capacity_hold_id;
    $credentials = ['flow' => $held['flow']->public_id, 'secret' => $held['secret']];
    $url = fn ($name) => route($name, $path['business']->booking_slug);
    $this->postJson($url('public.booking.resume'), $credentials)->assertOk()->assertJsonPath('status', 'held')->assertJsonPath('state.policy.services.0.staff_name', 'Avery');
    $slots = $booking->search($path['business'], $path['location']->public_id, [$path['service']->public_id], $path['staff']->public_id, '2026-08-18', '2026-08-18', 'new');
    expect($slots[0])->not->toHaveKeys(['lines', 'resource_claims', 'staff_profile_id']);
    $selection = [...$held['flow']->state, 'starts_at' => '2026-08-18T12:00:00+05:30'];
    unset($selection['policy'], $selection['hold_expires_at']);
    $replacement = $booking->hold($path['business'], $held['flow'], $selection, 'replacement-hold');
    expect(CapacityHold::find($oldHold)->status)->toBe('expired')
        ->and(CapacityHold::where('status', 'active')->count())->toBe(1);
    $replay = $booking->hold($path['business'], $replacement, $selection, 'replacement-hold');
    expect($replay->capacity_hold_id)->toBe($replacement->capacity_hold_id);
    $this->postJson($url('public.booking.release'), $credentials)->assertOk();
    expect($replacement->fresh()->status)->toBe('started')->and(CapacityHold::where('status', 'active')->count())->toBe(0);
    CarbonImmutable::setTestNow();
});

it('preserves a previous hold when a replacement time fails', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-12 09:00', 'Asia/Kolkata')->utc());
    $path = publicBookingPath();
    $held = heldPublicFlow($path);
    $booking = app(PublicBookingService::class);
    $selection = [...$held['flow']->state, 'starts_at' => '2026-08-18T23:00:00+05:30'];
    expect(fn () => $booking->hold($path['business'], $held['flow'], $selection, 'invalid-replacement'))->toThrow(BookingRuleViolation::class);
    expect($held['flow']->fresh()->hold->status)->toBe('active');
    CarbonImmutable::setTestNow();
});

it('restores a confirmed appointment after an ambiguous response without creating another visit', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-12 09:00', 'Asia/Kolkata')->utc());
    $path = publicBookingPath();
    $held = heldPublicFlow($path);
    app(PublicBookingService::class)->confirm($path['business'], $held['flow'], ['client_name' => 'Test Customer', 'client_mobile' => '+14155552671', 'client_email' => 'guest@example.test'], 'confirm-recovery');
    CarbonImmutable::setTestNow(CarbonImmutable::now()->addHours(3));
    $this->postJson(route('public.booking.resume', $path['business']->booking_slug), ['flow' => $held['flow']->public_id, 'secret' => $held['secret']])
        ->assertOk()->assertJsonPath('status', 'confirmed')->assertJsonPath('confirmation.staff.0', 'Avery');
    expect(Appointment::where('business_id', $path['business']->id)->count())->toBe(1);
    $this->postJson(route('public.booking.resume', $path['business']->booking_slug), ['flow' => $held['flow']->public_id, 'secret' => str_repeat('0', 64)])->assertNotFound();
    CarbonImmutable::setTestNow();
});

it('rejects a held confirmation after online bookings are paused and bounds date search', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-12 09:00', 'Asia/Kolkata')->utc());
    $path = publicBookingPath();
    $held = heldPublicFlow($path);
    $path['business']->update(['online_booking_enabled' => false]);
    expect(fn () => app(PublicBookingService::class)->confirm($path['business'], $held['flow'], [], 'paused-confirm'))->toThrow(HttpException::class);
    expect(Appointment::count())->toBe(0);
    $path['business']->update(['online_booking_enabled' => true]);
    $this->postJson(route('public.booking.search', $path['business']->booking_slug), ['flow' => $held['flow']->public_id, 'secret' => $held['secret'], 'location' => $path['location']->public_id, 'services' => [$path['service']->public_id], 'from_date' => '2026-08-18', 'until_date' => '2027-08-18', 'client_eligibility' => 'new'])->assertUnprocessable();
    CarbonImmutable::setTestNow();
});

it('shows available rescheduling times through purpose-bound links and revalidates on commit', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-12 09:00', 'Asia/Kolkata')->utc());
    $path = publicBookingPath();
    $held = heldPublicFlow($path);
    $appointment = app(PublicBookingService::class)->confirm($path['business'], $held['flow'], ['client_name' => 'Test Customer', 'client_mobile' => '+14155552671', 'client_email' => 'guest@example.test'], 'reschedule-selection')['appointment'];
    $links = app(SecureAppointmentLinkService::class);
    $token = $links->issue($appointment->loadMissing('business'), 'reschedule')['token'];
    $slots = $this->postJson(route('public.appointment.availability', $token), ['date' => '2026-08-18'])->assertOk()->json('slots');
    expect(collect($slots)->contains('starts_at_utc', $appointment->starts_at_utc->toIso8601String()))->toBeTrue();
    $this->post(route('public.appointment.mutate', [$token, 'reschedule']), ['starts_at' => '2026-08-18T12:00:00+05:30', 'confirmed' => true, 'idempotency_key' => 'reschedule-picked-slot'])->assertRedirect()->assertSessionHasNoErrors();
    expect($appointment->fresh()->status)->toBe('rescheduled');
    $this->postJson(route('public.appointment.availability', $token), ['date' => '2026-08-18'])->assertStatus(410);
    $view = $links->issue($appointment->fresh(), 'view')['token'];
    $this->postJson(route('public.appointment.availability', $view), ['date' => '2026-08-18'])->assertNotFound();
    CarbonImmutable::setTestNow();
});

it('returns JSON waitlist validation and success instead of redirecting an asynchronous booking form', function () {
    $path = publicBookingPath();
    $base = ['location' => $path['location']->public_id, 'service' => $path['service']->public_id, 'client_name' => 'Waitlist Customer', 'client_mobile' => '+14155552671', 'acceptable_from' => now()->addDay()->toDateString(), 'acceptable_until' => now()->addDays(3)->toDateString(), 'time_from' => '09:00', 'time_until' => '17:00', 'notification_method' => 'email'];
    $url = route('public.waitlist.store', $path['business']->booking_slug);
    $this->postJson($url, $base)->assertUnprocessable()->assertJsonValidationErrors('client_email');
    $this->postJson($url, [...$base, 'client_email' => 'wait@example.test'])->assertOk()->assertJsonPath('message', 'You are on the waitlist.');
    $this->postJson($url, [...$base, 'client_email' => 'wait@example.test'])->assertOk()->assertJsonPath('message', 'That waitlist request is already active.');
});

it('recognizes returning contacts only within the business and recalculates new-client deposits without a public lookup', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-12 09:00', 'Asia/Kolkata')->utc());
    $path = publicBookingPath();
    $held = heldPublicFlow($path);
    $settings = app(DepositPolicyService::class)->settings($path['business']);
    $settings->update(['default_deposit_type' => 'fixed', 'default_deposit_value' => 1000, 'deposit_new_clients_only' => true]);
    $contact = ['client_name' => 'Returning Customer', 'client_mobile' => '+14155552671', 'client_email' => 'returning@example.test'];
    $credentials = ['flow' => $held['flow']->public_id, 'secret' => $held['secret']];
    $other = publicBookingPath();
    app(ClientIdentityService::class)->createManual($other['business'], ['name' => $contact['client_name'], 'mobile' => $contact['client_mobile'], 'email' => $contact['client_email']]);
    $url = route('public.booking.details', $path['business']->booking_slug);
    $response = $this->postJson($url, [...$credentials, ...$contact])->assertOk()->assertJsonPath('policy.deposit.amount_minor', 1000);
    expect($response->json('policy.deposit'))->not->toHaveKeys(['prior_no_show_count', 'client_eligibility']);
    app(ClientIdentityService::class)->createManual($path['business'], ['name' => $contact['client_name'], 'mobile' => $contact['client_mobile'], 'email' => $contact['client_email']]);
    $this->postJson($url, [...$credentials, ...$contact])->assertOk()->assertJsonPath('policy.deposit.amount_minor', 0);
    $payload = [...$credentials, ...$contact, 'policy_accepted' => true, 'idempotency_key' => 'smart-returning-confirm'];
    $this->postJson(route('public.booking.confirm', $path['business']->booking_slug), $payload)->assertOk();
    $this->postJson(route('public.booking.confirm', $path['business']->booking_slug), $payload)->assertOk();
    expect(Appointment::where('business_id', $path['business']->id)->count())->toBe(1);
    CarbonImmutable::setTestNow();
});

it('requires a reviewed contact quote and keeps secrets out of persisted flow state', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-12 09:00', 'Asia/Kolkata')->utc());
    $path = publicBookingPath();
    $booking = app(PublicBookingService::class);
    $started = $booking->start($path['business']);
    $credentials = ['flow' => $started['flow']->public_id, 'secret' => $started['secret']];
    $this->postJson(route('public.booking.hold', $path['business']->booking_slug), [...$credentials, 'location' => $path['location']->public_id, 'services' => [$path['service']->public_id], 'starts_at' => '2026-08-18T10:00:00+05:30', 'client_eligibility' => 'auto', 'idempotency_key' => 'auto-client-hold'])->assertOk();
    expect($started['flow']->fresh()->state)->not->toHaveKeys(['secret', 'flow']);
    $contact = ['client_name' => 'Guest Customer', 'client_mobile' => '+14155552671', 'client_email' => 'guest@example.test'];
    $confirmUrl = route('public.booking.confirm', $path['business']->booking_slug);
    $this->postJson($confirmUrl, [...$credentials, ...$contact, 'policy_accepted' => true, 'idempotency_key' => 'unreviewed-confirm'])->assertConflict()->assertJsonPath('code', 'DETAILS_REVIEW_REQUIRED');
    $this->postJson(route('public.booking.details', $path['business']->booking_slug), [...$credentials, ...$contact])->assertOk();
    $this->postJson($confirmUrl, [...$credentials, ...$contact, 'client_email' => 'changed@example.test', 'policy_accepted' => true, 'idempotency_key' => 'changed-contact-confirm'])->assertConflict()->assertJsonPath('code', 'DETAILS_REVIEW_REQUIRED');
    expect(Appointment::count())->toBe(0);
    CarbonImmutable::setTestNow();
});

it('does not let a self-declared returning client bypass first-visit restrictions', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-12 09:00', 'Asia/Kolkata')->utc());
    $path = publicBookingPath();
    $path['business']->update(['online_new_client_rule' => 'existing_only']);
    $booking = app(PublicBookingService::class);
    $started = $booking->start($path['business']);
    $flow = $booking->hold($path['business'], $started['flow'], ['location' => $path['location']->public_id, 'services' => [$path['service']->public_id], 'staff' => $path['staff']->public_id, 'starts_at' => '2026-08-18T10:00:00+05:30', 'client_eligibility' => 'existing'], 'returning-only-hold');
    $this->postJson(route('public.booking.details', $path['business']->booking_slug), ['flow' => $flow->public_id, 'secret' => $started['secret'], 'client_name' => 'New Customer', 'client_mobile' => '+14155552671', 'client_email' => 'new@example.test'])->assertUnprocessable()->assertJsonValidationErrors('booking');
    expect(Appointment::count())->toBe(0);
    CarbonImmutable::setTestNow();
});

it('matches a late-day waitlist opening without truncating it behind earlier available times', function () {
    $this->travelTo(CarbonImmutable::parse('2026-08-12T00:00:00Z'));
    $path = publicBookingPath();
    $booking = app(PublicBookingService::class);
    $held = heldPublicFlow($path, '2026-08-18T16:30:00+05:30');
    $appointment = $booking->confirm($path['business'], $held['flow'], ['client_name' => 'Opening Guest', 'client_mobile' => '+14155552671', 'client_email' => 'opening@example.test'], 'late-day-original')['appointment'];
    $waitlist = app(WaitlistService::class);
    $waitlist->create($path['business'], $path['location'], $path['service'], $path['staff'], [
        'client_name' => 'Waiting Guest', 'client_mobile' => '+14155552672', 'client_email' => 'waiting@example.test',
        'acceptable_from' => '2026-08-18', 'acceptable_until' => '2026-08-18',
        'time_from' => '16:00', 'time_until' => '18:00', 'notification_method' => 'email',
    ]);
    $cancelled = app(AppointmentLifecycleCommand::class)->transition($appointment, 'cancelled_by_shop', 'late-day-cancel', 1, 'calendar', reason: 'Opening for waitlist test.');
    $offers = $waitlist->offerForOpening($cancelled);
    expect($offers)->toHaveCount(1)
        ->and($offers[0]['match']->slot_starts_at_utc->equalTo($appointment->starts_at_utc))->toBeTrue();
    $this->travelBack();
});

it('rejects incompatible first-visit and returning service combinations before searching times', function () {
    $this->travelTo(CarbonImmutable::parse('2026-08-12T00:00:00Z'));
    $path = publicBookingPath();
    $path['service']->update(['client_eligibility' => 'new']);
    $returning = $path['service']->replicate(['public_id']);
    $returning->name = 'Returning visit';
    $returning->client_eligibility = 'existing';
    $returning->save();
    $started = app(PublicBookingService::class)->start($path['business']);
    $this->postJson(route('public.booking.search', $path['business']->booking_slug), [
        'flow' => $started['flow']->public_id, 'secret' => $started['secret'], 'location' => $path['location']->public_id,
        'services' => [$path['service']->public_id, $returning->public_id], 'from_date' => '2026-08-18', 'until_date' => '2026-08-18', 'client_eligibility' => 'auto',
    ])->assertUnprocessable()->assertJsonValidationErrors('services');
    $this->travelBack();
});

it('removes legacy raw flow secrets during a resumable booking slug backfill', function () {
    $path = publicBookingPath();
    $started = app(PublicBookingService::class)->start($path['business']);
    $started['flow']->update(['state' => ['secret' => $started['secret'], 'flow' => $started['flow']->public_id, 'location' => $path['location']->public_id]]);
    $migration = require database_path('migrations/2026_10_10_000001_reserve_public_booking_slugs.php');
    $migration->up();
    $migration->up();
    expect($started['flow']->fresh()->state)->not->toHaveKeys(['secret', 'flow'])
        ->and($started['flow']->fresh()->state['location'])->toBe($path['location']->public_id)
        ->and(app(PublicBookingService::class)->resolve($path['business'], $started['flow']->public_id, $started['secret'])->id)->toBe($started['flow']->id);
});

it('honors the accepted cancellation cutoff when the business later changes its defaults', function () {
    $this->travelTo(CarbonImmutable::parse('2026-08-12T00:00:00Z'));
    $path = publicBookingPath();
    $held = heldPublicFlow($path);
    $appointment = app(PublicBookingService::class)->confirm($path['business'], $held['flow'], ['client_name' => 'Policy Guest', 'client_mobile' => '+14155552671', 'client_email' => 'policy@example.test'], 'frozen-cutoff')['appointment'];
    $path['business']->update(['cancellation_cutoff_minutes' => 1440]);
    $this->travelTo($appointment->starts_at_utc->subHours(2));
    $token = app(SecureAppointmentLinkService::class)->issue($appointment->loadMissing('business'), 'reschedule')['token'];
    $this->postJson(route('public.appointment.availability', $token), ['date' => '2026-08-18'])->assertOk();
    $path['business']->update(['cancellation_cutoff_minutes' => 0]);
    $this->travelTo($appointment->starts_at_utc->subMinutes(30));
    $this->postJson(route('public.appointment.availability', $token), ['date' => '2026-08-18'])->assertConflict()->assertJsonPath('code', 'CANCELLATION_CUTOFF');
    $this->travelBack();
});

it('exposes only eligible branches and preserves the assigned location snapshot across self-service rescheduling', function () {
    $this->travelTo(CarbonImmutable::parse('2026-08-12 09:00', 'Asia/Kolkata'));
    $path = publicBookingPath();
    $path['location']->update(['address' => '18 Original Lane', 'entrance_latitude' => 12.97, 'entrance_longitude' => 77.59]);
    $unavailable = Location::factory()->create(['business_id' => $path['business']->id, 'name' => 'No services branch', 'is_primary' => true]);
    $catalog = app(PublicBookingService::class)->catalog($path['business']);
    expect($catalog['locations'])->toHaveCount(1)->and($catalog['locations'][0]['public_id'])->toBe($path['location']->public_id);
    $held = heldPublicFlow($path);
    $result = app(PublicBookingService::class)->confirm($path['business'], $held['flow'], ['client_name' => 'Entrance guest', 'client_mobile' => '+919000000001', 'client_email' => 'entrance@example.test'], 'entrance-confirm');
    expect($result['appointment']->location_id)->toBe($path['location']->id)->and($result['appointment']->location_snapshot['address'])->toBe('18 Original Lane');
    $path['location']->update(['address' => '99 Updated Lane', 'entrance_latitude' => 13]);
    $link = app(SecureAppointmentLinkService::class)->issue($result['appointment'], 'reschedule');
    $resolved = app(SecureAppointmentLinkService::class)->resolve($link['token'], 'reschedule');
    $replacement = app(AppointmentSelfService::class)->reschedule($resolved, $result['appointment']->starts_at_utc->addDays(1), 'entrance-reschedule');
    expect($replacement->location_id)->toBe($path['location']->id)->and($replacement->location_snapshot['address'])->toBe('18 Original Lane')->and($replacement->location_snapshot['entrance_latitude'])->toBe('12.9700000');
    $this->travelBack();
});

it('rejects services and staff that belong to another branch without bypassing tenant scheduling', function () {
    $this->travelTo(CarbonImmutable::parse('2026-08-12 09:00', 'Asia/Kolkata'));
    $path = publicBookingPath();
    $branch = Location::factory()->create(['business_id' => $path['business']->id]);
    $booking = app(PublicBookingService::class);
    expect(fn () => $booking->search($path['business'], $branch->public_id, [$path['service']->public_id], null, '2026-08-18', '2026-08-18', 'auto'))->toThrow(ValidationException::class);
    $path['service']->locations()->syncWithoutDetaching([$branch->id => ['business_id' => $path['business']->id, 'is_eligible' => true]]);
    expect(fn () => $booking->search($path['business'], $branch->public_id, [$path['service']->public_id], $path['staff']->public_id, '2026-08-18', '2026-08-18', 'auto'))->toThrow(ModelNotFoundException::class);
    expect(Appointment::query()->count())->toBe(0);
    $this->travelBack();
});

it('requires fresh location review if the branch address changes while the customer holds a time', function () {
    $this->travelTo(CarbonImmutable::parse('2026-08-12 09:00', 'Asia/Kolkata'));
    $path = publicBookingPath();
    $held = heldPublicFlow($path);
    $path['location']->update(['address' => 'Changed while booking']);
    expect(fn () => app(PublicBookingService::class)->confirm($path['business'], $held['flow'], ['client_name' => 'Location guest', 'client_mobile' => '+919000000001', 'client_email' => 'location@example.test'], 'changed-location-confirm'))->toThrow(BookingRuleViolation::class, 'location details');
    expect(Appointment::query()->count())->toBe(0);
    $this->travelBack();
});

it('permits user-initiated geolocation only on successful public booking pages', function () {
    $this->withoutVite();
    $path = publicBookingPath();
    $this->get(route('booking.business.fallback', $path['business']->booking_slug))->assertOk()->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(self), payment=(self)');
    $this->get('/book/unknown-location-business')->assertNotFound()->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(self)');
});

it('confirms reviewed contacts after JSON storage reorders an unchanged entrance address', function () {
    $this->travelTo(CarbonImmutable::parse('2026-08-12 09:00', 'Asia/Kolkata'));
    $path = publicBookingPath();
    $path['location']->update(['entrance_latitude' => 43.65, 'entrance_longitude' => -79.38, 'entrance_instructions' => 'Side door', 'parking_instructions' => 'Rear lot']);
    $held = heldPublicFlow($path);
    $state = $held['flow']->state;
    ksort($state['policy']['location_details']);
    $held['flow']->update(['state' => $state]);
    $credentials = ['flow' => $held['flow']->public_id, 'secret' => $held['secret']];
    $details = ['client_name' => 'JSON Guest', 'client_mobile' => '+14155552671', 'client_email' => 'json@example.test'];
    $this->postJson(route('public.booking.details', $path['business']->booking_slug), [...$credentials, ...$details])->assertOk();
    $payload = [...$credentials, ...$details, 'policy_accepted' => true, 'idempotency_key' => 'sorted-json-confirm'];
    $url = route('public.booking.confirm', $path['business']->booking_slug);
    $this->postJson($url, $payload)->assertOk();
    $this->postJson($url, $payload)->assertOk();
    expect(Appointment::query()->count())->toBe(1)->and($held['flow']->fresh()->status)->toBe('confirmed');
    $this->travelBack();
});
