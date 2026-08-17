<?php

namespace App\Domain\BusinessConfiguration\Contracts;

use App\Domain\PlatformAccess\Models\Business;
use Illuminate\Database\Eloquent\Model;

interface AppointmentImpactSource
{
    /**
     * Return future appointment public IDs that would violate the proposed
     * configuration. The scheduling module supplies the implementation.
     *
     * @param  array<string, mixed>  $proposedChange
     * @return list<string>
     */
    public function affectedAppointmentIds(Business $business, Model $subject, string $changeType, array $proposedChange): array;
}
