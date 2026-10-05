<?php

namespace App\Domain\Reporting\Services;

use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\Location;
use App\Domain\PlatformAccess\Models\StaffProfile;
use App\Domain\SchedulingOperations\Services\CalendarWorkspaceQuery;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** Reuses effective Calendar windows; intervals are unioned before measuring. */
class CapacityReport
{
    public function __construct(private readonly CalendarWorkspaceQuery $calendar) {}

    public function rows(Business $business, array $scope): array
    {
        $staff = StaffProfile::query()->forBusiness($business)->where(function ($q) use ($scope) {
            $q->whereHas('locations', fn ($locations) => $locations->whereIn('locations.id', $scope['location_ids']))
                ->orWhereIn('id', DB::table('appointment_segments as s')->join('appointments as a', 'a.id', '=', 's.appointment_id')->where('s.business_id', $businessId = $scope['business_id'])->where('a.business_id', $businessId)->whereIn('a.location_id', $scope['location_ids'])->where('s.starts_at_utc', '<', $scope['until_utc'])->where('s.ends_at_utc', '>', $scope['from_utc'])->select('s.staff_profile_id'));
        })->when($scope['staff_ids'], fn ($q) => $q->whereIn('id', $scope['staff_ids']))->with(['locations', 'availabilityRules'])->orderBy('display_name')->get();
        $ids = $staff->pluck('id')->all();
        $locations = Location::query()->forBusiness($business)->whereIn('id', $scope['location_ids'])->with(['hours', 'scheduleExceptions'])->get();
        $inside = [];
        $outside = [];
        $blocks = [];
        $segments = DB::table('appointment_segments as s')->join('appointments as a', 'a.id', '=', 's.appointment_id')->where('s.business_id', $business->id)->where('a.business_id', $business->id)->whereIn('s.staff_profile_id', $ids)->where('s.occupies_staff', true)->whereNotIn('a.status', ['cancelled_by_client', 'cancelled_by_shop', 'no_show', 'rescheduled'])->where('s.starts_at_utc', '<', $scope['until_utc'])->where('s.ends_at_utc', '>', $scope['from_utc'])->select(['s.staff_profile_id', 's.starts_at_utc', 's.ends_at_utc', 'a.location_id'])->orderBy('s.starts_at_utc')->orderBy('s.id');
        // Merge sorted numeric intervals as each bounded batch is read. No
        // client fields or complete appointment objects are retained.
        foreach ($segments->lazy(500) as $segment) {
            $range = $this->range($segment->starts_at_utc, $segment->ends_at_utc);
            $range = [max($range[0], $scope['from_utc']->timestamp), min($range[1], $scope['until_utc']->timestamp)];
            if (in_array((int) $segment->location_id, $scope['location_ids'], true)) {
                $this->append($inside[$segment->staff_profile_id], $range);
            } else {
                $this->append($outside[$segment->staff_profile_id], $range);
            }
        }
        $blockQuery = DB::table('schedule_blocks')->where('business_id', $business->id)->whereIn('staff_profile_id', $ids)->where('starts_at_utc', '<', $scope['until_utc'])->where('ends_at_utc', '>', $scope['from_utc'])->select(['staff_profile_id', 'starts_at_utc', 'ends_at_utc'])->orderBy('starts_at_utc')->orderBy('id');
        foreach ($blockQuery->lazy(500) as $block) {
            $this->append($blocks[$block->staff_profile_id], $this->range($block->starts_at_utc, $block->ends_at_utc));
        }
        $windows = [];
        // Configuration is eager loaded once. Weekly chunks bound projection
        // memory and avoid SQL queries per member or date.
        foreach ($locations as $location) {
            for ($day = $scope['from']; $day->lte($scope['to']); $day = $day->addDays(7)) {
                $days = min(7, (int) $day->startOfDay()->diffInDays($scope['to']->startOfDay()) + 1);
                foreach ($this->calendar->build($location, $staff, $day, $days, false)['days'] as $projection) {
                    foreach ($projection['staff'] as $person) {
                        foreach ($person['windows'] as $window) {
                            $windows[$person['id']][] = $this->range($window['startsAt'], $window['endsAt']);
                        }
                    }
                }
            }
        }

        return $staff->map(function ($person) use ($windows, $inside, $outside, $blocks) {
            $available = $this->union($windows[$person->public_id] ?? []);
            // Unselected branch occupancy reduces capacity without exposing its records.
            $available = $this->subtract($available, [...($blocks[$person->id] ?? []), ...($outside[$person->id] ?? [])]);
            $booked = $inside[$person->id] ?? [];
            $occupied = $this->intersect($available, $booked);
            $recordedMinutes = $this->minutes($booked);
            $availableMinutes = $this->minutes($available);
            $bookedMinutes = $this->minutes($this->union($occupied));

            return ['staff_id' => $person->id, 'staff' => $person->display_name, 'staff_public_id' => $person->public_id, 'available_minutes' => $availableMinutes, 'recorded_minutes' => $recordedMinutes, 'outside_windows_minutes' => max(0, $recordedMinutes - $bookedMinutes), 'booked_minutes' => $bookedMinutes, 'free_minutes' => max(0, $availableMinutes - $bookedMinutes), 'utilisation_percent' => $availableMinutes > 0 ? round($bookedMinutes * 100 / $availableMinutes, 1) : null, 'schedule_status' => $person->status === 'active' ? 'Configured windows' : 'Historical availability unavailable'];
        })->all();
    }

    /** Append one interval from a stream already ordered by start. */
    private function append(?array &$ranges, array $range): void
    {
        $ranges ??= [];
        [$start, $end] = $range;
        if ($end <= $start) {
            return;
        }
        $last = count($ranges) - 1;
        if ($last >= 0 && $start <= $ranges[$last][1]) {
            $ranges[$last][1] = max($end, $ranges[$last][1]);
        } else {
            $ranges[] = [$start, $end];
        }
    }

    /** Linear intersection of two sorted, unioned interval lists. */
    private function intersect(array $windows, array $booked): array
    {
        $out = [];
        $i = 0;
        $j = 0;
        while ($i < count($windows) && $j < count($booked)) {
            [$a, $b] = $windows[$i];
            [$c, $d] = $booked[$j];
            if (max($a, $c) < min($b, $d)) {
                $out[] = [max($a, $c), min($b, $d)];
            }
            if ($b <= $d) {
                $i++;
            } else {
                $j++;
            }
        }

        return $out;
    }

    private function range(string $from, string $until): array
    {
        return [CarbonImmutable::parse($from, 'UTC')->timestamp, CarbonImmutable::parse($until, 'UTC')->timestamp];
    }

    private function minutes(array $ranges): int
    {
        return (int) round(array_sum(array_map(fn ($range) => $range[1] - $range[0], $ranges)) / 60);
    }

    private function union(array $ranges): array
    {
        usort($ranges, fn ($a, $b) => $a[0] <=> $b[0]);
        $out = [];
        foreach ($ranges as [$start, $end]) {
            if ($end <= $start) {
                continue;
            }
            $last = count($out) - 1;
            if ($last >= 0 && $start <= $out[$last][1]) {
                $out[$last][1] = max($end, $out[$last][1]);
            } else {
                $out[] = [$start, $end];
            }
        }

        return $out;
    }

    private function subtract(array $windows, array $blocks): array
    {
        foreach ($this->union($blocks) as [$from, $until]) {
            $pieces = [];
            foreach ($windows as [$start, $end]) {
                if ($until <= $start || $from >= $end) {
                    $pieces[] = [$start, $end];
                } else {
                    if ($start < $from) {
                        $pieces[] = [$start, min($from, $end)];
                    }
                    if ($until < $end) {
                        $pieces[] = [max($until, $start), $end];
                    }
                }
            }
            $windows = $pieces;
        }

        return $windows;
    }
}
