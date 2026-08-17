<?php

namespace App\Domain\BusinessConfiguration\Services;

use App\Domain\BusinessConfiguration\Contracts\AppointmentImpactSource;
use App\Domain\PlatformAccess\Models\Business;
use Illuminate\Database\Eloquent\Model;

class NullAppointmentImpactSource implements AppointmentImpactSource
{
    public function affectedAppointmentIds(Business $business, Model $subject, string $changeType, array $proposedChange): array
    {
        return [];
    }
}
