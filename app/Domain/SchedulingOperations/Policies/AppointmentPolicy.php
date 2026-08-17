<?php

namespace App\Domain\SchedulingOperations\Policies;

use App\Domain\PlatformAccess\Enums\PermissionName;
use App\Domain\SchedulingOperations\Models\Appointment;
use App\Models\User;
use App\Support\Tenancy\TenantContext;

class AppointmentPolicy
{
    public function view(User $user, Appointment $appointment): bool
    {
        $membership = $this->membership($user, $appointment->business_id);
        if (! $membership) {
            return false;
        }
        if ($membership->hasPermissionTo(PermissionName::CalendarViewAll->value, 'web')) {
            return $membership->locations()->whereKey($appointment->location_id)->exists()
                || $membership->hasRole('owner', 'web');
        }

        return $membership->hasPermissionTo(PermissionName::CalendarViewOwn->value, 'web')
            && $membership->staffProfile
            && $appointment->segments()->where('staff_profile_id', $membership->staffProfile->id)->exists();
    }

    public function update(User $user, Appointment $appointment): bool
    {
        $membership = $this->membership($user, $appointment->business_id);
        if (! $membership) {
            return false;
        }
        if ($membership->hasPermissionTo(PermissionName::AppointmentsManageAll->value, 'web')) {
            return $membership->locations()->whereKey($appointment->location_id)->exists()
                || $membership->hasRole('owner', 'web');
        }

        return $membership->hasPermissionTo(PermissionName::AppointmentsManageOwn->value, 'web')
            && $membership->staffProfile
            && $appointment->segments()->where('staff_profile_id', $membership->staffProfile->id)->exists();
    }

    public function override(User $user, Appointment $appointment): bool
    {
        return $this->membership($user, $appointment->business_id)?->hasPermissionTo(PermissionName::ScheduleOverride->value, 'web') ?? false;
    }

    private function membership(User $user, int $businessId): mixed
    {
        $context = app(TenantContext::class);
        $membership = $context->membership();

        return $context->hasBusiness()
            && $context->business()->id === $businessId
            && $membership?->user_id === $user->id
            && $membership->isActive()
            ? $membership
            : null;
    }
}
