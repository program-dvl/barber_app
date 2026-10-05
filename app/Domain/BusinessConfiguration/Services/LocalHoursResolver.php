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
        $exceptions = ($location->relationLoaded('scheduleExceptions') ? $location->scheduleExceptions : $location->scheduleExceptions()->get())->filter(fn ($e) => $e->starts_on->toDateString() <= $date && $e->ends_on->toDateString() >= $date);
        if ($exceptions->contains(fn ($exception) => in_array($exception->kind, ['holiday', 'closure', 'temporary_closure'], true))) {
            return [];
        }

        $special = $exceptions->where('kind', 'special_hours')->sortBy('opens_at');
        if ($special->isNotEmpty()) {
            return $special->map(fn ($window) => ['opens_at' => $window->opens_at, 'closes_at' => $window->closes_at, 'source' => 'special_hours'])->values()->all();
        }

        return ($location->relationLoaded('hours') ? $location->hours : $location->hours()->get())
            ->filter(fn ($h) => (int) $h->day_of_week === $localDate->setTimezone($location->time_zone)->dayOfWeekIso
                && (! $h->effective_from || $h->effective_from->toDateString() <= $date)
                && (! $h->effective_until || $h->effective_until->toDateString() >= $date))
            ->sortBy('sequence')->map(fn ($h) => ['opens_at' => $h->opens_at, 'closes_at' => $h->closes_at, 'source' => 'normal_hours'])->values()->all();
    }
}
