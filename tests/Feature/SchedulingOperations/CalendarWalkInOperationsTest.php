<?php

use App\Domain\BusinessConfiguration\Models\LocationHour;
use App\Domain\BusinessConfiguration\Models\Service;
use App\Domain\BusinessConfiguration\Models\StaffAvailabilityRule;
use App\Domain\BusinessConfiguration\Models\StaffServiceAssignment;
use App\Domain\ClientRecords\Models\Client;
use App\Domain\MoneyCommerce\Models\Sale;
use App\Domain\PlatformAccess\Enums\PermissionName;
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
use App\Domain\SchedulingOperations\Services\CalendarWorkspaceQuery;
use App\Domain\SchedulingOperations\Services\OperationalExceptionService;
use App\Domain\SchedulingOperations\Services\ScheduleBlockService;
use App\Domain\SchedulingOperations\Services\WalkInQueueService;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Database\Seeders\FrontDeskDaySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('preserves unreadable contact and notes during a schedule change and denies unassigned print access', function () {
    CarbonImmutable::setTestNow('2035-10-09 03:00 UTC');
    try {
        $p = operationalPath(StarterRole::Receptionist);
        $a = operationalBooking($p, '2035-10-10 10:00', 'privacy-calendar-booking');
        $a->update(['client_email' => 'private@example.test', 'internal_notes' => 'Private appointment note']);
        app(TenantContext::class)->run($p['business'], $p['membership'], function () use ($p) {
            $p['membership']->syncRoles([]);
            $p['membership']->syncPermissions([PermissionName::CalendarViewAll->value, PermissionName::AppointmentsManageAll->value]);
        });
        $this->actingAs($p['user'])->post(route('business.appointments.replace', [$p['business'], $a]), [
            'kind' => 'reschedule', 'location' => $p['location']->public_id, 'starts_at' => '2035-10-10T12:00',
            'lines' => [['service' => $p['service']->public_id, 'staff' => $p['staff']->public_id]], 'version' => 1,
            'reason' => 'Client requested another time', 'confirmed' => true, 'idempotency_key' => 'privacy-preserve-replacement',
            'client_name' => $a->client_name, 'client_mobile' => null, 'client_email' => null, 'internal_notes' => null,
        ])->assertSessionHasNoErrors()->assertRedirect();
        $replacement = Appointment::query()->findOrFail($a->fresh()->rescheduled_to_appointment_id);
        expect($replacement->client_mobile)->toBe('+919999999999')->and($replacement->client_email)->toBe('private@example.test')->and($replacement->internal_notes)->toBe('Private appointment note');
        $this->patchJson(route('business.appointments.notes', [$p['business'], $replacement]), ['notes' => 'Not allowed', 'version' => 1, 'idempotency_key' => 'denied-note'])->assertForbidden();
        $other = Location::factory()->create(['business_id' => $p['business']->id]);
        $this->get(route('business.calendar.print', ['business' => $p['business'], 'location' => $other->public_id]))->assertForbidden();
    } finally {
        CarbonImmutable::setTestNow();
    }
});

/** @return array{business:Business,location:Location,staff:StaffProfile,service:Service,user:User,membership:Membership} */
function operationalPath(StarterRole $role = StarterRole::Owner, int $noticeMinutes = 0): array
{
    $business = Business::factory()->create(['appointment_interval_minutes' => 15, 'time_zone' => 'Asia/Kolkata', 'currency_code' => 'INR']);
    activateTestSubscription($business);
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
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-16 09:00', 'Asia/Kolkata')->utc());
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
    CarbonImmutable::setTestNow();
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

it('cancels from the calendar, releases the default view, and keeps the record discoverable by status', function () {
    $path = operationalPath();
    $appointment = operationalBooking($path, '2035-10-10 10:00', 'calendar-cancel-http');
    app(TenantContext::class)->clear();

    $this->actingAs($path['user'])->patch(route('business.appointments.status', [$path['business'], $appointment]), [
        'status' => 'cancelled_by_shop', 'version' => 1, 'reason' => 'Client requested cancellation by phone.',
        'confirmed' => true, 'idempotency_key' => 'calendar-cancel-http-command',
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($appointment->fresh()->status)->toBe('cancelled_by_shop');
    $default = app(CalendarQuery::class)->calendar(new CalendarFilter($path['business']->id, $path['location']->id, 'day', CarbonImmutable::parse('2035-10-10', 'Asia/Kolkata')));
    $cancelled = app(CalendarQuery::class)->calendar(new CalendarFilter($path['business']->id, $path['location']->id, 'day', CarbonImmutable::parse('2035-10-10', 'Asia/Kolkata'), [], [], ['cancelled_by_shop']));
    expect($default['events'])->toBeEmpty()->and($cancelled['events'])->toHaveCount(1);

    $clientCancelled = operationalBooking($path, '2035-10-11 10:00', 'calendar-client-cancel-http');
    $this->actingAs($path['user'])->patch(route('business.appointments.status', [$path['business'], $clientCancelled]), [
        'status' => 'cancelled_by_shop', 'version' => 1, 'reason_code' => 'client_requested',
        'confirmed' => true, 'idempotency_key' => 'calendar-client-cancel-http-command',
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($clientCancelled->fresh()->status)->toBe('cancelled_by_client')
        ->and($clientCancelled->changes()->latest('id')->value('reason'))->toBe('Client requested cancellation.');
});

it('creates and links a client as soon as a walk-in joins the queue', function () {
    $path = operationalPath();
    app(TenantContext::class)->clear();

    $this->actingAs($path['user'])->post(route('business.walk-ins.store', $path['business']), [
        'location' => $path['location']->public_id, 'service' => $path['service']->public_id,
        'preferred_staff' => $path['staff']->public_id, 'client_mode' => 'new',
        'client_name' => 'Queue Client', 'client_mobile' => '+919123456789', 'client_email' => 'queue@example.test',
        'arrived_at' => '2035-10-10T10:00', 'notes' => 'First visit.',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $client = Client::query()->where('business_id', $path['business']->id)->where('normalized_email', 'queue@example.test')->firstOrFail();
    $entry = WalkInEntry::query()->where('business_id', $path['business']->id)->firstOrFail();
    expect($entry->client_id)->toBe($client->id)->and($entry->client_email)->toBe('queue@example.test')->and($entry->appointment_id)->toBeNull();

    $this->actingAs($path['user'])->getJson(route('business.walk-ins.clients.search', [$path['business'], 'q' => 'Queue']))
        ->assertOk()->assertJsonPath('clients.0.public_id', $client->public_id);
});

it('records a full manual payment once and removes the settled visit from checkout', function () {
    $path = operationalPath();
    $appointment = operationalBooking($path, '2035-10-10 10:00', 'manual-checkout-booking');
    $appointment->forceFill(['status' => 'completed', 'completed_at' => now()])->save();
    app(TenantContext::class)->clear();

    $sale = $this->actingAs($path['user'])->postJson(route('business.checkout.open', [$path['business'], $appointment]), ['lines' => []])
        ->assertOk()->json('sale');
    $this->actingAs($path['user'])->postJson(route('business.checkout.tender', [$path['business'], $sale['public_id']]), [
        'method' => 'cash', 'amount_minor' => $sale['balance_minor'], 'idempotency_key' => 'manual-payment-http',
        'evidence' => ['manually_confirmed' => true],
    ])->assertOk()->assertJsonPath('sale.status', 'completed')->assertJsonPath('sale.balance_minor', 0);

    expect(Sale::query()->where('business_id', $path['business']->id)->count())->toBe(1)
        ->and(OperationalNotificationEvent::query()->where('event_type', 'payment.receipt')->where('subject_id', $appointment->id)->count())->toBe(1);
    $this->actingAs($path['user'])->get(route('business.checkout.index', [$path['business'], 'appointment' => $appointment->public_id]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('appointments', []));
});

it('projects real schedule context even when appointment filters hide reserved capacity', function () {
    $path = operationalPath();
    $a = operationalBooking($path, '2035-10-10 10:00', 'workspace-filtered');
    StaffAvailabilityRule::query()->create([
        'business_id' => $path['business']->id, 'staff_profile_id' => $path['staff']->id,
        'location_id' => $path['location']->id, 'kind' => 'break', 'day_of_week' => 3,
        'starts_at' => '12:00', 'ends_at' => '12:30', 'reason' => 'Private staff information',
    ]);
    app(ScheduleBlockService::class)->create($path['business']->id, $path['location']->id, $path['staff']->id, 'personal_block', 'Meeting',
        CarbonImmutable::parse('2035-10-10 14:00', 'Asia/Kolkata')->utc(), CarbonImmutable::parse('2035-10-10 14:30', 'Asia/Kolkata')->utc(),
        'Private reason', 'user', $path['user']->id);
    $this->actingAs($path['user'])->get(route('business.calendar', ['business' => $path['business'], 'date' => '2035-10-10', 'status' => ['completed']]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('filters.view', 'staff')->where('calendar.counts.appointments', 0)
        ->has('schedule.days', 1)->has('schedule.days.0.staff', 1)
        ->has('schedule.days.0.staff.0.windows', 2)
        ->where('schedule.days.0.staff.0.busy.0.appointmentId', $a->public_id)
        ->where('schedule.days.0.staff.0.unavailable.0.label', 'Break')
        ->missing('schedule.days.0.staff.0.unavailable.0.reason'));
});

it('limits calendar own staff options and hides protected payment and client fields', function () {
    $path = operationalPath(StarterRole::BarberStylist);
    $a = operationalBooking($path, '2035-10-10 10:00', 'workspace-own');
    app(TenantContext::class)->run($path['business'], $path['membership'], function () use ($path) {
        $path['membership']->syncRoles([]);
        $path['membership']->syncPermissions([PermissionName::CalendarViewOwn->value, PermissionName::AppointmentsManageOwn->value]);
    });
    $peer = StaffProfile::factory()->create(['business_id' => $path['business']->id, 'display_name' => 'Private peer']);
    $peer->locations()->syncWithPivotValues([$path['location']->id], ['business_id' => $path['business']->id]);
    $this->actingAs($path['user'])->get(route('business.calendar', ['business' => $path['business'], 'date' => '2035-10-10']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('options.staff', 1)->where('options.staff.0.public_id', $path['staff']->public_id)
        ->has('schedule.days.0.staff', 1)->where('permissions.checkout', false)
        ->where('calendar.events.0.canManage', true)->where('calendar.events.0.clientMobile', null)
        ->missing('calendar.events.0.checkoutReady')->missing('calendar.events.0.paymentLabel'));
    $this->actingAs($path['user'])->get(route('business.calendar.clients', ['business' => $path['business'], 'search' => 'Jordan']))->assertForbidden();
});

it('selects an existing client explicitly and preserves the canonical identity through replay and reschedule', function () {
    $path = operationalPath();
    $client = Client::query()->create([
        'business_id' => $path['business']->id, 'name' => 'Alex Example', 'normalized_name' => 'alexexample', 'status' => 'active',
    ]);
    $data = ['client' => $client->public_id, 'location' => $path['location']->public_id, 'starts_at' => '2035-10-10T10:00', 'source' => 'reception',
        'client_name' => 'Ignored tampering', 'lines' => [['service' => $path['service']->public_id, 'staff' => $path['staff']->public_id]], 'idempotency_key' => 'selected-client'];
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2035-10-09', 'Asia/Kolkata'));
    try {
        $this->actingAs($path['user'])->post(route('business.appointments.store', $path['business']), $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('business.appointments.store', $path['business']), $data)->assertRedirect()->assertSessionHasNoErrors();
        $a = Appointment::query()->where('idempotency_key', 'selected-client')->firstOrFail();
        expect($a->client_id)->toBe($client->id)->and($a->client_name)->toBe('Alex Example')->and(Appointment::query()->count())->toBe(1)
            ->and(Client::query()->count())->toBe(1);
        $client->update(['status' => 'inactive']);
        $this->post(route('business.appointments.replace', [$path['business'], $a]), [
            'kind' => 'reschedule', 'location' => $path['location']->public_id, 'starts_at' => '2035-10-10T11:00',
            'source' => 'reception', 'client_name' => $a->client_name, 'lines' => $data['lines'], 'version' => 1,
            'reason' => 'Client requested a later time.', 'confirmed' => true, 'idempotency_key' => 'selected-client-replace',
        ])->assertRedirect()->assertSessionHasNoErrors();
        expect(Appointment::query()->where('status', 'confirmed')->firstOrFail()->client_id)->toBe($client->id);
        $other = operationalPath();
        $this->post(route('business.appointments.store', $other['business']), $data)->assertForbidden();
    } finally {
        CarbonImmutable::setTestNow();
    }
});

it('uses the selected clients saved formatted contacts before validating stale form fields', function () {
    $path = operationalPath();
    $client = Client::query()->create([
        'business_id' => $path['business']->id, 'name' => 'Anika Rao', 'normalized_name' => 'anikarao', 'status' => 'active',
        'mobile' => '+91 (90000) 01001', 'email' => 'anika@example.test',
    ]);
    $data = ['client' => $client->public_id, 'location' => $path['location']->public_id, 'starts_at' => '2035-10-10T10:00', 'source' => 'reception',
        'client_name' => ['invalid stale input'], 'client_mobile' => 'not a phone', 'client_email' => 'not an email',
        'lines' => [['service' => $path['service']->public_id, 'staff' => $path['staff']->public_id]], 'idempotency_key' => 'saved-client-phone'];
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2035-10-09', 'Asia/Kolkata'));
    try {
        $this->actingAs($path['user'])->post(route('business.appointments.store', $path['business']), $data)->assertRedirect()->assertSessionHasNoErrors();
        $data['client_mobile'] = '+919123456789';
        $this->post(route('business.appointments.store', $path['business']), $data)->assertRedirect()->assertSessionHasNoErrors();
        $a = Appointment::query()->where('idempotency_key', 'saved-client-phone')->firstOrFail();
        expect($a->client_id)->toBe($client->id)->and($a->client_name)->toBe('Anika Rao')
            ->and($a->client_mobile)->toBe('+919000001001')->and($a->client_email)->toBe('anika@example.test')
            ->and($client->fresh()->mobile)->toBe('+91 (90000) 01001')
            ->and(Appointment::query()->count())->toBe(1)->and(Client::query()->count())->toBe(1);
    } finally {
        CarbonImmutable::setTestNow();
    }
});

it('allows an optional appointment phone for selected clients with missing or invalid saved numbers', function (?string $savedMobile) {
    $path = operationalPath();
    $client = Client::query()->create([
        'business_id' => $path['business']->id, 'name' => 'Alex Example', 'normalized_name' => 'alexexample', 'status' => 'active', 'mobile' => $savedMobile,
    ]);
    $data = ['client' => $client->public_id, 'location' => $path['location']->public_id, 'starts_at' => '2035-10-10T10:00', 'source' => 'reception',
        'client_mobile' => 'not a phone', 'lines' => [['service' => $path['service']->public_id, 'staff' => $path['staff']->public_id]], 'idempotency_key' => 'client-phone-correction'];
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2035-10-09', 'Asia/Kolkata'));
    try {
        $this->actingAs($path['user'])->post(route('business.appointments.store', $path['business']), $data)->assertSessionHasErrors('client_mobile');
        expect(Appointment::query()->count())->toBe(0);
        $data['client_mobile'] = '+91 91234 56789';
        $this->post(route('business.appointments.store', $path['business']), $data)->assertRedirect()->assertSessionHasNoErrors();
        $a = Appointment::query()->where('idempotency_key', 'client-phone-correction')->firstOrFail();
        expect($a->client_id)->toBe($client->id)->and($a->client_mobile)->toBe('+919123456789')
            ->and($client->fresh()->mobile)->toBe($savedMobile)->and(Client::query()->count())->toBe(1);
    } finally {
        CarbonImmutable::setTestNow();
    }
})->with(['missing' => [null], 'invalid' => ['invalid saved phone']]);

it('reuses private saved client contacts without letting a restricted operator replace them', function () {
    $path = operationalPath();
    $client = Client::query()->create([
        'business_id' => $path['business']->id, 'name' => 'Private Client', 'normalized_name' => 'privateclient', 'status' => 'active',
        'mobile' => '+91 90000 01001', 'email' => 'private@example.test',
    ]);
    app(TenantContext::class)->run($path['business'], $path['membership'], function () use ($path) {
        $path['membership']->syncRoles([]);
        $path['membership']->syncPermissions([PermissionName::CalendarViewAll->value, PermissionName::AppointmentsManageAll->value, PermissionName::ClientView->value]);
    });
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2035-10-09', 'Asia/Kolkata'));
    try {
        $this->actingAs($path['user'])->post(route('business.appointments.store', $path['business']), [
            'client' => $client->public_id, 'location' => $path['location']->public_id, 'starts_at' => 'not a date', 'source' => 'reception',
            'client_mobile' => null, 'client_email' => null,
            'lines' => [['service' => $path['service']->public_id, 'staff' => $path['staff']->public_id]], 'idempotency_key' => 'private-contact-validation',
        ])->assertSessionHasErrors('starts_at');
        expect(session()->getOldInput('client_mobile'))->toBeNull()->and(session()->getOldInput('client_email'))->toBeNull();
        $this->actingAs($path['user'])->post(route('business.appointments.store', $path['business']), [
            'client' => $client->public_id, 'location' => $path['location']->public_id, 'starts_at' => '2035-10-10T10:00', 'source' => 'reception',
            'client_mobile' => 'invalid hidden input', 'client_email' => 'invalid hidden input',
            'lines' => [['service' => $path['service']->public_id, 'staff' => $path['staff']->public_id]], 'idempotency_key' => 'private-saved-contact',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $a = Appointment::query()->where('idempotency_key', 'private-saved-contact')->firstOrFail();
        expect($a->client_id)->toBe($client->id)->and($a->client_mobile)->toBe('+919000001001')->and($a->client_email)->toBe('private@example.test');
        $this->getJson(route('business.calendar.clients', ['business' => $path['business'], 'search' => 'Private']))
            ->assertOk()->assertJsonPath('clients.0.mobile', null)->assertJsonPath('clients.0.email', null);
        $client->update(['mobile' => null]);
        $this->post(route('business.appointments.store', $path['business']), [
            'client' => $client->public_id, 'location' => $path['location']->public_id, 'starts_at' => '2035-10-10T12:00', 'source' => 'reception',
            'client_mobile' => '+919123456789', 'lines' => [['service' => $path['service']->public_id, 'staff' => $path['staff']->public_id]], 'idempotency_key' => 'private-missing-contact',
        ])->assertRedirect()->assertSessionHasNoErrors();
        expect(Appointment::query()->where('idempotency_key', 'private-missing-contact')->firstOrFail()->client_mobile)->toBeNull();
    } finally {
        CarbonImmutable::setTestNow();
    }
});

it('still rejects invalid phone input when booking a new client', function () {
    $path = operationalPath();
    $this->actingAs($path['user'])->post(route('business.appointments.store', $path['business']), [
        'location' => $path['location']->public_id, 'starts_at' => '2035-10-10T10:00', 'source' => 'reception',
        'client_name' => 'New Client', 'client_mobile' => '9000001001',
        'lines' => [['service' => $path['service']->public_id, 'staff' => $path['staff']->public_id]], 'idempotency_key' => 'invalid-new-client-phone',
    ])->assertSessionHasErrors('client_mobile');
    expect(Appointment::query()->count())->toBe(0)->and(Client::query()->count())->toBe(0);
});

it('projects overnight hours and full day leave without leaking leave reasons', function () {
    $path = operationalPath();
    $path['location']->hours()->delete();
    $path['staff']->availabilityRules()->delete();
    LocationHour::query()->create(['business_id' => $path['business']->id, 'location_id' => $path['location']->id, 'day_of_week' => 2, 'opens_at' => '22:00', 'closes_at' => '02:00', 'sequence' => 1]);
    StaffAvailabilityRule::query()->create(['business_id' => $path['business']->id, 'staff_profile_id' => $path['staff']->id, 'location_id' => $path['location']->id, 'kind' => 'working', 'day_of_week' => 2, 'starts_at' => '22:00', 'ends_at' => '02:00']);
    $query = app(CalendarWorkspaceQuery::class);
    $result = $query->build($path['location'], collect([$path['staff']]), CarbonImmutable::parse('2035-10-10', 'Asia/Kolkata'), 1);
    expect($result['days'][0]['windows'][0]['startsAt'])->toBe('2035-10-10T00:00:00+05:30')
        ->and($result['days'][0]['staff'][0]['windows'][0]['endsAt'])->toBe('2035-10-10T02:00:00+05:30');
    StaffAvailabilityRule::query()->create(['business_id' => $path['business']->id, 'staff_profile_id' => $path['staff']->id, 'kind' => 'leave', 'starts_on' => '2035-10-10', 'ends_on' => '2035-10-10', 'reason' => 'Private medical reason']);
    $path['staff']->unsetRelation('availabilityRules');
    $result = $query->build($path['location'], collect([$path['staff']]), CarbonImmutable::parse('2035-10-10', 'Asia/Kolkata'), 1);
    expect($result['days'][0]['staff'][0]['windows'])->toBe([])
        ->and($result['days'][0]['staff'][0]['unavailable'][0]['label'])->toBe('Time off')
        ->and(json_encode($result))->not->toContain('Private medical reason');
    $request = new BookingRequest($path['business']->id, $path['location']->id, CarbonImmutable::parse('2035-10-10 00:30', 'Asia/Kolkata')->utc(), [new BookingLineRequest($path['service']->id, $path['staff']->id)], asOfUtc: CarbonImmutable::parse('2035-10-08', 'Asia/Kolkata')->utc());
    expect(fn () => app(BookingCommitCommand::class)->commit($request, 'overnight-leave-denial'))->toThrow(BookingRuleViolation::class);
});

it('scopes existing client search and redacts contact fields before serialization', function () {
    $path = operationalPath();
    $a = operationalBooking($path, '2035-10-10 10:00', 'client-search-visit');
    $client = $a->client;
    $client->update(['name' => 'Jordan Example', 'normalized_name' => 'jordanexample', 'email' => 'jordan@example.test', 'normalized_email' => 'jordan@example.test']);
    $otherBusiness = Business::factory()->create();
    Client::query()->create(['business_id' => $otherBusiness->id, 'name' => 'Jordan Private', 'normalized_name' => 'jordanprivate', 'status' => 'active']);
    $this->actingAs($path['user'])->getJson(route('business.calendar.clients', ['business' => $path['business'], 'search' => 'jordan@example']))
        ->assertOk()->assertJsonCount(1, 'clients')->assertJsonPath('clients.0.id', $client->public_id);
    app(TenantContext::class)->run($path['business'], $path['membership'], function () use ($path) {
        $path['membership']->syncRoles([]);
        $path['membership']->syncPermissions([PermissionName::CalendarViewOwn->value, PermissionName::AppointmentsManageOwn->value, PermissionName::ClientView->value]);
    });
    $this->getJson(route('business.calendar.clients', ['business' => $path['business'], 'search' => 'Jordan']))
        ->assertOk()->assertJsonCount(1, 'clients')->assertJsonPath('clients.0.mobile', null)->assertJsonPath('clients.0.email', null);
    $this->getJson(route('business.calendar.clients', ['business' => $path['business'], 'search' => '+919999999999']))->assertOk()->assertJsonCount(0, 'clients');
});

it('keeps existing visits visible when their staff member becomes inactive', function () {
    $path = operationalPath();
    $appointment = operationalBooking($path, '2035-10-10 10:00', 'inactive-calendar-staff');
    $path['staff']->update(['status' => 'inactive']);
    $this->actingAs($path['user'])->get(route('business.calendar', ['business' => $path['business'], 'date' => '2035-10-10']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('calendar.events.0.id', $appointment->public_id)->has('options.bookableStaff', 0)
        ->where('schedule.days.0.staff.0.id', $path['staff']->public_id)
        ->where('schedule.days.0.staff.0.status', 'inactive')->has('schedule.days.0.staff.0.windows', 0));
});

it('forecasts parallel staff capacity in queue order and protects future bookings and breaks', function () {
    $p = operationalPath();
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2035-10-10 09:00', 'Asia/Kolkata'));
    try {
        $peer = StaffProfile::factory()->create(['business_id' => $p['business']->id, 'display_name' => 'Blair']);
        $peer->locations()->attach($p['location']->id, ['business_id' => $p['business']->id]);
        StaffAvailabilityRule::query()->create(['business_id' => $p['business']->id, 'staff_profile_id' => $peer->id, 'location_id' => $p['location']->id, 'kind' => 'working', 'day_of_week' => 3, 'starts_at' => '09:00', 'ends_at' => '18:00']);
        StaffServiceAssignment::query()->create(['business_id' => $p['business']->id, 'staff_profile_id' => $peer->id, 'service_id' => $p['service']->id, 'is_qualified' => true, 'is_active' => true, 'duration_minutes' => 30]);
        operationalBooking($p, '2035-10-10 09:30', 'queue-next-booking');
        $q = app(WalkInQueueService::class);
        $first = $q->add($p['business']->id, $p['location']->id, $p['service']->id, 'First Client', '+919111111111', $p['staff']->id, CarbonImmutable::now()->utc(), null, 'reception', 'user', $p['user']->id);
        $second = $q->add($p['business']->id, $p['location']->id, $p['service']->id, 'Second Client', '+919222222222', null, CarbonImmutable::now()->utc(), null, 'reception', 'user', $p['user']->id);
        $this->actingAs($p['user'])->get(route('business.walk-ins.index', $p['business']))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('entries.0.public_id', $first->public_id)->where('entries.0.estimated_at', '2035-10-10T05:00:00+00:00')
            ->where('entries.1.public_id', $second->public_id)->where('entries.1.estimated_at', '2035-10-10T03:30:00+00:00')
            ->where('entries.1.suggested_staff_id', $peer->public_id)->where('staff.0.gap_minutes', 30));
        $this->getJson(route('business.walk-ins.readiness', [$p['business'], $first->public_id, 'staff' => $p['staff']->public_id]))
            ->assertOk()->assertJsonPath('ready', false)->assertJsonPath('code', 'STAFF_UNAVAILABLE');
        $this->getJson(route('business.walk-ins.readiness', [$p['business'], $second->public_id, 'staff' => $peer->public_id]))->assertOk()->assertJsonPath('ready', true);
        StaffAvailabilityRule::query()->create(['business_id' => $p['business']->id, 'staff_profile_id' => $peer->id, 'kind' => 'break', 'day_of_week' => 3, 'starts_at' => '09:00', 'ends_at' => '09:30', 'reason' => 'Private reason']);
        $this->getJson(route('business.walk-ins.readiness', [$p['business'], $second->public_id, 'staff' => $peer->public_id]))->assertOk()->assertJsonPath('ready', false)->assertDontSee('Private reason');
    } finally {
        CarbonImmutable::setTestNow();
    }
});

it('keeps walk-in identity start replay and completion synchronized with the canonical appointment', function () {
    $p = operationalPath();
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2035-10-10 09:00', 'Asia/Kolkata'));
    try {
        $client = Client::query()->create(['business_id' => $p['business']->id, 'name' => 'Canonical Client', 'normalized_name' => 'canonicalclient', 'mobile' => '+919111111111', 'normalized_mobile' => '+919111111111', 'status' => 'active']);
        $q = app(WalkInQueueService::class);
        $e = $q->add($p['business']->id, $p['location']->id, $p['service']->id, $client->name, $client->mobile, null, CarbonImmutable::now()->utc(), null, 'reception', 'user', $p['user']->id, $client->id);
        $a = $q->startService($e, CarbonImmutable::now()->utc(), 'atomic-queue-start', 1, $p['staff']->id, 'reception', 'user', $p['user']->id);
        $replay = $q->startService($e, CarbonImmutable::now()->utc(), 'atomic-queue-start', 1, $p['staff']->id, 'reception', 'user', $p['user']->id);
        expect($a->client_id)->toBe($client->id)->and($replay->id)->toBe($a->id)->and(Appointment::query()->count())->toBe(1)->and(Client::query()->count())->toBe(1);
        expect(fn () => $q->startService($e, CarbonImmutable::now()->utc()->addMinutes(15), 'atomic-queue-start', 1, $p['staff']->id, 'reception', 'user', $p['user']->id))->toThrow(BookingRuleViolation::class);
        $this->actingAs($p['user'])->patch(route('business.appointments.status', [$p['business'], $a]), ['status' => 'completed', 'version' => $a->version, 'idempotency_key' => 'queue-complete'])->assertRedirect()->assertSessionHasNoErrors();
        $this->patch(route('business.appointments.status', [$p['business'], $a]), ['status' => 'completed', 'version' => $a->version, 'idempotency_key' => 'queue-complete'])->assertRedirect()->assertSessionHasNoErrors();
        expect($e->fresh()->status)->toBe('completed')->and($e->history()->where('action', 'completed')->count())->toBe(1);
        $this->get(route('business.walk-ins.index', $p['business']))->assertOk()->assertInertia(fn (Assert $page) => $page->has('entries', 0)->where('recent.0.public_id', $e->public_id));
    } finally {
        CarbonImmutable::setTestNow();
    }
});

it('redacts queue contacts and notes and denies branch and tenant overrides', function () {
    $p = operationalPath(StarterRole::Receptionist);
    $q = app(WalkInQueueService::class);
    $client = Client::query()->create(['business_id' => $p['business']->id, 'name' => 'Private Client', 'normalized_name' => 'privateclient', 'status' => 'active', 'mobile' => '+919111111111', 'normalized_mobile' => '+919111111111', 'email' => 'private@example.test', 'normalized_email' => 'private@example.test']);
    $e = $q->add($p['business']->id, $p['location']->id, $p['service']->id, $client->name, $client->mobile, null, CarbonImmutable::now()->utc(), 'Private visit note', 'reception', 'user', $p['user']->id, $client->id);
    app(TenantContext::class)->run($p['business'], $p['membership'], function () use ($p) {
        $p['membership']->syncRoles([]);
        $p['membership']->syncPermissions([PermissionName::WalkInsManage->value, PermissionName::ScheduleOverride->value]);
    });
    $this->actingAs($p['user'])->get(route('business.walk-ins.index', $p['business']))->assertOk()->assertInertia(fn (Assert $page) => $page->where('entries.0.client_mobile', null)->where('entries.0.notes', null)->where('entries.0.client_public_id', null)->where('permissions.checkout', false));
    $this->getJson(route('business.walk-ins.clients.search', [$p['business'], 'q' => 'Private']))->assertOk()->assertJsonPath('clients.0.mobile', null)->assertJsonPath('clients.0.email', null);
    $this->getJson(route('business.walk-ins.clients.search', [$p['business'], 'q' => 'private@example.test']))->assertOk()->assertJsonCount(0, 'clients');
    $other = Location::factory()->create(['business_id' => $p['business']->id]);
    $this->post(route('business.walk-ins.reorder', $p['business']), ['location' => $other->public_id, 'entries' => [$e->public_id], 'reason' => 'Unauthorized change', 'confirmed' => true])->assertForbidden();
    $otherTenant = operationalPath();
    $this->getJson(route('business.walk-ins.readiness', [$p['business'], $e->public_id, 'staff' => $otherTenant['staff']->public_id]))->assertNotFound();
    $this->get(route('business.walk-ins.index', ['business' => $p['business'], 'location' => $other->public_id]))->assertNotFound();
});

it('does not invent a finish estimate or start another walk-in for an overdue live service', function () {
    $p = operationalPath();
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2035-10-10 11:00', 'Asia/Kolkata'));
    try {
        $a = operationalBooking($p, '2035-10-09 09:00', 'overdue-live', CarbonImmutable::parse('2035-10-08 03:00 UTC'));
        $a->update(['status' => 'in_service']);
        $q = app(WalkInQueueService::class);
        $e = $q->add($p['business']->id, $p['location']->id, $p['service']->id, 'Waiting Client', '+919111111111', $p['staff']->id, CarbonImmutable::now()->utc(), null, 'reception', 'user', $p['user']->id);
        $this->actingAs($p['user'])->get(route('business.walk-ins.index', $p['business']))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('staff.0.state', 'Service running over')->where('staff.0.available_now', false)->where('entries.0.estimated_at', null));
        $this->actingAs($p['user'])->getJson(route('business.walk-ins.readiness', [$p['business'], $e->public_id, 'staff' => $p['staff']->public_id]))->assertOk()->assertJsonPath('ready', false)->assertJsonPath('code', 'SERVICE_RUNNING_OVER');
        expect(fn () => $q->startService($e, CarbonImmutable::now()->utc(), 'overdue-start-denied', 1, $p['staff']->id, 'reception', 'user', $p['user']->id))->toThrow(BookingRuleViolation::class);
        $e->update(['preferred_staff_profile_id' => null]);
        expect(fn () => $q->startService($e, CarbonImmutable::now()->utc(), 'overdue-auto-denied', 1, null, 'reception', 'user', $p['user']->id))->toThrow(BookingRuleViolation::class);
        expect($e->fresh()->appointment_id)->toBeNull()->and(Appointment::query()->count())->toBe(1);
    } finally {
        CarbonImmutable::setTestNow();
    }
});

it('keeps board query growth bounded as the waiting queue grows', function () {
    $p = operationalPath();
    $make = fn ($position) => WalkInEntry::query()->create(['business_id' => $p['business']->id, 'location_id' => $p['location']->id, 'service_id' => $p['service']->id, 'client_name' => 'Synthetic client '.$position, 'client_mobile' => '+919000000200', 'status' => 'waiting', 'queue_position' => $position, 'arrived_at' => now()]);
    $make(1);
    $this->actingAs($p['user'])->get(route('business.walk-ins.index', $p['business']))->assertOk();
    DB::enableQueryLog();
    DB::flushQueryLog();
    $this->get(route('business.walk-ins.index', $p['business']))->assertOk();
    $single = count(DB::getQueryLog());
    foreach (range(2, 40) as $position) {
        $make($position);
    }
    DB::flushQueryLog();
    $this->get(route('business.walk-ins.index', $p['business']))->assertOk()->assertInertia(fn (Assert $page) => $page->has('entries', 40));
    $busy = count(DB::getQueryLog());
    DB::disableQueryLog();
    expect($busy)->toBeLessThanOrEqual($single + 3);
});

it('replays a rapid check-in without duplicating the client or queue entry', function () {
    $p = operationalPath();
    $data = ['location' => $p['location']->public_id, 'service' => $p['service']->public_id, 'client_mode' => 'new',
        'client_name' => 'Replay Client', 'client_mobile' => '+919111111111', 'arrived_at' => '2035-10-10T10:00', 'idempotency_key' => 'rapid-check-in'];
    $this->actingAs($p['user'])->post(route('business.walk-ins.store', $p['business']), $data)->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('status', 'Walk-in added to the queue.');
    $this->post(route('business.walk-ins.store', $p['business']), $data)->assertRedirect()->assertSessionHasNoErrors();
    expect(WalkInEntry::query()->count())->toBe(1)->and(Client::query()->count())->toBe(1)
        ->and(WalkInHistory::query()->where('action', 'created')->count())->toBe(1);
});

it('keeps a converted queue visit linked through Calendar replacement and lifecycle actions', function () {
    $p = operationalPath();
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2035-10-10 09:00', 'Asia/Kolkata'));
    try {
        $q = app(WalkInQueueService::class);
        $e = $q->add($p['business']->id, $p['location']->id, $p['service']->id, 'Converted Client', '+919111111111', null, CarbonImmutable::now()->utc(), null, 'reception', 'user', $p['user']->id);
        $a = $q->convertToAppointment($e, CarbonImmutable::parse('2035-10-10 10:00', 'Asia/Kolkata')->utc(), 'convert-for-calendar', 1, $p['staff']->id, 'reception', 'user', $p['user']->id);
        expect(fn () => $q->assign($e->fresh(), $p['staff']->id, $e->fresh()->version, 'reception', 'user', $p['user']->id))->toThrow(BookingRuleViolation::class);
        expect(fn () => $q->markLeft($e->fresh(), $e->fresh()->version, 'Leaving', 'reception', 'user', $p['user']->id))->toThrow(BookingRuleViolation::class);
        $request = new BookingRequest($p['business']->id, $p['location']->id, CarbonImmutable::parse('2035-10-10 11:00', 'Asia/Kolkata')->utc(), [new BookingLineRequest($p['service']->id, $p['staff']->id)], 'reception', 'existing', CarbonImmutable::now()->utc(), clientName: 'Converted Client', clientMobile: '+919111111111');
        $life = app(AppointmentLifecycleCommand::class);
        $replacement = $life->replace($a, $request, 'reschedule', 'converted-change', $a->version, 'Client requested a later time.');
        expect($e->fresh()->appointment_id)->toBe($replacement->id)->and($e->history()->where('action', 'calendar_updated')->count())->toBe(1);
        $arrived = $life->transition($replacement, 'arrived', 'converted-arrived', $replacement->version, 'reception', 'user', $p['user']->id);
        $started = $life->transition($arrived, 'in_service', 'converted-start', $arrived->version, 'reception', 'user', $p['user']->id);
        expect($e->fresh()->status)->toBe('in_service')->and($e->fresh()->service_started_at)->not->toBeNull()->and($e->history()->where('action', 'service_started')->count())->toBe(1);
        $life->transition($started, 'completed', 'converted-complete', $started->version, 'reception', 'user', $p['user']->id);
        expect($e->fresh()->status)->toBe('completed');
    } finally {
        CarbonImmutable::setTestNow();
    }
});

it('reuses formatted saved mobile details and explains invalid client contacts before check-in', function () {
    $p = operationalPath();
    $client = Client::query()->create(['business_id' => $p['business']->id, 'name' => 'Saved Client', 'normalized_name' => 'savedclient',
        'status' => 'active', 'mobile' => '+91 91111 11111', 'normalized_mobile' => '+919111111111']);
    $data = ['location' => $p['location']->public_id, 'service' => $p['service']->public_id, 'client_mode' => 'existing', 'client' => $client->public_id, 'arrived_at' => '2035-10-10T10:00'];
    $this->actingAs($p['user'])->post(route('business.walk-ins.store', $p['business']), $data)->assertRedirect()->assertSessionHasNoErrors();
    expect(WalkInEntry::query()->first()->client_mobile)->toBe('+919111111111')->and($client->fresh()->mobile)->toBe('+91 91111 11111');
    $client->update(['mobile' => 'invalid']);
    $this->post(route('business.walk-ins.store', $p['business']), $data)->assertRedirect()->assertSessionHasErrors('client');
    expect(WalkInEntry::query()->count())->toBe(1);
});

it('rejects an expired start check without creating a partial appointment', function () {
    $p = operationalPath();
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2035-10-10 09:15', 'Asia/Kolkata'));
    try {
        $q = app(WalkInQueueService::class);
        $e = $q->add($p['business']->id, $p['location']->id, $p['service']->id, 'Waiting Client', '+919111111111', null, CarbonImmutable::now()->utc()->subMinutes(15), null, 'reception', 'user', $p['user']->id);
        expect(fn () => $q->startService($e, CarbonImmutable::now()->utc()->subMinutes(15), 'expired-check', 1, $p['staff']->id, 'reception', 'user', $p['user']->id))->toThrow(BookingRuleViolation::class);
        expect(Appointment::query()->count())->toBe(0)->and($e->fresh()->status)->toBe('waiting')->and($e->fresh()->appointment_id)->toBeNull();
    } finally {
        CarbonImmutable::setTestNow();
    }
});

it('does not accept a service from another branch into the queue', function () {
    $p = operationalPath();
    $other = Location::factory()->create(['business_id' => $p['business']->id]);
    expect(fn () => app(WalkInQueueService::class)->add($p['business']->id, $other->id, $p['service']->id, 'Wrong branch', '+919111111111', null, CarbonImmutable::now()->utc(), null, 'reception', 'user', $p['user']->id))->toThrow(BookingRuleViolation::class);
    expect(WalkInEntry::query()->count())->toBe(0);
});
