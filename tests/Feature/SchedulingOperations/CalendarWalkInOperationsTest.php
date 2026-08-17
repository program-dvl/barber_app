<?php

use App\Domain\BusinessConfiguration\Models\LocationHour;
use App\Domain\BusinessConfiguration\Models\Service;
use App\Domain\BusinessConfiguration\Models\StaffAvailabilityRule;
use App\Domain\BusinessConfiguration\Models\StaffServiceAssignment;
use App\Domain\PlatformAccess\Enums\StarterRole;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\Location;
use App\Domain\PlatformAccess\Models\Membership;
use App\Domain\PlatformAccess\Models\StaffProfile;
use App\Domain\PlatformAccess\Services\MembershipAccessManager;
use App\Domain\SchedulingOperations\Contracts\AppointmentLifecycleCommand;
use App\Domain\SchedulingOperations\Contracts\BookingCommitCommand;
use App\Domain\SchedulingOperations\Contracts\CalendarQuery;
use App\Domain\SchedulingOperations\Data\BookingLineRequest;
use App\Domain\SchedulingOperations\Data\BookingRequest;
use App\Domain\SchedulingOperations\Data\CalendarFilter;
use App\Domain\SchedulingOperations\Exceptions\BookingRuleViolation;
use App\Domain\SchedulingOperations\Models\Appointment;
use App\Domain\SchedulingOperations\Models\AppointmentChange;
use App\Domain\SchedulingOperations\Models\OperationalException;
use App\Domain\SchedulingOperations\Models\OperationalNotificationEvent;
use App\Domain\SchedulingOperations\Models\ScheduleBlock;
use App\Domain\SchedulingOperations\Models\WalkInEntry;
use App\Domain\SchedulingOperations\Models\WalkInHistory;
use App\Domain\SchedulingOperations\Services\OperationalExceptionService;
use App\Domain\SchedulingOperations\Services\ScheduleBlockService;
use App\Domain\SchedulingOperations\Services\WalkInQueueService;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Database\Seeders\FrontDeskDaySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/** @return array{business:Business,location:Location,staff:StaffProfile,service:Service,user:User,membership:Membership} */
function operationalPath(StarterRole $role = StarterRole::Owner, int $noticeMinutes = 0): array
{
    $business = Business::factory()->create(['appointment_interval_minutes' => 15, 'time_zone' => 'Asia/Kolkata', 'currency_code' => 'INR']);
    $location = Location::factory()->create(['business_id' => $business->id, 'time_zone' => 'Asia/Kolkata']);
    for ($day = 1; $day <= 7; $day++) {
        LocationHour::query()->create(['business_id' => $business->id, 'location_id' => $location->id, 'day_of_week' => $day, 'opens_at' => '09:00', 'closes_at' => '18:00', 'sequence' => 1]);
    }
    $user = User::factory()->create(['email_verified_at' => now()]);
    $membership = Membership::factory()->create(['business_id' => $business->id, 'user_id' => $user->id]);
    app(MembershipAccessManager::class)->assignStarterRole($membership, $role, $user, 'Operational fixture.');
    $membership->locations()->syncWithPivotValues([$location->id], ['business_id' => $business->id]);
    $staff = StaffProfile::factory()->create([
        'business_id' => $business->id, 'membership_id' => $membership->id, 'user_id' => $user->id, 'display_name' => 'Avery',
    ]);
    $staff->locations()->syncWithPivotValues([$location->id], ['business_id' => $business->id]);
    for ($day = 1; $day <= 7; $day++) {
        StaffAvailabilityRule::query()->create([
            'business_id' => $business->id, 'staff_profile_id' => $staff->id, 'location_id' => $location->id,
            'kind' => 'working', 'day_of_week' => $day, 'starts_at' => '09:00', 'ends_at' => '18:00',
        ]);
    }
    $service = Service::query()->create([
        'business_id' => $business->id, 'kind' => 'service', 'name' => 'Cut', 'price_minor' => 3000,
        'currency_code' => 'INR', 'duration_minutes' => 60, 'minimum_notice_minutes' => $noticeMinutes,
        'maximum_advance_days' => 60, 'client_eligibility' => 'all', 'is_active' => true, 'online_visible' => true,
    ]);
    $service->locations()->attach($location->id, ['business_id' => $business->id, 'is_eligible' => true]);
    StaffServiceAssignment::query()->create([
        'business_id' => $business->id, 'staff_profile_id' => $staff->id, 'service_id' => $service->id,
        'is_qualified' => true, 'is_active' => true, 'online_visible' => true,
    ]);

    return compact('business', 'location', 'staff', 'service', 'user', 'membership');
}

function operationalBooking(array $path, string $localTime, string $key, ?CarbonImmutable $asOf = null, string $source = 'reception'): Appointment
{
    return app(BookingCommitCommand::class)->commit(new BookingRequest(
        $path['business']->id,
        $path['location']->id,
        CarbonImmutable::parse($localTime, $path['location']->time_zone)->utc(),
        [new BookingLineRequest($path['service']->id, $path['staff']->id, [], false)],
        $source,
        'existing',
        $asOf ?? CarbonImmutable::parse($localTime, $path['location']->time_zone)->subDay()->utc(),
        null,
        'user',
        $path['user']->id,
        'Jordan Lee',
        '+919999999999',
    ), $key);
}

it('enforces the complete controlled lifecycle with actor reason replay and stale-edit evidence', function () {
    $path = operationalPath();
    $appointment = operationalBooking($path, '2026-08-17 10:00', 'lifecycle-booking');
    $lifecycle = app(AppointmentLifecycleCommand::class);

    expect(fn () => $lifecycle->transition($appointment, 'late', 'late-without-reason', 1, 'calendar', 'user', $path['user']->id))
        ->toThrow(BookingRuleViolation::class, 'Explain why');
    $late = $lifecycle->transition($appointment, 'late', 'late', 1, 'calendar', 'user', $path['user']->id, 'Traffic delay.');
    $lateReplay = $lifecycle->transition($appointment, 'late', 'late', 1, 'calendar', 'user', $path['user']->id, 'Traffic delay.');
    expect($lateReplay->id)->toBe($late->id);
    $arrived = $lifecycle->transition($late, 'arrived', 'arrive', 2, 'calendar', 'user', $path['user']->id);
    $checkedIn = $lifecycle->transition($arrived, 'checked_in', 'check-in', 3, 'calendar', 'user', $path['user']->id);
    $inService = $lifecycle->transition($checkedIn, 'in_service', 'start-service', 4, 'calendar', 'user', $path['user']->id);
    $completed = $lifecycle->transition($inService, 'completed', 'complete', 5, 'calendar', 'user', $path['user']->id);

    expect($completed->status)->toBe('completed')
        ->and($completed->version)->toBe(6)
        ->and($completed->statusHistory)->toHaveCount(6)
        ->and($completed->statusHistory->pluck('actor_id')->filter()->unique()->all())->toBe([$path['user']->id])
        ->and(AppointmentChange::query()->where('appointment_id', $completed->id)->count())->toBe(5)
        ->and(OperationalNotificationEvent::query()->where('subject_id', $completed->id)->count())->toBe(6);
    expect(fn () => $lifecycle->transition($completed, 'in_service', 'invalid-terminal', 6, 'calendar'))
        ->toThrow(BookingRuleViolation::class, 'not allowed');
    expect(fn () => $lifecycle->updateNotes($completed, 'Changed elsewhere', 'stale-note', 5, 'calendar'))
        ->toThrow(BookingRuleViolation::class, 'another session');
});

it('revalidates reschedule resize reassign and manager policy override while preserving linked history', function () {
    $path = operationalPath(noticeMinutes: 60);
    $now = CarbonImmutable::parse('2026-08-18 09:00', 'Asia/Kolkata')->utc();
    $appointment = operationalBooking($path, '2026-08-18 11:00', 'replace-original', $now);
    $second = StaffProfile::factory()->create(['business_id' => $path['business']->id, 'display_name' => 'Morgan']);
    $second->locations()->syncWithPivotValues([$path['location']->id], ['business_id' => $path['business']->id]);
    for ($day = 1; $day <= 7; $day++) {
        StaffAvailabilityRule::query()->create(['business_id' => $path['business']->id, 'staff_profile_id' => $second->id, 'location_id' => $path['location']->id, 'kind' => 'working', 'day_of_week' => $day, 'starts_at' => '09:00', 'ends_at' => '18:00']);
    }
    StaffServiceAssignment::query()->create(['business_id' => $path['business']->id, 'staff_profile_id' => $second->id, 'service_id' => $path['service']->id, 'is_qualified' => true, 'is_active' => true, 'online_visible' => true]);

    $request = new BookingRequest(
        $path['business']->id, $path['location']->id, CarbonImmutable::parse('2026-08-18 12:00', 'Asia/Kolkata')->utc(),
        [new BookingLineRequest($path['service']->id, $second->id, [], false, 90)], 'reception', 'existing', $now,
        null, 'user', $path['user']->id, 'Jordan Lee', '+919999999999', null,
    );
    $replacement = app(AppointmentLifecycleCommand::class)->replace($appointment, $request, 'reassign', 'replace-operation', 1, 'Staff and duration changed at client request.');
    $original = $appointment->fresh();

    expect($original->status)->toBe('rescheduled')
        ->and($original->rescheduled_to_appointment_id)->toBe($replacement->id)
        ->and($replacement->rescheduled_from_appointment_id)->toBe($original->id)
        ->and($replacement->segments->first()->staff_profile_id)->toBe($second->id)
        ->and($replacement->starts_at_utc->diffInMinutes($replacement->ends_at_utc))->toEqual(90)
        ->and($original->changes->first()->reason)->toContain('client request');
    $collision = new BookingRequest(
        $path['business']->id, $path['location']->id, CarbonImmutable::parse('2026-08-18 12:00', 'Asia/Kolkata')->utc(),
        [new BookingLineRequest($path['service']->id, $second->id, [], false)], 'reception', 'existing', $now,
    );
    expect(fn () => app(BookingCommitCommand::class)->commit($collision, 'replacement-collision'))->toThrow(BookingRuleViolation::class);

    $insideNotice = new BookingRequest(
        $path['business']->id, $path['location']->id, CarbonImmutable::parse('2026-08-18 09:30', 'Asia/Kolkata')->utc(),
        [new BookingLineRequest($path['service']->id, $path['staff']->id, [], false)], 'reception', 'existing', $now,
    );
    expect(fn () => app(BookingCommitCommand::class)->commit($insideNotice, 'notice-denied'))->toThrow(BookingRuleViolation::class, 'minimum booking notice');
    $overridden = new BookingRequest(
        $insideNotice->businessId, $insideNotice->locationId, $insideNotice->startsAtUtc, $insideNotice->lines,
        'reception', 'existing', $now, null, 'user', $path['user']->id, 'Policy override', '+919999999999', null,
        ['NOTICE_WINDOW'], 'Client is already on premises; manager accepted the warning.',
    );
    $overrideAppointment = app(BookingCommitCommand::class)->commit($overridden, 'notice-overridden');
    expect($overrideAppointment->changes->first()->kind)->toBe('manager_override')
        ->and($overrideAppointment->changes->first()->metadata['warning_acknowledged'])->toBeTrue();
});

it('blocks time without bypassing capacity and returns accessible calendar cues within the performance target', function () {
    $path = operationalPath();
    operationalBooking($path, '2026-08-19 10:00', 'calendar-appointment');
    $blocks = app(ScheduleBlockService::class);
    expect(fn () => $blocks->create(
        $path['business']->id, $path['location']->id, $path['staff']->id, 'personal_block', 'Admin',
        CarbonImmutable::parse('2026-08-19 10:30', 'Asia/Kolkata')->utc(), CarbonImmutable::parse('2026-08-19 11:30', 'Asia/Kolkata')->utc(),
        'Cannot overlap a client.', 'user', $path['user']->id,
    ))->toThrow(BookingRuleViolation::class, 'overlaps existing work');
    $block = $blocks->create(
        $path['business']->id, $path['location']->id, $path['staff']->id, 'staff_break', 'Lunch',
        CarbonImmutable::parse('2026-08-19 12:00', 'Asia/Kolkata')->utc(), CarbonImmutable::parse('2026-08-19 12:30', 'Asia/Kolkata')->utc(),
        'Scheduled meal break.', 'user', $path['user']->id,
    );
    expect(fn () => operationalBooking($path, '2026-08-19 12:00', 'block-collision'))
        ->toThrow(BookingRuleViolation::class, 'not available');

    $started = hrtime(true);
    $calendar = app(CalendarQuery::class)->calendar(new CalendarFilter(
        $path['business']->id, $path['location']->id, 'day', CarbonImmutable::parse('2026-08-19', 'Asia/Kolkata'),
    ));
    $elapsedMs = (hrtime(true) - $started) / 1_000_000;
    expect($calendar['events'])->toHaveCount(2)
        ->and(collect($calendar['events'])->pluck('type')->sort()->values()->all())->toBe(['appointment', 'block'])
        ->and(collect($calendar['events'])->every(fn (array $event) => isset($event['statusLabel'], $event['statusCue'], $event['tone'])))->toBeTrue()
        ->and($calendar['timeZone'])->toBe('Asia/Kolkata')
        ->and($elapsedMs)->toBeLessThan(500)
        ->and(ScheduleBlock::query()->find($block->id)->private_reason)->toContain('meal break');
});

it('operates a walk-in queue and never silently collides with the next future appointment', function () {
    $path = operationalPath();
    $now = CarbonImmutable::parse('2026-08-20 09:00', 'Asia/Kolkata')->utc();
    CarbonImmutable::setTestNow($now);
    operationalBooking($path, '2026-08-20 11:00', 'future-booking', $now->subDay());
    $queue = app(WalkInQueueService::class);
    $first = $queue->add($path['business']->id, $path['location']->id, $path['service']->id, 'Walk In One', '+911111111111', $path['staff']->id, $now, 'Prefers a quiet chair.', 'reception', 'user', $path['user']->id);
    $second = $queue->add($path['business']->id, $path['location']->id, $path['service']->id, 'Walk In Two', '+912222222222', null, $now->addMinute(), null, 'reception', 'user', $path['user']->id);
    expect($first->estimated_wait_minutes)->not->toBeNull()
        ->and($first->estimate_evidence['future_appointments_and_staff_capacity_checked'])->toBeTrue()
        ->and($second->queue_position)->toBe(2);

    $ordered = $queue->reorder($path['business']->id, $path['location']->id, [$second->public_id, $first->public_id], 'Second client has an accessibility need.', 'reception', 'user', $path['user']->id);
    expect($ordered[0]->public_id)->toBe($second->public_id)
        ->and(WalkInHistory::query()->where('walk_in_entry_id', $second->id)->where('action', 'reordered')->value('reason'))->toContain('accessibility');
    $assigned = $queue->assign($first->fresh(), $path['staff']->id, $first->fresh()->version, 'reception', 'user', $path['user']->id);
    $notified = $queue->notify($assigned, $assigned->version, 'reception', 'user', $path['user']->id);
    expect(OperationalNotificationEvent::query()->where('event_type', 'walk_in.turn_approaching')->count())->toBe(1);

    expect(fn () => $queue->startService(
        $notified, CarbonImmutable::parse('2026-08-20 10:30', 'Asia/Kolkata')->utc(), 'walkin-collision', $notified->version,
        $path['staff']->id, 'reception', 'user', $path['user']->id,
    ))->toThrow(BookingRuleViolation::class);
    expect($notified->fresh()->appointment_id)->toBeNull()->and($notified->fresh()->status)->toBe('notified');

    $started = $queue->startService(
        $notified->fresh(), CarbonImmutable::parse('2026-08-20 10:00', 'Asia/Kolkata')->utc(), 'walkin-safe', $notified->fresh()->version,
        $path['staff']->id, 'reception', 'user', $path['user']->id,
    );
    expect($started->status)->toBe('in_service')
        ->and($notified->fresh()->status)->toBe('in_service')
        ->and($notified->fresh()->actual_wait_minutes)->toBe(0)
        ->and($notified->fresh()->history->pluck('action')->all())->toContain('converted', 'service_started');
    CarbonImmutable::setTestNow();
});

it('records late overrun staff-unavailable and unexpected-closure recovery evidence', function () {
    $path = operationalPath();
    $first = operationalBooking($path, '2026-08-21 10:00', 'exception-first');
    operationalBooking($path, '2026-08-21 11:00', 'exception-next');
    $exceptions = app(OperationalExceptionService::class);
    $overrun = $exceptions->recordAppointmentImpact(
        $first, 'service_overrun', 'Colour processing took longer than planned.',
        CarbonImmutable::parse('2026-08-21 11:30', 'Asia/Kolkata')->utc(), 'user', $path['user']->id,
    );
    $closure = $exceptions->unexpectedClosure(
        $path['business']->id, $path['location']->id,
        CarbonImmutable::parse('2026-08-21 09:00', 'Asia/Kolkata')->utc(), CarbonImmutable::parse('2026-08-21 13:00', 'Asia/Kolkata')->utc(),
        'Water supply interruption.', 'user', $path['user']->id,
    );

    expect($overrun->impact['affected_appointments'])->toHaveCount(1)
        ->and($closure->impact['affected_appointments'])->toHaveCount(2)
        ->and($closure->impact['recovery_actions'])->toBe(['contact', 'reschedule', 'cancel'])
        ->and(OperationalException::query()->where('status', 'open')->count())->toBe(2)
        ->and(OperationalNotificationEvent::query()->where('event_type', 'appointment.operational_impact')->count())->toBe(1);
});

it('enforces calendar and queue permissions, assigned locations, and cross-tenant identifiers through HTTP', function () {
    $owner = operationalPath();
    $other = operationalPath();
    app(TenantContext::class)->clear();

    $this->actingAs($owner['user'])->get(route('business.calendar', $owner['business']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->component('Operations/Calendar')->where('permissions.override', true));
    $this->actingAs($owner['user'])->get(route('business.walk-ins.index', $owner['business']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->component('Operations/WalkInQueue')->where('location.public_id', $owner['location']->public_id));
    $this->actingAs($owner['user'])->post(route('business.appointments.store', $owner['business']), [
        'location' => $owner['location']->public_id,
        'starts_at' => '2026-08-17T10:00',
        'source' => 'reception',
        'client_name' => 'Local Time Client',
        'lines' => [[
            'service' => $owner['service']->public_id,
            'staff' => $owner['staff']->public_id,
        ]],
        'idempotency_key' => 'http-local-time-booking',
    ])->assertRedirect();
    expect(Appointment::query()->where('business_id', $owner['business']->id)->firstOrFail()->starts_at_utc->toIso8601String())
        ->toBe('2026-08-17T04:30:00+00:00');
    $this->actingAs($owner['user'])->get(route('business.calendar', $other['business']))->assertForbidden();

    $accountant = operationalPath(StarterRole::Accountant);
    app(TenantContext::class)->clear();
    $this->actingAs($accountant['user'])->get(route('business.calendar', $accountant['business']))->assertForbidden();
    $this->actingAs($accountant['user'])->get(route('business.walk-ins.index', $accountant['business']))->assertForbidden();
});

it('runs an idempotent production-like front-desk day simulation', function () {
    $this->seed(FrontDeskDaySeeder::class);
    $business = Business::query()->where('slug', 'good-hours-demo-tenant')->firstOrFail();
    $location = Location::query()->where('business_id', $business->id)->firstOrFail();
    $date = Appointment::query()->where('business_id', $business->id)->min('starts_at_utc');
    $localDate = CarbonImmutable::parse($date)->setTimezone($location->time_zone)->startOfDay();
    $calendar = app(CalendarQuery::class)->calendar(new CalendarFilter($business->id, $location->id, 'day', $localDate));

    expect($calendar['counts']['appointments'])->toBe(8)
        ->and($calendar['counts']['walkInsWaiting'])->toBe(2)
        ->and($calendar['counts']['blocks'])->toBe(1)
        ->and(collect($calendar['events'])->where('status', 'in_service'))->toHaveCount(1)
        ->and(collect($calendar['events'])->where('status', 'late'))->toHaveCount(1)
        ->and(collect($calendar['events'])->where('status', 'checked_in'))->toHaveCount(1)
        ->and(OperationalNotificationEvent::query()->count())->toBeGreaterThanOrEqual(4);

    $this->seed(FrontDeskDaySeeder::class);
    expect(Appointment::query()->where('business_id', $business->id)->count())->toBe(8)
        ->and(WalkInEntry::query()->where('business_id', $business->id)->count())->toBe(2)
        ->and(ScheduleBlock::query()->where('business_id', $business->id)->count())->toBe(1);
});

it('returns booking-policy feedback instead of an exception page for a staff booking', function () {
    $path = operationalPath(noticeMinutes: 120);
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-25 09:00', 'Asia/Kolkata')->utc());
    app(TenantContext::class)->clear();

    $this->actingAs($path['user'])->from(route('business.calendar', $path['business']))
        ->post(route('business.appointments.store', $path['business']), [
            'location' => $path['location']->public_id,
            'starts_at' => '2026-08-25T09:30',
            'source' => 'reception',
            'client_name' => 'Notice feedback',
            'lines' => [['service' => $path['service']->public_id, 'staff' => $path['staff']->public_id]],
            'idempotency_key' => 'notice-feedback',
        ])->assertRedirect(route('business.calendar', $path['business']))
        ->assertSessionHasErrors('booking');

    CarbonImmutable::setTestNow();
});
