<?php

namespace App\Domain\SchedulingOperations\Services;

use App\Domain\BusinessConfiguration\Contracts\AvailabilityConfiguration;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\Location;
use App\Domain\SchedulingOperations\Contracts\AvailabilityQuery;
use App\Domain\SchedulingOperations\Data\AvailabilitySearch;
use App\Domain\SchedulingOperations\Data\BookingRequest;
use App\Domain\SchedulingOperations\Exceptions\BookingRuleViolation;
use Carbon\CarbonImmutable;

class AvailabilitySearchService implements AvailabilityQuery
{
    public function __construct(
        private readonly BookingRuleEngine $rules,
        private readonly AvailabilityConfiguration $configuration,
    ) {}

    public function search(AvailabilitySearch $query): array
    {
        $business = Business::query()->find($query->businessId);
        $location = Location::query()->where('business_id', $query->businessId)->find($query->locationId);
        if (! $business || ! $location || $query->limit < 1 || $query->limit > 200) {
            return [];
        }

        $from = $query->fromLocalDate->setTimezone($location->time_zone)->startOfDay();
        $until = $query->untilLocalDate->setTimezone($location->time_zone)->startOfDay();
        if ($until->lt($from) || $from->diffInDays($until) > 31) {
            return [];
        }

        $interval = max(1, (int) ($business->appointment_interval_minutes ?: 15));
        $cursorUtc = $from->utc();
        $untilUtc = $until->addDay()->utc();
        $slots = [];
        $windowCache = [];
        while ($cursorUtc->lt($untilUtc) && count($slots) < $query->limit) {
            $local = $cursorUtc->setTimezone($location->time_zone);
            if ($local->toDateString() >= $from->toDateString()
                && $local->toDateString() <= $until->toDateString()
                && $this->startsInsideLocationWindow($location, $local, $windowCache)) {
                try {
                    $plan = $this->rules->plan(new BookingRequest(
                        $query->businessId,
                        $query->locationId,
                        $cursorUtc,
                        $query->lines,
                        $query->source,
                        $query->clientEligibility,
                        $query->asOfUtc,
                    ));
                    $slots[] = $plan->toArray();
                } catch (BookingRuleViolation) {
                    // Search is advisory and intentionally omits private schedule
                    // reasons; callers receive only eligible slots.
                }
            }
            $cursorUtc = $cursorUtc->addMinutes($interval);
        }

        return $slots;
    }

    /** @param array<string, list<array{opens_at:string,closes_at:string,source:string}>> $cache */
    private function startsInsideLocationWindow(Location $location, CarbonImmutable $local, array &$cache): bool
    {
        $date = $local->startOfDay();
        foreach ([$date, $date->subDay()] as $windowDate) {
            $key = $windowDate->toDateString();
            $cache[$key] ??= $this->configuration->locationWindows($location, $windowDate);
            foreach ($cache[$key] as $window) {
                $opens = substr($window['opens_at'], 0, 5);
                $closes = substr($window['closes_at'], 0, 5);
                $time = $local->format('H:i');
                if ($opens < $closes && $windowDate->toDateString() === $local->toDateString() && $time >= $opens && $time < $closes) {
                    return true;
                }
                if ($opens >= $closes
                    && (($windowDate->toDateString() === $local->toDateString() && $time >= $opens)
                        || ($windowDate->addDay()->toDateString() === $local->toDateString() && $time < $closes))) {
                    return true;
                }
            }
        }

        return false;
    }
}
