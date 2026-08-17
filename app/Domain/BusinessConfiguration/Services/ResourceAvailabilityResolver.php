<?php

namespace App\Domain\BusinessConfiguration\Services;

use App\Domain\BusinessConfiguration\Models\PhysicalResource;
use Carbon\CarbonImmutable;

class ResourceAvailabilityResolver
{
    public function __construct(private readonly LocalHoursResolver $locations) {}

    /** @return list<array{opens_at:string,closes_at:string,source:string}> */
    public function windows(PhysicalResource $resource, CarbonImmutable $localDate): array
    {
        if (! $resource->is_active) {
            return [];
        }
        $hours = $resource->hours()->where('day_of_week', $localDate->setTimezone($resource->location->time_zone)->dayOfWeekIso)->orderBy('sequence')->get();
        if ($hours->isEmpty()) {
            return $this->locations->windows($resource->location, $localDate);
        }

        return $hours->map(fn ($window) => ['opens_at' => $window->opens_at, 'closes_at' => $window->closes_at, 'source' => 'resource_hours'])->all();
    }

    /** @return list<array{starts_at_utc:string,ends_at_utc:string,reason:string}> */
    public function maintenance(PhysicalResource $resource, CarbonImmutable $fromUtc, CarbonImmutable $untilUtc): array
    {
        return $resource->maintenanceBlocks()->where('starts_at_utc', '<', $untilUtc->utc())->where('ends_at_utc', '>', $fromUtc->utc())->orderBy('starts_at_utc')->get()
            ->map(fn ($block) => ['starts_at_utc' => $block->starts_at_utc->utc()->toIso8601String(), 'ends_at_utc' => $block->ends_at_utc->utc()->toIso8601String(), 'reason' => $block->reason])->all();
    }
}
