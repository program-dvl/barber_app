<?php

namespace App\Domain\SchedulingOperations\Services;

use App\Domain\BusinessConfiguration\Contracts\AppointmentImpactSource;
use App\Domain\BusinessConfiguration\Models\PhysicalResource;
use App\Domain\BusinessConfiguration\Models\Service;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\Location;
use App\Domain\PlatformAccess\Models\StaffProfile;
use App\Domain\SchedulingOperations\Models\Appointment;
use Illuminate\Database\Eloquent\Model;

class SchedulingAppointmentImpactSource implements AppointmentImpactSource
{
    public function affectedAppointmentIds(Business $business, Model $subject, string $changeType, array $proposedChange): array
    {
        $query = Appointment::query()
            ->where('business_id', $business->id)
            ->whereIn('status', ['pending_confirmation', 'confirmed', 'arrived', 'checked_in', 'in_service', 'late'])
            ->where('ends_at_utc', '>', now());

        if ($subject instanceof Location) {
            $query->where('location_id', $subject->id);
        } elseif ($subject instanceof StaffProfile) {
            $query->whereHas('segments', fn ($segments) => $segments->where('staff_profile_id', $subject->id));
        } elseif ($subject instanceof Service) {
            $query->whereHas('serviceLines', fn ($lines) => $lines->where('service_id', $subject->id));
        } elseif ($subject instanceof PhysicalResource) {
            $query->whereHas('resourceClaims', fn ($claims) => $claims->where('physical_resource_id', $subject->id));
        } else {
            return [];
        }

        $appointments = $query->orderBy('starts_at_utc')->get(['public_id', 'starts_at_utc', 'time_zone']);
        if (isset($proposedChange['starts_on'])) {
            $startsOn = (string) $proposedChange['starts_on'];
            $endsOn = (string) ($proposedChange['ends_on'] ?? $startsOn);
            $appointments = $appointments->filter(function (Appointment $appointment) use ($startsOn, $endsOn): bool {
                $date = $appointment->starts_at_utc->setTimezone($appointment->time_zone)->toDateString();

                return $date >= $startsOn && $date <= $endsOn;
            });
        }

        return $appointments->pluck('public_id')->unique()->values()->all();
    }
}
