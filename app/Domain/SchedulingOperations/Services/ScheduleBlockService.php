<?php

namespace App\Domain\SchedulingOperations\Services;

use App\Domain\PlatformAccess\Models\Location;
use App\Domain\PlatformAccess\Models\StaffProfile;
use App\Domain\SchedulingOperations\Exceptions\BookingRuleViolation;
use App\Domain\SchedulingOperations\Models\ScheduleBlock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ScheduleBlockService
{
    public function create(
        int $businessId,
        int $locationId,
        int $staffProfileId,
        string $kind,
        string $label,
        CarbonImmutable $startsAtUtc,
        CarbonImmutable $endsAtUtc,
        string $reason,
        ?string $actorType = null,
        ?int $actorId = null,
    ): ScheduleBlock {
        if (! in_array($kind, ['personal_block', 'staff_break'], true)) {
            throw new BookingRuleViolation('INVALID_BLOCK', 'Choose a supported blocked-time type.');
        }
        if (trim($label) === '' || trim($reason) === '' || $endsAtUtc->lte($startsAtUtc)) {
            throw new BookingRuleViolation('INVALID_BLOCK', 'A label, reason, and valid time range are required.');
        }

        return DB::transaction(function () use ($businessId, $locationId, $staffProfileId, $kind, $label, $startsAtUtc, $endsAtUtc, $reason, $actorType, $actorId): ScheduleBlock {
            $location = Location::query()->where('business_id', $businessId)->sharedLock()->findOrFail($locationId);
            StaffProfile::query()->where('business_id', $businessId)->lockForUpdate()->findOrFail($staffProfileId);
            $hasAppointment = DB::table('appointment_segments as segments')
                ->join('appointments', 'appointments.id', '=', 'segments.appointment_id')
                ->where('segments.business_id', $businessId)
                ->where('segments.staff_profile_id', $staffProfileId)
                ->where('segments.occupies_staff', true)
                ->whereIn('appointments.status', ['pending_confirmation', 'confirmed', 'arrived', 'checked_in', 'in_service', 'late'])
                ->where('segments.starts_at_utc', '<', $endsAtUtc->utc())
                ->where('segments.ends_at_utc', '>', $startsAtUtc->utc())
                ->exists();
            $hasHold = DB::table('capacity_hold_segments as segments')
                ->join('capacity_holds as holds', 'holds.id', '=', 'segments.capacity_hold_id')
                ->where('segments.business_id', $businessId)
                ->where('segments.staff_profile_id', $staffProfileId)
                ->where('segments.occupies_staff', true)
                ->where('holds.status', 'active')
                ->where('holds.expires_at', '>', CarbonImmutable::now()->utc())
                ->where('segments.starts_at_utc', '<', $endsAtUtc->utc())
                ->where('segments.ends_at_utc', '>', $startsAtUtc->utc())
                ->exists();
            $hasBlock = ScheduleBlock::query()
                ->where('business_id', $businessId)
                ->where('staff_profile_id', $staffProfileId)
                ->where('starts_at_utc', '<', $endsAtUtc->utc())
                ->where('ends_at_utc', '>', $startsAtUtc->utc())
                ->exists();
            if ($hasAppointment || $hasHold || $hasBlock) {
                throw new BookingRuleViolation('CAPACITY_CONFLICT', 'Blocked time overlaps existing work. Resolve the affected visit or hold first.');
            }

            return ScheduleBlock::query()->create([
                'business_id' => $businessId,
                'location_id' => $locationId,
                'staff_profile_id' => $staffProfileId,
                'kind' => $kind,
                'label' => trim($label),
                'private_reason' => trim($reason),
                'starts_at_utc' => $startsAtUtc->utc(),
                'ends_at_utc' => $endsAtUtc->utc(),
                'time_zone' => $location->time_zone,
                'local_starts_at' => $startsAtUtc->setTimezone($location->time_zone)->format('Y-m-d H:i:s P'),
                'local_ends_at' => $endsAtUtc->setTimezone($location->time_zone)->format('Y-m-d H:i:s P'),
                'actor_type' => $actorType,
                'actor_id' => $actorId,
            ]);
        }, 5);
    }
}
