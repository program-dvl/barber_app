<?php

namespace App\Domain\SchedulingOperations\Services;

use App\Domain\BusinessConfiguration\Contracts\AvailabilityConfiguration;
use App\Domain\BusinessConfiguration\Models\Service;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\Location;
use App\Domain\PlatformAccess\Models\StaffProfile;
use App\Domain\SchedulingOperations\Contracts\AppointmentLifecycleCommand;
use App\Domain\SchedulingOperations\Contracts\BookingCommitCommand;
use App\Domain\SchedulingOperations\Data\BookingLineRequest;
use App\Domain\SchedulingOperations\Data\BookingRequest;
use App\Domain\SchedulingOperations\Enums\WalkInStatus;
use App\Domain\SchedulingOperations\Exceptions\BookingRuleViolation;
use App\Domain\SchedulingOperations\Models\Appointment;
use App\Domain\SchedulingOperations\Models\OperationalNotificationEvent;
use App\Domain\SchedulingOperations\Models\WalkInEntry;
use App\Domain\SchedulingOperations\Models\WalkInHistory;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class WalkInQueueService
{
    public function __construct(
        private readonly BookingCommitCommand $bookings,
        private readonly AppointmentLifecycleCommand $lifecycle,
    ) {}

    public function add(
        int $businessId,
        int $locationId,
        int $serviceId,
        string $clientName,
        string $clientMobile,
        ?int $preferredStaffId,
        CarbonImmutable $arrivedAt,
        ?string $notes,
        string $source,
        ?string $actorType,
        ?int $actorId,
        ?int $clientId = null,
        ?string $clientEmail = null,
    ): WalkInEntry {
        $location = Location::query()->where('business_id', $businessId)->findOrFail($locationId);
        $service = Service::query()->where('business_id', $businessId)->where('is_active', true)->findOrFail($serviceId);
        if ($service->kind !== 'service' || ! $service->locations()->whereKey($locationId)->where('location_service.is_eligible', true)->exists()) {
            throw new BookingRuleViolation('SERVICE_UNAVAILABLE', 'This service is not offered at this location. Choose another service.');
        }
        if ($preferredStaffId) {
            $preferred = StaffProfile::query()->where('business_id', $businessId)->where('status', 'active')->findOrFail($preferredStaffId);
            app(AvailabilityConfiguration::class)->resolveService($service, $preferred, $location);
        }
        if (trim($clientName) === '' || trim($clientMobile) === '') {
            throw new BookingRuleViolation('INVALID_WALK_IN', 'Name and mobile number are required for a walk-in.');
        }

        return DB::transaction(function () use ($businessId, $locationId, $serviceId, $clientName, $clientMobile, $preferredStaffId, $arrivedAt, $notes, $source, $actorType, $actorId, $location, $service, $clientId, $clientEmail): WalkInEntry {
            Location::query()->where('business_id', $businessId)->lockForUpdate()->findOrFail($locationId);
            $position = ((int) WalkInEntry::query()
                ->where('business_id', $businessId)
                ->where('location_id', $locationId)
                ->whereIn('status', ['waiting', 'notified', 'assigned'])
                ->max('queue_position')) + 1;
            $estimate = $this->estimate($businessId, $location, $service, $preferredStaffId, $arrivedAt, $position);
            $entry = WalkInEntry::query()->create([
                'business_id' => $businessId,
                'location_id' => $locationId,
                'service_id' => $serviceId,
                'preferred_staff_profile_id' => $preferredStaffId,
                'client_id' => $clientId,
                'client_name' => trim($clientName),
                'client_mobile' => trim($clientMobile),
                'client_email' => filled($clientEmail) ? strtolower(trim($clientEmail)) : null,
                'notes' => $notes ? trim($notes) : null,
                'status' => WalkInStatus::Waiting->value,
                'queue_position' => $position,
                'arrived_at' => $arrivedAt->utc(),
                'estimated_service_at' => $estimate['estimated_service_at'],
                'estimated_wait_minutes' => $estimate['estimated_wait_minutes'],
                'estimate_evidence' => $estimate['evidence'],
            ]);
            $this->history($entry, 'created', null, WalkInStatus::Waiting->value, $source, $actorType, $actorId, null, [], [
                'queue_position' => $position, 'estimated_wait_minutes' => $estimate['estimated_wait_minutes'],
            ], $arrivedAt);

            return $entry->fresh('history');
        }, 5);
    }

    /** @param list<string> $orderedPublicIds */
    public function reorder(
        int $businessId,
        int $locationId,
        array $orderedPublicIds,
        string $reason,
        string $source,
        ?string $actorType,
        ?int $actorId,
    ): array {
        if (trim($reason) === '') {
            throw new BookingRuleViolation('REASON_REQUIRED', 'Explain why the queue order is changing.');
        }

        return DB::transaction(function () use ($businessId, $locationId, $orderedPublicIds, $reason, $source, $actorType, $actorId): array {
            Location::query()->where('business_id', $businessId)->lockForUpdate()->findOrFail($locationId);
            $entries = WalkInEntry::query()
                ->where('business_id', $businessId)
                ->where('location_id', $locationId)
                ->whereIn('status', ['waiting', 'notified', 'assigned'])
                ->orderBy('queue_position')
                ->lockForUpdate()
                ->get();
            $currentIds = $entries->pluck('public_id')->sort()->values()->all();
            $requestedIds = collect($orderedPublicIds)->unique()->sort()->values()->all();
            if ($currentIds !== $requestedIds || count($orderedPublicIds) !== count($currentIds)) {
                throw new BookingRuleViolation('STALE_QUEUE', 'The queue changed in another session. Refresh and try again.');
            }

            $byPublicId = $entries->keyBy('public_id');
            foreach (array_values($orderedPublicIds) as $index => $publicId) {
                $entry = $byPublicId->get($publicId);
                $before = $entry->queue_position;
                $entry->update(['queue_position' => $index + 1, 'version' => $entry->version + 1]);
                if ($before !== $index + 1) {
                    $this->history($entry, 'reordered', $entry->status, $entry->status, $source, $actorType, $actorId, $reason, ['queue_position' => $before], ['queue_position' => $index + 1], CarbonImmutable::now()->utc());
                }
            }

            return $entries->fresh()->sortBy('queue_position')->values()->all();
        }, 5);
    }

    public function assign(
        WalkInEntry $entry,
        int $staffProfileId,
        int $expectedVersion,
        string $source,
        ?string $actorType,
        ?int $actorId,
        ?string $reason = null,
    ): WalkInEntry {
        return DB::transaction(function () use ($entry, $staffProfileId, $expectedVersion, $source, $actorType, $actorId, $reason): WalkInEntry {
            $current = WalkInEntry::query()->where('business_id', $entry->business_id)->lockForUpdate()->findOrFail($entry->id);
            $this->assertVersionAndActive($current, $expectedVersion);
            if ($current->appointment_id) {
                throw new BookingRuleViolation('LINKED_APPOINTMENT', 'This walk-in has a Calendar visit. Change staff in Calendar so the reserved time stays correct.');
            }
            $staff = StaffProfile::query()->where('business_id', $entry->business_id)->where('status', 'active')->whereHas('locations', fn ($q) => $q->whereKey($current->location_id))->findOrFail($staffProfileId);
            $service = Service::query()->where('business_id', $entry->business_id)->findOrFail($current->service_id);
            $location = Location::query()->where('business_id', $entry->business_id)->findOrFail($current->location_id);
            app(AvailabilityConfiguration::class)->resolveService($service, $staff, $location);
            $before = ['staff_profile_id' => $current->assigned_staff_profile_id, 'status' => $current->status];
            $current->update([
                'assigned_staff_profile_id' => $staffProfileId,
                'status' => WalkInStatus::Assigned->value,
                'version' => $current->version + 1,
            ]);
            $this->history($current, 'assigned', $before['status'], WalkInStatus::Assigned->value, $source, $actorType, $actorId, $reason, $before, ['staff_profile_id' => $staffProfileId], CarbonImmutable::now()->utc());

            return $current->fresh('history');
        }, 5);
    }

    public function notify(WalkInEntry $entry, int $expectedVersion, string $source, ?string $actorType, ?int $actorId): WalkInEntry
    {
        return DB::transaction(function () use ($entry, $expectedVersion, $source, $actorType, $actorId): WalkInEntry {
            $current = WalkInEntry::query()->where('business_id', $entry->business_id)->lockForUpdate()->findOrFail($entry->id);
            $this->assertVersionAndActive($current, $expectedVersion);
            $previous = $current->status;
            $now = CarbonImmutable::now()->utc();
            $current->update(['status' => WalkInStatus::Notified->value, 'notified_at' => $now, 'version' => $current->version + 1]);
            OperationalNotificationEvent::query()->firstOrCreate([
                'business_id' => $current->business_id,
                'idempotency_key' => 'walk-in-turn:'.$current->id.':'.$current->version,
            ], [
                'event_type' => 'walk_in.turn_approaching',
                'subject_type' => WalkInEntry::class,
                'subject_id' => $current->id,
                'payload' => ['queue_position' => $current->queue_position, 'estimated_service_at' => $current->estimated_service_at?->toIso8601String()],
                'status' => 'pending',
                'occurred_at' => $now,
            ]);
            $this->history($current, 'notified', $previous, WalkInStatus::Notified->value, $source, $actorType, $actorId, null, [], [], $now);

            return $current->fresh('history');
        }, 5);
    }

    public function convertToAppointment(
        WalkInEntry $entry,
        CarbonImmutable $startsAtUtc,
        string $idempotencyKey,
        int $expectedVersion,
        ?int $staffProfileId,
        string $source,
        ?string $actorType,
        ?int $actorId,
    ): Appointment {
        $current = WalkInEntry::query()->where('business_id', $entry->business_id)->findOrFail($entry->id);
        $this->assertVersionAndActive($current, $expectedVersion);
        $staffId = $staffProfileId ?: $current->assigned_staff_profile_id ?: $current->preferred_staff_profile_id;
        $request = new BookingRequest(
            $current->business_id,
            $current->location_id,
            $startsAtUtc->utc(),
            [new BookingLineRequest($current->service_id, $staffId, [], $staffId === null)],
            'walk_in',
            'existing',
            CarbonImmutable::now()->utc(),
            'walk-in:'.$current->public_id,
            $actorType,
            $actorId,
            $current->client_name,
            $current->client_mobile,
            $current->notes,
            [],
            null,
            $current->client_email,
            clientPublicId: $current->client?->public_id,
        );
        // The atomic booking command revalidates against every future visit,
        // hold, staff rule, and resource immediately before capacity is claimed.
        $appointment = $this->bookings->commit($request, $idempotencyKey);

        DB::transaction(function () use ($current, $appointment, $expectedVersion, $source, $actorType, $actorId): void {
            $locked = WalkInEntry::query()->where('business_id', $current->business_id)->lockForUpdate()->findOrFail($current->id);
            if ($locked->appointment_id === $appointment->id) {
                return;
            }
            $this->assertVersionAndActive($locked, $expectedVersion);
            $before = $locked->status;
            $locked->update([
                'appointment_id' => $appointment->id,
                'assigned_staff_profile_id' => $appointment->segments()->whereNotNull('staff_profile_id')->value('staff_profile_id'),
                'status' => WalkInStatus::Assigned->value,
                'version' => $locked->version + 1,
            ]);
            $this->history($locked, 'converted', $before, WalkInStatus::Assigned->value, $source, $actorType, $actorId, null, [], ['appointment_public_id' => $appointment->public_id], CarbonImmutable::now()->utc());
        }, 5);

        return $appointment;
    }

    public function startService(
        WalkInEntry $entry,
        CarbonImmutable $startsAtUtc,
        string $idempotencyKey,
        int $expectedVersion,
        ?int $staffProfileId,
        string $source,
        ?string $actorType,
        ?int $actorId,
    ): Appointment {
        return DB::transaction(function () use ($entry, $startsAtUtc, $idempotencyKey, $expectedVersion, $staffProfileId, $source, $actorType, $actorId): Appointment {
            $hash = hash('sha256', json_encode([$entry->id, $startsAtUtc->utc()->toIso8601String(), $expectedVersion, $staffProfileId, $source, $actorType, $actorId], JSON_THROW_ON_ERROR));
            $key = app(AtomicBookingService::class)->claimCommandKey($entry->business_id, 'operation', 'queue-start:'.$idempotencyKey, $hash);
            if ($key->result_id) {
                return Appointment::query()->where('business_id', $entry->business_id)->findOrFail($key->result_id);
            }
            $entry = WalkInEntry::query()->where('business_id', $entry->business_id)->lockForUpdate()->findOrFail($entry->id);
            $this->assertVersionAndActive($entry, $expectedVersion);
            if (! $entry->appointment_id && $startsAtUtc->lt(CarbonImmutable::now()->utc()->startOfMinute())) {
                throw new BookingRuleViolation('STALE_AVAILABILITY', 'The availability check has expired. Check again before starting service.');
            }
            $chosenStaff = $staffProfileId ?: $entry->assigned_staff_profile_id ?: $entry->preferred_staff_profile_id;
            if ($chosenStaff && Appointment::query()->where('business_id', $entry->business_id)->where('status', 'in_service')
                ->where('ends_at_utc', '<=', now()->utc())->whereHas('segments', fn ($q) => $q->where('staff_profile_id', $chosenStaff)->where('occupies_staff', true))->exists()) {
                throw new BookingRuleViolation('SERVICE_RUNNING_OVER', 'This staff member is still serving a client past the planned finish. Complete or adjust that visit before starting another.');
            }
            $appointment = $entry->appointment_id
                ? Appointment::query()->where('business_id', $entry->business_id)->findOrFail($entry->appointment_id)
                : $this->convertToAppointment($entry, $startsAtUtc, $idempotencyKey.':convert', $expectedVersion, $staffProfileId, $source, $actorType, $actorId);
            $actualStaff = $appointment->segments()->where('occupies_staff', true)->pluck('staff_profile_id');
            if (Appointment::query()->where('business_id', $entry->business_id)->whereKeyNot($appointment->id)
                ->where('status', 'in_service')->where('ends_at_utc', '<=', now()->utc())
                ->whereHas('segments', fn ($q) => $q->whereIn('staff_profile_id', $actualStaff)->where('occupies_staff', true))->exists()) {
                throw new BookingRuleViolation('SERVICE_RUNNING_OVER', 'The selected staff member is still serving a client past the planned finish. Complete or adjust that visit first.');
            }
            $entry = $entry->fresh();
            if ($appointment->status === 'confirmed') {
                $appointment = $this->lifecycle->transition($appointment, 'arrived', $idempotencyKey.':arrived', $appointment->version, $source, $actorType, $actorId);
            }
            if ($appointment->status === 'arrived') {
                $appointment = $this->lifecycle->transition($appointment, 'in_service', $idempotencyKey.':start', $appointment->version, $source, $actorType, $actorId);
            }

            DB::transaction(function () use ($entry, $source, $actorType, $actorId): void {
                $current = WalkInEntry::query()->where('business_id', $entry->business_id)->lockForUpdate()->findOrFail($entry->id);
                if ($current->status === WalkInStatus::InService->value) {
                    return;
                }
                $now = CarbonImmutable::now()->utc();
                $previous = $current->status;
                $actualWait = max(0, (int) $current->arrived_at->diffInMinutes($now));
                $current->update([
                    'status' => WalkInStatus::InService->value,
                    'service_started_at' => $now,
                    'actual_wait_minutes' => $actualWait,
                    'version' => $current->version + 1,
                ]);
                $this->history($current, 'service_started', $previous, WalkInStatus::InService->value, $source, $actorType, $actorId, null, [], ['actual_wait_minutes' => $actualWait], $now);
            }, 5);

            DB::table('booking_command_keys')->where('id', $key->id)->update(['result_type' => 'appointment', 'result_id' => $appointment->id, 'updated_at' => now()]);

            return $appointment->fresh();
        }, 5);
    }

    public function markLeft(WalkInEntry $entry, int $expectedVersion, string $reason, string $source, ?string $actorType, ?int $actorId): WalkInEntry
    {
        if (trim($reason) === '') {
            throw new BookingRuleViolation('REASON_REQUIRED', 'Explain why the walk-in left the queue.');
        }

        return DB::transaction(function () use ($entry, $expectedVersion, $reason, $source, $actorType, $actorId): WalkInEntry {
            $current = WalkInEntry::query()->where('business_id', $entry->business_id)->lockForUpdate()->findOrFail($entry->id);
            $this->assertVersionAndActive($current, $expectedVersion);
            if ($current->appointment_id) {
                throw new BookingRuleViolation('LINKED_APPOINTMENT', 'This walk-in has a Calendar visit. Cancel that visit in Calendar to release the reserved time.');
            }
            $previous = $current->status;
            $now = CarbonImmutable::now()->utc();
            $actualWait = max(0, (int) $current->arrived_at->diffInMinutes($now));
            $current->update([
                'status' => WalkInStatus::Left->value,
                'abandoned_at' => $now,
                'actual_wait_minutes' => $actualWait,
                'version' => $current->version + 1,
            ]);
            $this->history($current, 'left', $previous, WalkInStatus::Left->value, $source, $actorType, $actorId, $reason, [], ['actual_wait_minutes' => $actualWait], $now);

            return $current->fresh('history');
        }, 5);
    }

    /** @return array{estimated_service_at:?CarbonImmutable,estimated_wait_minutes:?int,evidence:array<string,mixed>} */
    private function estimate(int $businessId, Location $location, Service $service, ?int $preferredStaffId, CarbonImmutable $arrivedAt, int $position): array
    {
        $now = CarbonImmutable::now()->utc()->max($arrivedAt);
        $entries = WalkInEntry::query()->where('business_id', $businessId)->where('location_id', $location->id)
            ->whereIn('status', ['waiting', 'notified', 'assigned', 'in_service'])->with('appointment.segments')->orderBy('queue_position')->get();
        $candidate = new WalkInEntry(['public_id' => 'arrival-estimate', 'service_id' => $service->id,
            'status' => 'waiting', 'queue_position' => $position, 'preferred_staff_profile_id' => $preferredStaffId]);
        $entries->push($candidate);
        $staff = StaffProfile::query()->where('business_id', $businessId)->where('status', 'active')
            ->whereHas('locations', fn ($q) => $q->whereKey($location->id))->with(['locations', 'availabilityRules'])->get();
        $services = Service::query()->where('business_id', $businessId)->whereIn('id', $entries->pluck('service_id'))
            ->with(['staffAssignments', 'locations', 'segments'])->get();
        $business = Business::query()->findOrFail($businessId);
        $board = app(WalkInWorkspaceQuery::class)->build($location, $staff, $services, $entries,
            app(CalendarWorkspaceQuery::class)->build($location, $staff, $now, 1), $now, max(1, (int) ($business->appointment_interval_minutes ?: 15)));
        $value = $board['forecast'][$candidate->public_id]['estimated_at'] ?? null;
        $estimatedAt = $value ? CarbonImmutable::parse($value)->utc() : null;

        return [
            'estimated_service_at' => $estimatedAt,
            'estimated_wait_minutes' => $estimatedAt ? max(0, (int) $arrivedAt->utc()->diffInMinutes($estimatedAt)) : null,
            'evidence' => [
                'calculated_at_utc' => $now->toIso8601String(), 'queue_entries_ahead' => $entries->whereIn('status', ['waiting', 'assigned', 'notified'])->count() - 1,
                'method' => 'ordered_qualified_staff_forecast', 'service_duration_minutes' => (int) $service->duration_minutes,
                'future_appointments_and_staff_capacity_checked' => true, 'resources_checked' => false,
                'availability_slot_found' => $estimatedAt !== null, 'advisory' => true,
            ],
        ];
    }

    private function assertVersionAndActive(WalkInEntry $entry, int $expectedVersion): void
    {
        if ($entry->version !== $expectedVersion) {
            throw new BookingRuleViolation('STALE_QUEUE', 'The queue changed in another session. Refresh and try again.');
        }
        if (in_array($entry->status, [WalkInStatus::Completed->value, WalkInStatus::Left->value, WalkInStatus::InService->value], true)) {
            throw new BookingRuleViolation('WALK_IN_NOT_WAITING', 'This walk-in is no longer waiting.');
        }
    }

    /** @param array<string, mixed> $before @param array<string, mixed> $after */
    private function history(
        WalkInEntry $entry,
        string $action,
        ?string $previousStatus,
        string $status,
        string $source,
        ?string $actorType,
        ?int $actorId,
        ?string $reason,
        array $before,
        array $after,
        CarbonImmutable $occurredAt,
    ): void {
        WalkInHistory::query()->create([
            'business_id' => $entry->business_id,
            'walk_in_entry_id' => $entry->id,
            'action' => $action,
            'previous_status' => $previousStatus,
            'status' => $status,
            'source' => $source,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'reason' => $reason,
            'before' => $before,
            'after' => $after,
            'occurred_at' => $occurredAt,
        ]);
    }
}
