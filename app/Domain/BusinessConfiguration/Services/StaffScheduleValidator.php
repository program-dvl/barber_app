<?php

namespace App\Domain\BusinessConfiguration\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class StaffScheduleValidator
{
    /** @param list<array<string, mixed>> $rules */
    public function validate(array $rules, ?Collection $locations = null): void
    {
        foreach ($rules as $i => $r) {
            $timed = filled($r['starts_at'] ?? null) || filled($r['ends_at'] ?? null);
            $capacity = in_array($r['kind'] ?? null, ['working', 'temporary_change'], true);
            if (($timed || $capacity) && (empty($r['starts_at']) || empty($r['ends_at']) || $r['starts_at'] >= $r['ends_at'])) {
                $this->fail($i, 'Choose an end time after the start time. Split an overnight shift into separate days.');
            }
            if (($r['kind'] ?? null) === 'working' && empty($r['day_of_week'])) {
                $this->fail($i, 'Regular working hours need a day of the week.');
            }
            if (! $locations) {
                continue;
            }
            $dated = filled($r['starts_on'] ?? null);
            $weekly = filled($r['day_of_week'] ?? null);
            if ($dated === $weekly || (($r['kind'] ?? null) === 'working' && $dated)) {
                $this->fail($i, 'Choose either a weekly day or a dated exception.');
            }
            if (in_array($r['kind'], ['temporary_change', 'leave', 'sick_leave', 'holiday'], true) && ! $dated) {
                $this->fail($i, 'Time off and temporary hours need a date range.');
            }
            if (in_array($r['kind'], ['break', 'personal_block'], true) && ! $timed) {
                $this->fail($i, 'Breaks and personal blocks need a start and end time.');
            }
            if ($capacity && ! ($r['location_id'] ?? null) && $locations->count() > 1) {
                $this->fail($i, 'Choose the branch for these working hours.');
            }
            if ($dated && CarbonImmutable::parse($r['starts_on'])->diffInDays(CarbonImmutable::parse($r['ends_on'] ?? $r['starts_on'])) > 366) {
                $this->fail($i, 'Use date ranges of up to one year.');
            }
        }
        $capacity = array_values(array_filter($rules, fn ($r) => in_array($r['kind'], ['working', 'temporary_change'], true)));
        if ($locations) {
            $this->validateCapacity($capacity, $locations);
        } else {
            foreach ($capacity as $i => $a) {
                foreach (array_slice($capacity, $i + 1) as $b) {
                    if ($this->sameRecurrence($a, $b) && $this->overlaps($a, $b)) {
                        $this->fail($i, ($a['location_id'] ?? null) !== ($b['location_id'] ?? null) ? 'Staff cannot be scheduled at different locations during overlapping times.' : 'Staff working intervals cannot overlap.');
                    }
                }
            }
        }
        $breaks = array_values(array_filter($rules, fn ($r) => in_array($r['kind'], ['break', 'personal_block'], true)));
        foreach ($breaks as $i => $a) {
            foreach (array_slice($breaks, $i + 1) as $b) {
                if (($a['location_id'] ?? null) !== ($b['location_id'] ?? null) && ($a['location_id'] ?? null) && ($b['location_id'] ?? null)) {
                    continue;
                }
                if ($this->sameRecurrence($a, $b) && $this->overlaps($a, $b)) {
                    $this->fail($i, 'Breaks and personal blocks cannot overlap.');
                }
            }
        }
    }

    private function validateCapacity(array $rules, Collection $locations): void
    {
        // Validate a complete offset cycle and every dated exception, in absolute time.
        $dates = [];
        $today = CarbonImmutable::today('UTC');
        for ($d = $today; $d->lte($today->addDays(370)); $d = $d->addDay()) {
            $dates[$d->toDateString()] = $d;
        }
        foreach ($rules as $r) {
            if (! ($r['starts_on'] ?? null)) {
                continue;
            }
            for ($d = CarbonImmutable::parse($r['starts_on'])->subDay(); $d->lte(CarbonImmutable::parse($r['ends_on'] ?? $r['starts_on'])->addDay()); $d = $d->addDay()) {
                $dates[$d->toDateString()] = $d;
            }
        }
        $intervals = [];
        foreach ($dates as $date => $d) {
            foreach ($rules as $r) {
                if (! $this->applies($r, $date, $d->dayOfWeekIso)) {
                    continue;
                }
                $location = $locations->firstWhere('id', $r['location_id'] ?? $locations->first()?->id);
                if (! $location) {
                    continue;
                }
                if ($r['kind'] === 'working' && collect($rules)->contains(fn ($other) => $other['kind'] === 'temporary_change' && ($other['location_id'] ?? null) === ($r['location_id'] ?? null) && $this->applies($other, $date, $d->dayOfWeekIso))) {
                    continue;
                }
                $from = CarbonImmutable::parse($date.' '.$r['starts_at'], $location->time_zone);
                $until = CarbonImmutable::parse($date.' '.$r['ends_at'], $location->time_zone);
                if ($from->format('H:i') !== substr($r['starts_at'], 0, 5) || $until->format('H:i') !== substr($r['ends_at'], 0, 5) || $until->lte($from)) {
                    $this->fail(0, 'These hours cross a clock change. Choose valid local start and end times.');
                }
                $intervals[] = [$from->timestamp, $until->timestamp, $location->id, $location->name.' · '.$date.' '.$r['starts_at'].'–'.$r['ends_at'].' ('.$location->time_zone.')'];
            }
        }
        usort($intervals, fn ($a, $b) => $a[0] <=> $b[0]);
        $last = null;
        foreach ($intervals as $current) {
            if ($last && $current[0] < $last[1]) {
                $this->fail(0, ($current[2] === $last[2] ? 'Working hours overlap: ' : 'Working hours overlap at two branches: ').$last[3].' and '.$current[3].'. Choose distinct intervals.');
            }
            $last = $current;
        }
    }

    private function applies(array $r, string $date, int $day): bool
    {
        return ($r['starts_on'] ?? null) ? $r['starts_on'] <= $date && ($r['ends_on'] ?? $r['starts_on']) >= $date : (int) ($r['day_of_week'] ?? 0) === $day;
    }

    private function sameRecurrence(array $a, array $b): bool
    {
        if (($a['day_of_week'] ?? null) && ($b['day_of_week'] ?? null)) {
            return (int) $a['day_of_week'] === (int) $b['day_of_week'];
        }
        if (($a['starts_on'] ?? null) && ($b['starts_on'] ?? null)) {
            return $a['starts_on'] <= ($b['ends_on'] ?? $b['starts_on']) && $b['starts_on'] <= ($a['ends_on'] ?? $a['starts_on']);
        }
        $dated = ($a['starts_on'] ?? null) ? $a : $b;
        $weekly = ($a['starts_on'] ?? null) ? $b : $a;
        if (! ($dated['starts_on'] ?? null) || ! ($weekly['day_of_week'] ?? null)) {
            return false;
        }
        $from = CarbonImmutable::parse($dated['starts_on']);
        $until = CarbonImmutable::parse($dated['ends_on'] ?? $dated['starts_on']);
        for ($d = $from; $d->lte($until) && $d->lt($from->addDays(7)); $d = $d->addDay()) {
            if ($d->dayOfWeekIso === (int) $weekly['day_of_week']) {
                return true;
            }
        }

        return false;
    }

    private function overlaps(array $a, array $b): bool
    {
        return $a['starts_at'] < $b['ends_at'] && $b['starts_at'] < $a['ends_at'];
    }

    private function fail(int $i, string $message): never
    {
        throw ValidationException::withMessages(['rules' => $message]);
    }
}
