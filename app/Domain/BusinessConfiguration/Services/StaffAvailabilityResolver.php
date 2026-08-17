<?php

namespace App\Domain\BusinessConfiguration\Services;

use App\Domain\PlatformAccess\Models\Location;
use App\Domain\PlatformAccess\Models\StaffProfile;
use Carbon\CarbonImmutable;

class StaffAvailabilityResolver
{
    /** @return list<array{opens_at:string,closes_at:string,source:string}> */
    public function windows(StaffProfile $staff, Location $location, CarbonImmutable $localDate): array
    {
        if ($staff->business_id !== $location->business_id || $staff->status !== 'active' || ! $staff->locations()->whereKey($location->id)->exists()) {
            return [];
        }
        $date = $localDate->setTimezone($location->time_zone)->toDateString();
        $day = $localDate->setTimezone($location->time_zone)->dayOfWeekIso;
        $rules = $staff->availabilityRules()->where(fn ($query) => $query->whereNull('location_id')->orWhere('location_id', $location->id))->get();
        $dated = $rules->filter(fn ($rule) => $rule->starts_on && $rule->starts_on->toDateString() <= $date && ($rule->ends_on ?? $rule->starts_on)->toDateString() >= $date);
        if ($dated->contains(fn ($rule) => in_array($rule->kind, ['leave', 'holiday', 'sick_leave'], true) && ! $rule->starts_at)) {
            return [];
        }

        $temporary = $dated->where('kind', 'temporary_change');
        $base = ($temporary->isNotEmpty() ? $temporary : $rules->where('kind', 'working')->where('day_of_week', $day))
            ->sortBy('sequence')->map(fn ($rule) => ['opens_at' => $rule->starts_at, 'closes_at' => $rule->ends_at, 'source' => $rule->kind])->values()->all();
        $unavailable = $rules->filter(function ($rule) use ($dated, $day): bool {
            if (! in_array($rule->kind, ['break', 'personal_block', 'leave', 'sick_leave'], true) || ! $rule->starts_at) {
                return false;
            }

            return ($rule->day_of_week && (int) $rule->day_of_week === $day) || $dated->contains(fn ($datedRule) => $datedRule->is($rule));
        });
        foreach ($unavailable as $block) {
            $base = $this->subtract($base, $block->starts_at, $block->ends_at);
        }

        return array_values($base);
    }

    /** @param list<array{opens_at:string,closes_at:string,source:string}> $windows @return list<array{opens_at:string,closes_at:string,source:string}> */
    private function subtract(array $windows, string $startsAt, string $endsAt): array
    {
        $result = [];
        foreach ($windows as $window) {
            if ($endsAt <= $window['opens_at'] || $startsAt >= $window['closes_at']) {
                $result[] = $window;

                continue;
            }
            if ($startsAt > $window['opens_at']) {
                $result[] = ['opens_at' => $window['opens_at'], 'closes_at' => $startsAt, 'source' => $window['source']];
            }
            if ($endsAt < $window['closes_at']) {
                $result[] = ['opens_at' => $endsAt, 'closes_at' => $window['closes_at'], 'source' => $window['source']];
            }
        }

        return $result;
    }
}
