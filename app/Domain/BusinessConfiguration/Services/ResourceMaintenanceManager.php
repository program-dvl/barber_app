<?php

namespace App\Domain\BusinessConfiguration\Services;

use App\Domain\BusinessConfiguration\Models\PhysicalResource;
use App\Domain\BusinessConfiguration\Models\ResourceMaintenanceBlock;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class ResourceMaintenanceManager
{
    public function block(PhysicalResource $resource, string $localStart, string $localEnd, string $reason): ResourceMaintenanceBlock
    {
        $timeZone = $resource->location->time_zone;
        $start = CarbonImmutable::parse($localStart, $timeZone);
        $end = CarbonImmutable::parse($localEnd, $timeZone);
        if ($end <= $start) {
            throw ValidationException::withMessages(['ends_at' => 'Maintenance must end after it starts.']);
        }

        return ResourceMaintenanceBlock::query()->create([
            'business_id' => $resource->business_id, 'physical_resource_id' => $resource->id,
            'starts_at_utc' => $start->utc(), 'ends_at_utc' => $end->utc(), 'time_zone' => $timeZone,
            'local_starts_at' => $start->format('Y-m-d H:i:s'), 'local_ends_at' => $end->format('Y-m-d H:i:s'),
            'reason' => $reason,
        ]);
    }
}
