<?php

namespace App\Domain\SchedulingOperations\Services;

use App\Domain\PlatformAccess\Models\Location;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Advisory staff forecast. Never reserves capacity or replaces BookingRuleEngine. */
class WalkInWorkspaceQuery
{
    public function build(Location $location, Collection $staff, Collection $services, Collection $entries, array $schedule, CarbonImmutable $now, int $interval): array
    {
        $members = collect($schedule['days'][0]['staff'] ?? [])->keyBy('id');
        // Live visits can outlast the projected day; do not infer that their staff are free.
        $overdueStaff = DB::table('appointment_segments as s')->join('appointments as a', 'a.id', '=', 's.appointment_id')
            ->where('a.business_id', $location->business_id)->where('s.business_id', $location->business_id)
            ->where('a.status', 'in_service')->where('a.ends_at_utc', '<=', $now)
            ->where('s.occupies_staff', true)->whereIn('s.staff_profile_id', $staff->pluck('id'))
            ->distinct()->pluck('s.staff_profile_id')->flip();
        $gaps = [];
        $team = [];
        foreach ($staff as $member) {
            $day = $members->get($member->public_id, ['windows' => [], 'busy' => [], 'unavailable' => []]);
            $busy = $day['busy'];
            // An overdue live service has no trustworthy finish time.
            $overdue = $overdueStaff->has($member->id) || collect($busy)->contains(fn ($range) => ($range['status'] ?? null) === 'in_service' && CarbonImmutable::parse($range['appointmentEndsAt'] ?? $range['endsAt'])->lte($now)) || $entries->contains(fn ($entry) => $entry->status === 'in_service' && $entry->assigned_staff_profile_id === $member->id && (! $entry->appointment || $entry->appointment->ends_at_utc->lte($now)));
            if ($overdue) {
                $busy[] = ['startsAt' => $now->toIso8601String(), 'endsAt' => $now->setTimezone($location->time_zone)->addDay()->startOfDay()->toIso8601String(), 'label' => 'Service running over'];
            }
            $gaps[$member->public_id] = $this->gaps($day['windows'], $busy, $now);
            $current = collect([...$day['unavailable'], ...$busy])->first(fn ($range) => CarbonImmutable::parse($range['startsAt'])->lte($now) && CarbonImmutable::parse($range['endsAt'])->gt($now));
            $gap = $gaps[$member->public_id][0] ?? null;
            $available = $gap && $gap[0]->lte($now);
            $nextBooking = collect($day['busy'])->where('kind', 'appointment')->filter(fn ($range) => CarbonImmutable::parse($range['startsAt'])->gt($now))->sortBy('startsAt')->first();
            $team[] = [
                'public_id' => $member->public_id, 'display_name' => $member->display_name,
                'state' => $overdue ? 'Service running over' : ((($current['status'] ?? null) === 'in_service' ? 'In service' : ($current['label'] ?? null)) ?? ($available ? 'Available now' : ($gap ? 'Available later' : 'Not working'))),
                'available_now' => (bool) $available, 'available_at' => $overdue ? null : ($gap[0] ?? null)?->toIso8601String(),
                'gap_minutes' => $available ? (int) $now->diffInMinutes($gap[1]) : null,
                'next_booking_at' => $nextBooking['startsAt'] ?? null,
            ];
        }
        $forecast = [];
        foreach ($entries->whereIn('status', ['waiting', 'notified', 'assigned'])->sortBy('queue_position') as $entry) {
            $service = $services->firstWhere('id', $entry->service_id);
            $choices = [];
            foreach ($staff as $member) {
                $assignment = $service?->staffAssignments->filter(fn ($a) => $a->staff_profile_id === $member->id && $a->is_active && $a->is_qualified && (! $a->effective_from || $a->effective_from->lte($now)) && (! $a->effective_until || $a->effective_until->gt($now)))->sortByDesc('effective_from')->first();
                $eligible = $service && $service->is_active && (! $service->effective_from || $service->effective_from->lte($now)) && (! $service->effective_until || $service->effective_until->gt($now)) && $service->locations->contains(fn ($l) => $l->id === $location->id && $l->pivot->is_eligible);
                $kinds = $service?->segments->pluck('kind')->all() ?? [];
                $duration = $assignment ? (int) (
                    (($kinds === [] || in_array('active', $kinds, true)) ? ($assignment->duration_minutes ?? $service->duration_minutes) : 0)
                    + (($kinds === [] || in_array('processing', $kinds, true)) ? ($assignment->processing_minutes ?? $service->processing_minutes) : 0)
                    + (($kinds === [] || in_array('cleanup', $kinds, true)) ? ($assignment->cleanup_minutes ?? $service->cleanup_minutes) : 0)
                ) : null;
                $at = null;
                if ($eligible && $duration > 0) {
                    foreach ($gaps[$member->public_id] as [$from, $until]) {
                        $candidate = $this->align($from->max($now), $location->time_zone, $interval);
                        if ($candidate->addMinutes($duration)->lte($until) && (! $assignment->effective_until || $candidate->addMinutes($duration)->lt($assignment->effective_until)) && (! $service->effective_until || $candidate->addMinutes($duration)->lt($service->effective_until))) {
                            $at = $candidate;
                            break;
                        }
                    }
                }
                $choices[] = ['staff_id' => $member->public_id, 'name' => $member->display_name, 'qualified' => (bool) ($eligible && $assignment), 'duration_minutes' => $duration, 'available_at' => $at?->toIso8601String()];
            }
            $required = $entry->assigned_staff_profile_id ?: $entry->preferred_staff_profile_id;
            $requiredPublic = $required ? $staff->firstWhere('id', $required)?->public_id : null;
            $choice = collect($choices)->filter(fn ($c) => $c['available_at'] && (! $required || $c['staff_id'] === $requiredPublic))->sortBy('available_at')->first();
            $forecast[$entry->public_id] = ['estimated_at' => $choice['available_at'] ?? null, 'suggested_staff_id' => $choice['staff_id'] ?? null, 'choices' => $choices];
            if ($choice) {
                $from = CarbonImmutable::parse($choice['available_at']);
                $gaps[$choice['staff_id']] = $this->gaps(array_map(fn ($r) => ['startsAt' => $r[0]->toIso8601String(), 'endsAt' => $r[1]->toIso8601String()], $gaps[$choice['staff_id']]), [['startsAt' => $from->toIso8601String(), 'endsAt' => $from->addMinutes($choice['duration_minutes'])->toIso8601String()]], $now);
            }
        }

        return ['team' => $team, 'forecast' => $forecast, 'updated_at' => $now->toIso8601String()];
    }

    public function align(CarbonImmutable $from, string $zone, int $interval): CarbonImmutable
    {
        $local = $from->setTimezone($zone);
        $minute = $local->hour * 60 + $local->minute;
        $increment = ($interval - $minute % $interval) % $interval;
        if ($increment === 0 && ($local->second > 0 || $local->micro > 0)) {
            $increment = $interval;
        }

        return $local->startOfMinute()->addMinutes($increment)->utc();
    }

    private function gaps(array $windows, array $busy, CarbonImmutable $now): array
    {
        $ranges = array_map(fn ($r) => [CarbonImmutable::parse($r['startsAt'])->max($now), CarbonImmutable::parse($r['endsAt'])], $windows);
        foreach ($busy as $block) {
            $from = CarbonImmutable::parse($block['startsAt']);
            $until = CarbonImmutable::parse($block['endsAt']);
            $ranges = collect($ranges)->flatMap(fn ($r) => $until->lte($r[0]) || $from->gte($r[1]) ? [$r] : [[$r[0], $from->min($r[1])], [$until->max($r[0]), $r[1]]])->all();
        }

        return collect($ranges)->filter(fn ($r) => $r[0]->lt($r[1]))->sortBy(fn ($r) => $r[0]->timestamp)->values()->all();
    }
}
