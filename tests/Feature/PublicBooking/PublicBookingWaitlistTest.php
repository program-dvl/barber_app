<?php

use App\Domain\BusinessConfiguration\Models\LocationHour;
use App\Domain\BusinessConfiguration\Models\Service;
use App\Domain\BusinessConfiguration\Models\StaffAvailabilityRule;
use App\Domain\BusinessConfiguration\Models\StaffServiceAssignment;
use App\Domain\ClientRecords\Models\Client;
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
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
