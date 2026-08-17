<?php

use App\Domain\BusinessConfiguration\Models\LocationHour;
use App\Domain\BusinessConfiguration\Models\LocationScheduleException;
use App\Domain\BusinessConfiguration\Models\PhysicalResource;
use App\Domain\BusinessConfiguration\Models\ResourceMaintenanceBlock;
use App\Domain\BusinessConfiguration\Models\Service;
use App\Domain\BusinessConfiguration\Models\ServiceResourceRequirement;
use App\Domain\BusinessConfiguration\Models\ServiceSegment;
use App\Domain\BusinessConfiguration\Models\StaffAvailabilityRule;
use App\Domain\BusinessConfiguration\Models\StaffServiceAssignment;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\Location;
use App\Domain\PlatformAccess\Models\StaffProfile;
use App\Domain\SchedulingOperations\Contracts\AvailabilityQuery;
use App\Domain\SchedulingOperations\Contracts\BookingCommitCommand;
use App\Domain\SchedulingOperations\Contracts\CapacityHoldCommand;
use App\Domain\SchedulingOperations\Contracts\CapacityHoldExpiryCommand;
use App\Domain\SchedulingOperations\Data\AvailabilitySearch;
use App\Domain\SchedulingOperations\Data\BookingLineRequest;
use App\Domain\SchedulingOperations\Data\BookingRequest;
use App\Domain\SchedulingOperations\Exceptions\BookingRuleViolation;
use App\Domain\SchedulingOperations\Models\Appointment;
use App\Domain\SchedulingOperations\Models\CapacityHold;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{business:Business,location:Location,staff:StaffProfile,service:Service,resource:PhysicalResource}
 */
function schedulingPath(string $timeZone = 'Asia/Kolkata', int $resourceQuantity = 1, bool $segments = false): array
{
    $business = Business::factory()->create([
        'appointment_interval_minutes' => 15,
        'time_zone' => $timeZone,
        'currency_code' => 'INR',
    ]);
    $location = Location::factory()->create(['business_id' => $business->id, 'time_zone' => $timeZone]);
    for ($day = 1; $day <= 7; $day++) {
        LocationHour::query()->create([
            'business_id' => $business->id, 'location_id' => $location->id, 'day_of_week' => $day,
            'opens_at' => '09:00', 'closes_at' => '18:00', 'sequence' => 1,
        ]);
    }
    $staff = StaffProfile::factory()->create(['business_id' => $business->id]);
    $staff->locations()->syncWithPivotValues([$location->id], ['business_id' => $business->id]);
    for ($day = 1; $day <= 7; $day++) {
        StaffAvailabilityRule::query()->create([
            'business_id' => $business->id, 'staff_profile_id' => $staff->id, 'location_id' => $location->id,
            'kind' => 'working', 'day_of_week' => $day, 'starts_at' => '09:00', 'ends_at' => '18:00',
        ]);
    }
    $service = Service::query()->create([
        'business_id' => $business->id, 'kind' => 'service', 'name' => 'Cut and finish', 'price_minor' => 5000,
        'currency_code' => 'INR', 'duration_minutes' => $segments ? 30 : 60,
        'processing_minutes' => $segments ? 30 : 0, 'cleanup_minutes' => $segments ? 15 : 0,
        'minimum_notice_minutes' => 60, 'maximum_advance_days' => 60,
        'client_eligibility' => 'all', 'is_active' => true, 'online_visible' => true,
    ]);
    $service->locations()->attach($location->id, ['business_id' => $business->id, 'is_eligible' => true]);
    StaffServiceAssignment::query()->create([
        'business_id' => $business->id, 'staff_profile_id' => $staff->id, 'service_id' => $service->id,
        'is_qualified' => true, 'is_active' => true, 'online_visible' => true,
    ]);
    if ($segments) {
        ServiceSegment::query()->create(['business_id' => $business->id, 'service_id' => $service->id, 'kind' => 'active', 'sequence' => 1, 'duration_minutes' => 30, 'occupies_staff' => true]);
        ServiceSegment::query()->create(['business_id' => $business->id, 'service_id' => $service->id, 'kind' => 'processing', 'sequence' => 2, 'duration_minutes' => 30, 'occupies_staff' => false]);
        ServiceSegment::query()->create(['business_id' => $business->id, 'service_id' => $service->id, 'kind' => 'cleanup', 'sequence' => 3, 'duration_minutes' => 15, 'occupies_staff' => true]);
    }
    $resource = PhysicalResource::query()->create([
        'business_id' => $business->id, 'location_id' => $location->id, 'type' => 'chair',
        'name' => 'Styling chairs', 'quantity' => $resourceQuantity, 'is_active' => true,
    ]);

    return compact('business', 'location', 'staff', 'service', 'resource');
}

function assignSchedulingStaff(Business $business, Location $location, Service $service, string $name): StaffProfile
{
    $staff = StaffProfile::factory()->create(['business_id' => $business->id, 'display_name' => $name]);
    $staff->locations()->syncWithPivotValues([$location->id], ['business_id' => $business->id]);
    for ($day = 1; $day <= 7; $day++) {
        StaffAvailabilityRule::query()->create([
            'business_id' => $business->id, 'staff_profile_id' => $staff->id, 'location_id' => $location->id,
            'kind' => 'working', 'day_of_week' => $day, 'starts_at' => '09:00', 'ends_at' => '18:00',
        ]);
    }
    StaffServiceAssignment::query()->create([
        'business_id' => $business->id, 'staff_profile_id' => $staff->id, 'service_id' => $service->id,
        'is_qualified' => true, 'is_active' => true, 'online_visible' => true,
    ]);

    return $staff;
}

function bookingRequest(array $path, string $localTime, ?int $staffId = null, ?CarbonImmutable $asOf = null, array $segmentStaff = []): BookingRequest
{
    $starts = CarbonImmutable::parse($localTime, $path['location']->time_zone)->utc();

    return new BookingRequest(
        $path['business']->id,
        $path['location']->id,
        $starts,
        [new BookingLineRequest($path['service']->id, $staffId ?? $path['staff']->id, $segmentStaff, false)],
        'online',
        'existing',
        $asOf ?? $starts->subDays(5),
    );
}

it('uses one query and commit rule engine and safely replays a direct booking', function () {
    $path = schedulingPath(resourceQuantity: 1);
    ServiceResourceRequirement::query()->create([
        'business_id' => $path['business']->id, 'service_id' => $path['service']->id,
        'physical_resource_id' => $path['resource']->id, 'quantity' => 1,
    ]);
    $date = CarbonImmutable::parse('2026-08-17', $path['location']->time_zone);
    $slots = app(AvailabilityQuery::class)->search(new AvailabilitySearch(
        $path['business']->id, $path['location']->id, $date, $date,
        [new BookingLineRequest($path['service']->id, $path['staff']->id, [], false)],
        asOfUtc: $date->subDays(5)->utc(),
    ));
    expect($slots)->not->toBeEmpty();

    $request = bookingRequest($path, '2026-08-17 10:00:00');
    $first = app(BookingCommitCommand::class)->commit($request, 'direct-001');
    $replay = app(BookingCommitCommand::class)->commit($request, 'direct-001');

    expect($replay->is($first))->toBeTrue()
        ->and(Appointment::query()->count())->toBe(1)
        ->and($first->serviceLines)->toHaveCount(1)
        ->and($first->segments)->toHaveCount(1)
        ->and($first->resourceClaims)->toHaveCount(1)
        ->and($first->statusHistory)->toHaveCount(1)
        ->and($first->serviceLines->first()->configuration_snapshot['priceMinor'])->toBe(5000);
    expect(fn () => $first->statusHistory->first()->update(['status' => 'cancelled_by_shop']))
        ->toThrow(LogicException::class, 'append-only');

    expect(fn () => app(BookingCommitCommand::class)->commit(bookingRequest($path, '2026-08-17 11:00:00'), 'direct-001'))
        ->toThrow(BookingRuleViolation::class, 'already used');
});

it('expires capacity holds deterministically and returns the stable hold on replay', function () {
    $path = schedulingPath();
    $request = bookingRequest($path, '2026-08-18 10:00:00');
    $hold = app(CapacityHoldCommand::class)->hold($request, 'hold-001', 60);
    $replay = app(CapacityHoldCommand::class)->hold($request, 'hold-001', 60);

    expect($replay->is($hold))->toBeTrue()
        ->and($hold->owner_key)->toStartWith('command:')
        ->and(CapacityHold::query()->count())->toBe(1);
    expect(fn () => app(BookingCommitCommand::class)->commit(bookingRequest($path, '2026-08-18 10:00:00'), 'competitor'))
        ->toThrow(BookingRuleViolation::class, 'not available');

    expect(app(CapacityHoldExpiryCommand::class)->expire($request->now()->addSeconds(61), $path['business']->id))->toBe(1);
    $appointment = app(BookingCommitCommand::class)->commit(
        new BookingRequest(
            $request->businessId, $request->locationId, $request->startsAtUtc, $request->lines,
            $request->source, $request->clientEligibility, $request->now()->addSeconds(61),
        ),
        'after-expiry',
    );
    expect($appointment->status)->toBe('confirmed')->and($hold->fresh()->status)->toBe('expired');
    expect(fn () => app(BookingCommitCommand::class)->confirmHold(
        $path['business']->id, $hold->public_id, 'confirm-expired', $request->now()->addSeconds(61),
    ))->toThrow(BookingRuleViolation::class, 'expired');

    $unprocessedRequest = bookingRequest($path, '2026-08-18 12:00:00');
    $unprocessed = app(CapacityHoldCommand::class)->hold($unprocessedRequest, 'unprocessed-expiry', 30);
    expect(fn () => app(BookingCommitCommand::class)->confirmHold(
        $path['business']->id, $unprocessed->public_id, 'confirm-unprocessed-expiry', $unprocessedRequest->now()->addSeconds(31),
    ))->toThrow(BookingRuleViolation::class, 'expired');
    expect($unprocessed->fresh()->status)->toBe('expired');
});

it('enforces pooled resource quantity when staff is independently free', function () {
    $path = schedulingPath(resourceQuantity: 2);
    $second = assignSchedulingStaff($path['business'], $path['location'], $path['service'], 'Second');
    $third = assignSchedulingStaff($path['business'], $path['location'], $path['service'], 'Third');
    ServiceResourceRequirement::query()->create([
        'business_id' => $path['business']->id, 'service_id' => $path['service']->id,
        'physical_resource_id' => $path['resource']->id, 'quantity' => 1,
    ]);

    app(BookingCommitCommand::class)->commit(bookingRequest($path, '2026-08-19 10:00:00', $path['staff']->id), 'quantity-1');
    app(BookingCommitCommand::class)->commit(bookingRequest($path, '2026-08-19 10:00:00', $second->id), 'quantity-2');
    try {
        app(BookingCommitCommand::class)->commit(bookingRequest($path, '2026-08-19 10:00:00', $third->id), 'quantity-3');
        test()->fail('Expected resource capacity denial.');
    } catch (BookingRuleViolation $violation) {
        expect($violation->ruleCode)->toBe('RESOURCE_UNAVAILABLE')
            ->and($violation->toDomainError()['message'])->not->toContain('appointment')
            ->and(Appointment::query()->count())->toBe(2);
    }
});

it('releases staff but retains configured resources through a processing segment', function () {
    $path = schedulingPath(resourceQuantity: 1, segments: true);
    $processing = ServiceSegment::query()->where('service_id', $path['service']->id)->where('kind', 'processing')->firstOrFail();
    ServiceResourceRequirement::query()->create([
        'business_id' => $path['business']->id, 'service_id' => $path['service']->id,
        'service_segment_id' => $processing->id, 'physical_resource_id' => $path['resource']->id, 'quantity' => 1,
    ]);
    app(BookingCommitCommand::class)->commit(bookingRequest($path, '2026-08-20 09:00:00'), 'processing-main');

    $short = Service::query()->create([
        'business_id' => $path['business']->id, 'kind' => 'service', 'name' => 'Consult', 'price_minor' => 1000,
        'currency_code' => 'INR', 'duration_minutes' => 30, 'minimum_notice_minutes' => 0,
        'maximum_advance_days' => 60, 'client_eligibility' => 'all', 'is_active' => true, 'online_visible' => true,
    ]);
    $short->locations()->attach($path['location']->id, ['business_id' => $path['business']->id, 'is_eligible' => true]);
    StaffServiceAssignment::query()->create([
        'business_id' => $path['business']->id, 'staff_profile_id' => $path['staff']->id, 'service_id' => $short->id,
        'is_qualified' => true, 'is_active' => true, 'online_visible' => true,
    ]);
    $request = new BookingRequest(
        $path['business']->id, $path['location']->id,
        CarbonImmutable::parse('2026-08-20 09:30:00', $path['location']->time_zone)->utc(),
        [new BookingLineRequest($short->id, $path['staff']->id, [], false)],
        asOfUtc: CarbonImmutable::parse('2026-08-15 00:00:00Z'),
    );
    $appointment = app(BookingCommitCommand::class)->commit($request, 'processing-release');
    expect($appointment->starts_at_utc->format('H:i'))->toBe('04:00');

    ServiceResourceRequirement::query()->create([
        'business_id' => $path['business']->id, 'service_id' => $short->id,
        'physical_resource_id' => $path['resource']->id, 'quantity' => 1,
    ]);
    $resourceOnlyStaff = assignSchedulingStaff($path['business'], $path['location'], $short, 'Resource-only contender');
    $resourceRequest = new BookingRequest(
        $request->businessId, $request->locationId, $request->startsAtUtc,
        [new BookingLineRequest($short->id, $resourceOnlyStaff->id, [], false)],
        asOfUtc: $request->asOfUtc,
    );
    expect(fn () => app(BookingCommitCommand::class)->commit($resourceRequest, 'processing-resource'))
        ->toThrow(BookingRuleViolation::class, 'equipment or space');
});

it('commits a segmented provider handoff atomically and leaves no partial visit on conflict', function () {
    $path = schedulingPath(segments: true);
    $handoff = assignSchedulingStaff($path['business'], $path['location'], $path['service'], 'Handoff provider');
    $segmentStaff = [1 => $path['staff']->id, 2 => $path['staff']->id, 3 => $handoff->id];
    $first = app(BookingCommitCommand::class)->commit(
        bookingRequest($path, '2026-08-21 09:00:00', $path['staff']->id, segmentStaff: $segmentStaff),
        'handoff-1',
    );
    expect($first->segments->pluck('staff_profile_id')->all())->toBe([$path['staff']->id, $path['staff']->id, $handoff->id]);

    $otherPrimary = assignSchedulingStaff($path['business'], $path['location'], $path['service'], 'Other primary');
    $before = Appointment::query()->count();
    expect(fn () => app(BookingCommitCommand::class)->commit(
        bookingRequest($path, '2026-08-21 09:00:00', $otherPrimary->id, segmentStaff: [3 => $handoff->id]),
        'handoff-conflict',
    ))->toThrow(BookingRuleViolation::class);
    expect(Appointment::query()->count())->toBe($before);
});

it('revalidates stale search and hides closure break maintenance and private schedule reasons', function () {
    $path = schedulingPath();
    ServiceResourceRequirement::query()->create([
        'business_id' => $path['business']->id, 'service_id' => $path['service']->id,
        'physical_resource_id' => $path['resource']->id, 'quantity' => 1,
    ]);
    $date = CarbonImmutable::parse('2026-08-24', $path['location']->time_zone);
    $search = new AvailabilitySearch(
        $path['business']->id, $path['location']->id, $date, $date,
        [new BookingLineRequest($path['service']->id, $path['staff']->id, [], false)],
        asOfUtc: $date->subDays(5)->utc(),
    );
    expect(app(AvailabilityQuery::class)->search($search))->not->toBeEmpty();

    LocationScheduleException::query()->create([
        'business_id' => $path['business']->id, 'location_id' => $path['location']->id,
        'kind' => 'temporary_closure', 'starts_on' => '2026-08-24', 'ends_on' => '2026-08-24',
        'name' => 'Private closure', 'reason' => 'Private operational reason',
    ]);
    try {
        app(BookingCommitCommand::class)->commit(bookingRequest($path, '2026-08-24 10:00:00'), 'stale-closure');
        test()->fail('Expected stale closure denial.');
    } catch (BookingRuleViolation $violation) {
        expect($violation->ruleCode)->toBe('LOCATION_UNAVAILABLE')
            ->and($violation->getMessage())->not->toContain('Private');
    }

    $path['location']->scheduleExceptions()->delete();
    StaffAvailabilityRule::query()->create([
        'business_id' => $path['business']->id, 'staff_profile_id' => $path['staff']->id,
        'location_id' => $path['location']->id, 'kind' => 'break', 'day_of_week' => 1,
        'starts_at' => '10:00', 'ends_at' => '11:00', 'reason' => 'Private medical detail',
    ]);
    expect(fn () => app(BookingCommitCommand::class)->commit(bookingRequest($path, '2026-08-24 10:00:00'), 'private-break'))
        ->toThrow(BookingRuleViolation::class, 'selected team member is not available');

    ResourceMaintenanceBlock::query()->create([
        'business_id' => $path['business']->id, 'physical_resource_id' => $path['resource']->id,
        'starts_at_utc' => CarbonImmutable::parse('2026-08-24 10:00:00', $path['location']->time_zone)->utc(),
        'ends_at_utc' => CarbonImmutable::parse('2026-08-24 11:00:00', $path['location']->time_zone)->utc(),
        'time_zone' => $path['location']->time_zone, 'local_starts_at' => '2026-08-24 10:00:00',
        'local_ends_at' => '2026-08-24 11:00:00', 'reason' => 'Private equipment detail',
    ]);
    StaffAvailabilityRule::query()->where('kind', 'break')->delete();
    expect(fn () => app(BookingCommitCommand::class)->commit(bookingRequest($path, '2026-08-24 10:00:00'), 'private-maintenance'))
        ->toThrow(BookingRuleViolation::class, 'equipment or space');
});

it('handles local midnight and daylight-saving transitions as real instants', function () {
    $path = schedulingPath('America/New_York');
    $path['service']->update(['duration_minutes' => 30, 'minimum_notice_minutes' => 0]);
    LocationHour::query()->where('location_id', $path['location']->id)->delete();
    StaffAvailabilityRule::query()->where('staff_profile_id', $path['staff']->id)->delete();
    LocationHour::query()->create([
        'business_id' => $path['business']->id, 'location_id' => $path['location']->id,
        'day_of_week' => 7, 'opens_at' => '00:00', 'closes_at' => '04:00', 'sequence' => 1,
    ]);
    StaffAvailabilityRule::query()->create([
        'business_id' => $path['business']->id, 'staff_profile_id' => $path['staff']->id,
        'location_id' => $path['location']->id, 'kind' => 'working', 'day_of_week' => 7,
        'starts_at' => '00:00', 'ends_at' => '04:00',
    ]);
    $dst = bookingRequest(
        $path,
        '2026-03-08 01:30:00',
        asOf: CarbonImmutable::parse('2026-03-01T00:00:00Z'),
    );
    $appointment = app(BookingCommitCommand::class)->commit($dst, 'dst-spring');
    expect($appointment->local_starts_at)->toContain('01:30:00 -05:00')
        ->and($appointment->local_ends_at)->toContain('03:00:00 -04:00')
        ->and($appointment->starts_at_utc->diffInMinutes($appointment->ends_at_utc))->toEqual(30);

    $fallDate = CarbonImmutable::parse('2026-11-01', $path['location']->time_zone);
    $fallSlots = app(AvailabilityQuery::class)->search(new AvailabilitySearch(
        $path['business']->id, $path['location']->id, $fallDate, $fallDate,
        [new BookingLineRequest($path['service']->id, $path['staff']->id, [], false)],
        limit: 20, asOfUtc: CarbonImmutable::parse('2026-10-25T00:00:00Z'),
    ));
    $repeatedHour = collect($fallSlots)->pluck('local_starts_at')->filter(fn (string $local) => str_contains($local, 'T01:'));
    expect($repeatedHour->contains(fn (string $local) => str_ends_with($local, '-04:00')))->toBeTrue()
        ->and($repeatedHour->contains(fn (string $local) => str_ends_with($local, '-05:00')))->toBeTrue();

    LocationHour::query()->where('location_id', $path['location']->id)->delete();
    StaffAvailabilityRule::query()->where('staff_profile_id', $path['staff']->id)->delete();
    LocationHour::query()->create([
        'business_id' => $path['business']->id, 'location_id' => $path['location']->id,
        'day_of_week' => 1, 'opens_at' => '22:00', 'closes_at' => '02:00', 'sequence' => 1,
    ]);
    StaffAvailabilityRule::query()->create([
        'business_id' => $path['business']->id, 'staff_profile_id' => $path['staff']->id,
        'location_id' => $path['location']->id, 'kind' => 'working', 'day_of_week' => 1,
        'starts_at' => '22:00', 'ends_at' => '02:00',
    ]);
    $midnight = bookingRequest(
        $path,
        '2026-08-24 23:45:00',
        asOf: CarbonImmutable::parse('2026-08-20T00:00:00Z'),
    );
    $crossing = app(BookingCommitCommand::class)->commit($midnight, 'local-midnight');
    expect($crossing->local_ends_at)->toContain('2026-08-25 00:15:00');
});

it('honors preferred staff, falls back only when any qualified staff is allowed, and enforces booking windows', function () {
    $path = schedulingPath();
    $alternative = assignSchedulingStaff($path['business'], $path['location'], $path['service'], 'Alternative');
    app(BookingCommitCommand::class)->commit(bookingRequest($path, '2026-08-25 10:00:00'), 'preferred-taken');
    $starts = CarbonImmutable::parse('2026-08-25 10:00:00', $path['location']->time_zone)->utc();
    $anyRequest = new BookingRequest(
        $path['business']->id, $path['location']->id, $starts,
        [new BookingLineRequest($path['service']->id, $path['staff']->id, [], true)],
        asOfUtc: $starts->subDays(5),
    );
    $fallback = app(BookingCommitCommand::class)->commit($anyRequest, 'preferred-fallback');
    expect($fallback->serviceLines->first()->primary_staff_profile_id)->toBe($alternative->id);

    $onlyPreferred = new BookingRequest(
        $path['business']->id, $path['location']->id, $starts,
        [new BookingLineRequest($path['service']->id, $path['staff']->id, [], false)],
        asOfUtc: $starts->subDays(5),
    );
    expect(fn () => app(BookingCommitCommand::class)->commit($onlyPreferred, 'preferred-only'))
        ->toThrow(BookingRuleViolation::class, 'selected team member is not available');

    $notice = new BookingRequest(
        $path['business']->id, $path['location']->id, $starts->addDay(), $onlyPreferred->lines,
        asOfUtc: $starts->addDay()->subMinutes(30),
    );
    try {
        app(BookingCommitCommand::class)->commit($notice, 'notice-window');
        test()->fail('Expected notice-window denial.');
    } catch (BookingRuleViolation $violation) {
        expect($violation->ruleCode)->toBe('NOTICE_WINDOW');
    }

    $advance = new BookingRequest(
        $path['business']->id, $path['location']->id, $starts->addDays(61), $onlyPreferred->lines,
        asOfUtc: $starts,
    );
    try {
        app(BookingCommitCommand::class)->commit($advance, 'advance-window');
        test()->fail('Expected advance-window denial.');
    } catch (BookingRuleViolation $violation) {
        expect($violation->ruleCode)->toBe('ADVANCE_WINDOW');
    }
});

it('confirms an unchanged hold once and rejects a hold whose commercial snapshot became stale', function () {
    $path = schedulingPath();
    $request = bookingRequest($path, '2026-08-26 10:00:00');
    $hold = app(CapacityHoldCommand::class)->hold($request, 'confirmable-hold', 600);
    $confirmed = app(BookingCommitCommand::class)->confirmHold(
        $path['business']->id, $hold->public_id, 'confirm-hold', $request->now()->addMinute(),
    );
    $replay = app(BookingCommitCommand::class)->confirmHold(
        $path['business']->id, $hold->public_id, 'confirm-hold', $request->now()->addMinute(),
    );
    expect($replay->is($confirmed))->toBeTrue()
        ->and($hold->fresh()->status)->toBe('confirmed')
        ->and(Appointment::query()->count())->toBe(1);

    $staleRequest = bookingRequest($path, '2026-08-26 12:00:00');
    $staleHold = app(CapacityHoldCommand::class)->hold($staleRequest, 'stale-hold', 600);
    $path['service']->update(['price_minor' => 6000]);
    expect(fn () => app(BookingCommitCommand::class)->confirmHold(
        $path['business']->id, $staleHold->public_id, 'confirm-stale-hold', $staleRequest->now()->addMinute(),
    ))->toThrow(BookingRuleViolation::class, 'Availability changed');
    expect($staleHold->fresh()->status)->toBe('active')->and(Appointment::query()->count())->toBe(1);
});

it('prevents travel-impossible overlap when the same staff is requested at another location', function () {
    $path = schedulingPath();
    app(BookingCommitCommand::class)->commit(bookingRequest($path, '2026-08-27 10:00:00'), 'first-location');
    $other = Location::factory()->create([
        'business_id' => $path['business']->id, 'name' => 'Other location', 'time_zone' => $path['location']->time_zone,
    ]);
    LocationHour::query()->create([
        'business_id' => $path['business']->id, 'location_id' => $other->id, 'day_of_week' => 4,
        'opens_at' => '09:00', 'closes_at' => '18:00', 'sequence' => 1,
    ]);
    $path['staff']->locations()->attach($other->id, ['business_id' => $path['business']->id]);
    StaffAvailabilityRule::query()->create([
        'business_id' => $path['business']->id, 'staff_profile_id' => $path['staff']->id, 'location_id' => $other->id,
        'kind' => 'working', 'day_of_week' => 4, 'starts_at' => '09:00', 'ends_at' => '18:00',
    ]);
    $path['service']->locations()->attach($other->id, ['business_id' => $path['business']->id, 'is_eligible' => true]);
    $request = new BookingRequest(
        $path['business']->id, $other->id,
        CarbonImmutable::parse('2026-08-27 10:00:00', $other->time_zone)->utc(),
        [new BookingLineRequest($path['service']->id, $path['staff']->id, [], false)],
        asOfUtc: CarbonImmutable::parse('2026-08-22T00:00:00Z'),
    );
    expect(fn () => app(BookingCommitCommand::class)->commit($request, 'other-location-overlap'))
        ->toThrow(BookingRuleViolation::class, 'selected team member is not available');
});

it('requires eligible add-ons and commits them as explicit contiguous service lines', function () {
    $path = schedulingPath();
    $addon = Service::query()->create([
        'business_id' => $path['business']->id, 'kind' => 'addon', 'name' => 'Beard trim',
        'price_minor' => 1500, 'currency_code' => 'INR', 'duration_minutes' => 15,
        'minimum_notice_minutes' => 0, 'maximum_advance_days' => 60,
        'client_eligibility' => 'all', 'is_active' => true, 'online_visible' => true,
    ]);
    $addon->locations()->attach($path['location']->id, ['business_id' => $path['business']->id, 'is_eligible' => true]);
    StaffServiceAssignment::query()->create([
        'business_id' => $path['business']->id, 'staff_profile_id' => $path['staff']->id,
        'service_id' => $addon->id, 'is_qualified' => true, 'is_active' => true, 'online_visible' => true,
    ]);
    $starts = CarbonImmutable::parse('2026-08-28 10:00:00', $path['location']->time_zone)->utc();
    $standalone = new BookingRequest(
        $path['business']->id, $path['location']->id, $starts,
        [new BookingLineRequest($addon->id, $path['staff']->id, [], false)],
        asOfUtc: $starts->subDays(5),
    );
    expect(fn () => app(BookingCommitCommand::class)->commit($standalone, 'standalone-addon'))
        ->toThrow(BookingRuleViolation::class, 'cannot be combined');

    $path['service']->addons()->attach($addon->id, ['business_id' => $path['business']->id]);
    $combined = new BookingRequest(
        $path['business']->id, $path['location']->id, $starts,
        [
            new BookingLineRequest($path['service']->id, $path['staff']->id, [], false),
            new BookingLineRequest($addon->id, $path['staff']->id, [], false),
        ],
        asOfUtc: $starts->subDays(5),
    );
    $appointment = app(BookingCommitCommand::class)->commit($combined, 'combined-addon');
    expect($appointment->serviceLines)->toHaveCount(2)
        ->and($appointment->price_minor)->toBe(6500)
        ->and($appointment->starts_at_utc->diffInMinutes($appointment->ends_at_utc))->toEqual(75);
});

it('applies staff duration variants to explicit segment occupancy and the immutable snapshot', function () {
    $path = schedulingPath(segments: true);
    StaffServiceAssignment::query()->where('service_id', $path['service']->id)->update([
        'duration_minutes' => 45, 'processing_minutes' => 15, 'cleanup_minutes' => 10,
    ]);
    $appointment = app(BookingCommitCommand::class)->commit(
        bookingRequest($path, '2026-08-29 10:00:00'),
        'segmented-staff-variant',
    );
    expect($appointment->segments->map(fn ($segment) => (int) $segment->starts_at_utc->diffInMinutes($segment->ends_at_utc))->all())
        ->toBe([45, 15, 10])
        ->and($appointment->serviceLines->first()->configuration_snapshot['bookableMinutes'])->toBe(70)
        ->and($appointment->starts_at_utc->diffInMinutes($appointment->ends_at_utc))->toEqual(70);
});
