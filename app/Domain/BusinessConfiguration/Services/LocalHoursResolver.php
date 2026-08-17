<?php

namespace App\Domain\BusinessConfiguration\Services;

use App\Domain\PlatformAccess\Models\Location;
use Carbon\CarbonImmutable;

class LocalHoursResolver
{
    /** @return list<array{opens_at:string,closes_at:string,source:string}> */
    public function windows(Location $location, CarbonImmutable $localDate): array
    {
        $date = $localDate->setTimezone($location->time_zone)->toDateString();
        $exceptions = $location->scheduleExceptions()->whereDate('starts_on', '<=', $date)->whereDate('ends_on', '>=', $date)->get();
        if ($exceptions->contains(fn ($exception) => in_array($exception->kind, ['holiday', 'closure', 'temporary_closure'], true))) {
            return [];
        }

        $special = $exceptions->where('kind', 'special_hours')->sortBy('opens_at');
        if ($special->isNotEmpty()) {
            return $special->map(fn ($window) => ['opens_at' => $window->opens_at, 'closes_at' => $window->closes_at, 'source' => 'special_hours'])->values()->all();
        }

        return $location->hours()
            ->where('day_of_week', $localDate->setTimezone($location->time_zone)->dayOfWeekIso)
            ->where(fn ($query) => $query->whereNull('effective_from')->orWhereDate('effective_from', '<=', $date))
            ->where(fn ($query) => $query->whereNull('effective_until')->orWhereDate('effective_until', '>=', $date))
            ->orderBy('sequence')->get()
            ->map(fn ($window) => ['opens_at' => $window->opens_at, 'closes_at' => $window->closes_at, 'source' => 'normal_hours'])
            ->all();
    }
}
