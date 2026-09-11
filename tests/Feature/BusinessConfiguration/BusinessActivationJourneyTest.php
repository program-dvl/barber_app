<?php

use App\Domain\BusinessConfiguration\Services\ReadinessEvaluator;
use App\Domain\ClientRecords\Models\Client;
use App\Domain\PlatformAccess\Enums\StarterRole;
use App\Domain\PublicBooking\Services\PublicBookingService;
use App\Domain\SchedulingOperations\Models\Appointment;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
});

it('takes a new owner from focused activation workspaces to a received public booking', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-01 09:00:00', 'Asia/Kolkata')->utc());
    [$owner, $business] = createTenantMembership(StarterRole::Owner);
    activateTestSubscription($business);
    $business->update([
        'name' => 'Northstar Salon', 'booking_slug' => 'northstar-activation', 'business_type' => 'salon',
        'country_code' => 'IN', 'locale' => 'en-IN', 'currency_code' => 'INR', 'time_zone' => 'Asia/Kolkata',
        'week_starts_on' => 1, 'appointment_interval_minutes' => 15, 'tax_posture' => 'inclusive',
        'phone' => '+919000000000', 'email' => 'hello@northstar.test', 'address' => '12 Market Road, Pune',
        'default_cancellation_policy' => 'Please change or cancel at least 24 hours before the appointment.',
        'terms_url' => 'https://northstar.test/terms', 'privacy_url' => 'https://northstar.test/privacy',
        'online_booking_enabled' => true,
    ]);

    $this->actingAs($owner)->get(route('business.locations.index', $business))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->component('Activation/Location'));
    $this->actingAs($owner)->post(route('business.locations.activation.store', $business), [
        'name' => 'Northstar Salon', 'address' => '12 Market Road, Pune', 'time_zone' => 'Asia/Kolkata',
        'phone' => '+919000000000', 'email' => 'hello@northstar.test', 'working_days' => [1, 2, 3, 4, 5, 6, 7],
        'opens_at' => '09:00', 'closes_at' => '18:00',
    ])->assertRedirect()->assertSessionHasNoErrors();
    $location = $business->locations()->firstOrFail();

    $this->actingAs($owner)->get(route('business.team.index', $business))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->component('Team/Index'));
    $providerPayload = [
        'display_name' => 'Avery Singh', 'email' => 'avery@northstar.test', 'mobile' => '+919111111111',
        'title' => 'Senior stylist', 'online_visible' => true, 'location' => $location->public_id,
        'service_ids' => [], 'working_days' => [1, 2, 3, 4, 5, 6, 7], 'starts_at' => '09:00', 'ends_at' => '18:00',
    ];
    $this->actingAs($owner)->post(route('business.team.providers.store', $business), $providerPayload)
        ->assertRedirect()->assertSessionHasNoErrors();
    $staff = $business->staffProfiles()->firstOrFail();

    $this->actingAs($owner)->get(route('business.services.index', $business))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->component('Services/Index'));
    $servicePayload = [
        'category' => 'Hair', 'name' => 'Signature cut', 'description' => 'Consultation, cut and finish.',
        'price_type' => 'fixed', 'price_minor' => 5000, 'duration_minutes' => 45, 'processing_minutes' => 0,
        'cleanup_minutes' => 5, 'minimum_notice_minutes' => 0, 'maximum_advance_days' => 60,
        'deposit_type' => 'none', 'deposit_value' => 0, 'client_eligibility' => 'all',
        'consultation_required' => false, 'online_visible' => true, 'tax_category' => 'salon_service',
        'location_ids' => [$location->public_id], 'staff_ids' => [$staff->public_id],
    ];
    $this->actingAs($owner)->post(route('business.services.store', $business), $servicePayload)
        ->assertRedirect()->assertSessionHasNoErrors();
    $service = $business->services()->firstOrFail();

    // Safe retries update the same activation records instead of multiplying them.
    $this->actingAs($owner)->post(route('business.locations.activation.store', $business), [
        'location' => $location->public_id, 'name' => 'Northstar Salon', 'address' => '12 Market Road, Pune',
        'time_zone' => 'Asia/Kolkata', 'phone' => '+919000000000', 'email' => 'hello@northstar.test',
        'working_days' => [1, 2, 3, 4, 5, 6, 7], 'opens_at' => '09:00', 'closes_at' => '18:00',
    ])->assertRedirect();
    $this->actingAs($owner)->post(route('business.team.providers.store', $business), $providerPayload)->assertRedirect();
    $this->actingAs($owner)->post(route('business.services.store', $business), $servicePayload)->assertRedirect();
    expect($business->locations()->count())->toBe(1)
        ->and($business->staffProfiles()->count())->toBe(1)
        ->and($business->services()->count())->toBe(1);

    $this->actingAs($owner)->post(route('business.configuration.preview', $business))->assertRedirect();
    expect(app(ReadinessEvaluator::class)->evaluate($business->fresh())->publishable)->toBeTrue();
    $this->actingAs($owner)->post(route('business.configuration.publish', $business))->assertRedirect()->assertSessionHasNoErrors();
    expect($business->fresh()->configuration_published_at)->not->toBeNull();

    $this->actingAs($owner)->post(route('business.locations.activation.store', $business), [
        'location' => $location->public_id, 'name' => 'Northstar Salon', 'address' => '12 Market Road, Pune',
        'time_zone' => 'Asia/Kolkata', 'phone' => '+919000000000', 'email' => 'hello@northstar.test',
        'working_days' => [1, 2, 3, 4, 5, 6, 7], 'opens_at' => '10:00', 'closes_at' => '18:00',
    ])->assertRedirect()->assertSessionHasErrors('working_days');
    $this->actingAs($owner)->post(route('business.team.providers.store', $business), [
        ...$providerPayload, 'starts_at' => '10:00',
    ])->assertRedirect()->assertSessionHasErrors('working_days');
    expect($location->hours()->where('opens_at', '09:00')->count())->toBe(7)
        ->and($staff->availabilityRules()->where('starts_at', '09:00')->count())->toBe(7);

    $booking = app(PublicBookingService::class);
    $started = $booking->start($business->fresh());
    $slots = $booking->search($business->fresh(), $location->public_id, [$service->public_id], $staff->public_id, '2026-09-07', '2026-09-07', 'new');
    expect($slots)->not->toBeEmpty();
    $flow = $booking->hold($business->fresh(), $started['flow'], [
        'location' => $location->public_id, 'services' => [$service->public_id], 'staff' => $staff->public_id,
        'starts_at' => $slots[0]['starts_at_utc'], 'client_eligibility' => 'new',
    ], 'wave-two-hold');
    $result = $booking->confirm($business->fresh(), $flow, [
        'client_name' => 'Priya Shah', 'client_mobile' => '+919222222222', 'client_email' => 'priya@example.test',
        'communication_preferences' => ['email'], 'marketing_opt_in' => false,
    ], 'wave-two-confirm');

    expect($result['appointment']->status)->toBe('confirmed')
        ->and(Appointment::query()->where('business_id', $business->id)->count())->toBe(1)
        ->and(Client::query()->where('business_id', $business->id)->where('normalized_email', 'priya@example.test')->exists())->toBeTrue();

    $segmentIds = $service->segments()->orderBy('sequence')->pluck('id')->all();
    $this->actingAs($owner)->put(route('business.services.update', [$business, $service]), [
        ...$servicePayload, 'duration_minutes' => 50,
    ])->assertRedirect()->assertSessionHasNoErrors();
    expect($service->fresh()->duration_minutes)->toBe(50)
        ->and($service->segments()->orderBy('sequence')->pluck('id')->all())->toBe($segmentIds)
        ->and($result['appointment']->fresh()->status)->toBe('confirmed');
    $this->actingAs($owner)->patch(route('business.services.status', [$business, $service]), ['active' => false])
        ->assertRedirect()->assertSessionHasNoErrors();
    expect($service->fresh()->is_active)->toBeFalse()
        ->and($result['appointment']->fresh()->status)->toBe('confirmed');
    CarbonImmutable::setTestNow();
});

it('keeps activation workspaces role and tenant scoped', function () {
    [$owner, $business] = createTenantMembership(StarterRole::Owner);
    [$receptionist, $otherBusiness] = createTenantMembership(StarterRole::Receptionist);
    activateTestSubscription($business);
    activateTestSubscription($otherBusiness);

    $this->actingAs($owner)->get(route('business.services.index', $otherBusiness))->assertForbidden();
    $this->actingAs($receptionist)->get(route('business.team.index', $otherBusiness))->assertForbidden();
    $this->actingAs($receptionist)->get(route('business.locations.index', $otherBusiness))->assertForbidden();
});
