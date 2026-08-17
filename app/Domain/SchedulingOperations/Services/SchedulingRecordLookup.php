<?php

namespace App\Domain\SchedulingOperations\Services;

use App\Domain\SchedulingOperations\Models\Appointment;
use App\Domain\SchedulingOperations\Models\WalkInEntry;

class SchedulingRecordLookup
{
    public function appointment(int $businessId, string $publicId): Appointment
    {
        return Appointment::query()->where('business_id', $businessId)->where('public_id', $publicId)->firstOrFail();
    }

    public function walkIn(int $businessId, string $publicId): WalkInEntry
    {
        return WalkInEntry::query()->where('business_id', $businessId)->where('public_id', $publicId)->firstOrFail();
    }
}
