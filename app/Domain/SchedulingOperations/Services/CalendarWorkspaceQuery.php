<?php

namespace App\Domain\SchedulingOperations\Services;

use App\Domain\BusinessConfiguration\Contracts\AvailabilityConfiguration;
use App\Domain\ClientRecords\Models\Client;
use App\Domain\PlatformAccess\Enums\PermissionName;
use App\Domain\PlatformAccess\Models\Location;
use App\Domain\PlatformAccess\Models\Membership;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Read-only schedule context. Appointment filters never change capacity. */
class CalendarWorkspaceQuery
{
    public function __construct(private readonly AvailabilityConfiguration $configuration) {}

    public function build(Location $location, Collection $staff, CarbonImmutable $date, int $days, bool $reservations = true): array
    {
        $start = $date->setTimezone($location->time_zone)->startOfDay();
        $end = $start->addDays($days);
        $ids = $staff->pluck('id')->all();
        $location->loadMissing(['hours', 'scheduleExceptions']);
        $staff = new \Illuminate\Database\Eloquent\Collection($staff->all());
        $staff->loadMissing(['locations', 'availabilityRules']);
        $segments = $reservations ? DB::table('appointment_segments as s')->join('appointments as a', 'a.id', '=', 's.appointment_id')
            ->where('s.business_id', $location->business_id)->where('a.business_id', $location->business_id)
            ->whereIn('s.staff_profile_id', $ids)->where('s.occupies_staff', true)
            ->whereIn('a.status', ['pending_confirmation', 'confirmed', 'arrived', 'checked_in', 'in_service', 'late'])
            ->where('s.starts_at_utc', '<', $end->utc())->where('s.ends_at_utc', '>', $start->utc())
            ->get(['s.staff_profile_id', 's.starts_at_utc', 's.ends_at_utc', 'a.public_id', 'a.location_id', 'a.status', 'a.ends_at_utc as appointment_ends_at_utc']) : collect();
        $blocks = $reservations ? DB::table('schedule_blocks')->where('business_id', $location->business_id)->whereIn('staff_profile_id', $ids)
            ->where('starts_at_utc', '<', $end->utc())->where('ends_at_utc', '>', $start->utc())
            ->get(['staff_profile_id', 'starts_at_utc', 'ends_at_utc', 'kind', 'label', 'location_id']) : collect();
        $holds = $reservations ? DB::table('capacity_hold_segments as s')->join('capacity_holds as h', 'h.id', '=', 's.capacity_hold_id')
            ->where('s.business_id', $location->business_id)->where('h.business_id', $location->business_id)
            ->whereIn('s.staff_profile_id', $ids)->where('s.occupies_staff', true)->where('h.status', 'active')->where('h.expires_at', '>', now()->utc())
            ->where('s.starts_at_utc', '<', $end->utc())->where('s.ends_at_utc', '>', $start->utc())
            ->get(['s.staff_profile_id', 's.starts_at_utc', 's.ends_at_utc']) : collect();
        $segments = $segments->groupBy('staff_profile_id');
        $blocks = $blocks->groupBy('staff_profile_id');
        $holds = $holds->groupBy('staff_profile_id');
        $result = [];
        for ($day = $start; $day->lt($end); $day = $day->addDay()) {
            $locationWindows = $this->windows($day, $location, fn ($date) => $this->configuration->locationWindows($location, $date));
            if ($location->scheduleExceptions->contains(fn ($exception) => in_array($exception->kind, ['holiday', 'closure', 'temporary_closure'], true) && $exception->starts_on->toDateString() <= $day->toDateString() && $exception->ends_on->toDateString() >= $day->toDateString())) {
                $locationWindows = [];
            }
            $team = $staff->map(function ($member) use ($day, $location, $locationWindows, $segments, $blocks, $holds) {
                $working = $this->windows($day, $location, fn ($date) => $this->configuration->staffWindows($member, $location, $date));
                $windows = [];
                foreach ($working as $shift) {
                    foreach ($locationWindows as $hours) {
                        $from = CarbonImmutable::parse($shift['startsAt'])->max(CarbonImmutable::parse($hours['startsAt']));
                        $until = CarbonImmutable::parse($shift['endsAt'])->min(CarbonImmutable::parse($hours['endsAt']));
                        if ($from->lt($until)) {
                            $windows[] = ['startsAt' => $from->toIso8601String(), 'endsAt' => $until->toIso8601String()];
                        }
                    }
                }
                $busy = [];
                foreach ($segments->get($member->id, collect()) as $segment) {
                    $range = $this->clip($segment->starts_at_utc, $segment->ends_at_utc, $day);
                    if ($range) {
                        $busy[] = [...$range, 'kind' => 'appointment', 'status' => $segment->status, 'appointmentEndsAt' => CarbonImmutable::parse($segment->appointment_ends_at_utc, 'UTC')->toIso8601String(), 'appointmentId' => $segment->location_id === $location->id ? $segment->public_id : null, 'label' => $segment->location_id === $location->id ? 'Reserved' : 'Working at another location'];
                    }
                }
                foreach ($blocks->get($member->id, collect()) as $block) {
                    $range = $this->clip($block->starts_at_utc, $block->ends_at_utc, $day);
                    if ($range) {
                        $busy[] = [...$range, 'kind' => $block->kind, 'label' => (int) $block->location_id === $location->id ? $block->label : 'Blocked at another location'];
                    }
                }
                foreach ($holds->get($member->id, collect()) as $hold) {
                    $range = $this->clip($hold->starts_at_utc, $hold->ends_at_utc, $day);
                    if ($range) {
                        $busy[] = [...$range, 'kind' => 'hold', 'label' => 'Temporarily reserved'];
                    }
                }
                $unavailable = [];
                foreach ([$day->subDay(), $day] as $ruleDay) {
                    foreach ($member->availabilityRules as $rule) {
                        if ($rule->location_id !== null && (int) $rule->location_id !== $location->id) {
                            continue;
                        }
                        $dated = $rule->starts_on && $rule->starts_on->toDateString() <= $ruleDay->toDateString() && ($rule->ends_on ?? $rule->starts_on)->toDateString() >= $ruleDay->toDateString();
                        $weekly = $rule->day_of_week && (int) $rule->day_of_week === $ruleDay->dayOfWeekIso;
                        if ((! $dated && ! $weekly) || ! in_array($rule->kind, ['break', 'personal_block', 'leave', 'holiday', 'sick_leave'], true)) {
                            continue;
                        }
                        $range = $rule->starts_at ? $this->clockRange($ruleDay, $rule->starts_at, $rule->ends_at) : ['startsAt' => $ruleDay->toIso8601String(), 'endsAt' => $ruleDay->addDay()->toIso8601String()];
                        $clipped = $range ? $this->clip($range['startsAt'], $range['endsAt'], $day) : null;
                        if ($clipped) {
                            $unavailable[] = [...$clipped, 'kind' => $rule->kind, 'label' => match ($rule->kind) {
                                'break' => 'Break', 'leave', 'sick_leave' => 'Time off', 'holiday' => 'Holiday', default => 'Unavailable'
                            }];
                        }
                    }
                }
                $windows = $this->subtract($windows, $unavailable);

                return ['id' => $member->public_id, 'name' => $member->display_name, 'title' => $member->title, 'status' => $member->status, 'windows' => $windows, 'busy' => $busy, 'unavailable' => $unavailable];
            })->values()->all();
            $result[] = ['date' => $day->toDateString(), 'windows' => $locationWindows, 'staff' => $team, 'clockChanges' => $day->offset !== $day->addDay()->offset];
        }

        return ['days' => $result];
    }

    public function clients(Membership $membership, string $search): array
    {
        $contact = $membership->hasPermissionTo(PermissionName::ClientContactView->value, 'web');
        $all = $membership->hasPermissionTo(PermissionName::CalendarViewAll->value, 'web');
        if (! $all && ! $membership->staffProfile) {
            return [];
        }
        $normalized = mb_strtolower(preg_replace('/[^\\pL\\pN]+/u', '', $search) ?: $search);

        return Client::query()->where('business_id', $membership->business_id)->where('status', 'active')
            ->when(! $all, fn ($q) => $q->whereHas('appointments.segments', fn ($q) => $q->where('staff_profile_id', $membership->staffProfile->id)))
            ->where(function ($q) use ($normalized, $contact, $search) {
                $q->where('normalized_name', 'like', '%'.$normalized.'%');
                if ($contact) {
                    $q->orWhere('normalized_mobile', 'like', '%'.$normalized.'%')->orWhere('normalized_email', 'like', '%'.mb_strtolower(trim($search)).'%');
                }
            })->orderBy('name')->limit(15)->get()->map(fn ($client) => [
                'id' => $client->public_id, 'name' => $client->name,
                'mobile' => $contact ? $client->mobile : null, 'email' => $contact ? $client->email : null,
            ])->all();
    }

    private function subtract(array $windows, array $blocked): array
    {
        $ranges = array_map(fn ($window) => [CarbonImmutable::parse($window['startsAt']), CarbonImmutable::parse($window['endsAt'])], $windows);
        foreach ($blocked as $block) {
            $from = CarbonImmutable::parse($block['startsAt']);
            $until = CarbonImmutable::parse($block['endsAt']);
            $ranges = collect($ranges)->flatMap(function ($range) use ($from, $until) {
                if ($until->lte($range[0]) || $from->gte($range[1])) {
                    return [$range];
                }

                return array_values(array_filter([[$range[0], $from->min($range[1])], [$until->max($range[0]), $range[1]]], fn ($piece) => $piece[0]->lt($piece[1])));
            })->all();
        }

        return array_map(fn ($range) => ['startsAt' => $range[0]->toIso8601String(), 'endsAt' => $range[1]->toIso8601String()], $ranges);
    }

    private function windows(CarbonImmutable $day, Location $location, callable $resolve): array
    {
        $result = [];
        foreach ([$day->subDay(), $day] as $date) {
            foreach ($resolve($date) as $window) {
                $range = $this->clockRange($date, $window['opens_at'], $window['closes_at']);
                if ($range && ($clipped = $this->clip($range['startsAt'], $range['endsAt'], $day))) {
                    $result[] = $clipped;
                }
            }
        }

        return $result;
    }

    private function clockRange(CarbonImmutable $date, string $from, ?string $until): ?array
    {
        if (! $until) {
            return null;
        }
        $start = CarbonImmutable::parse($date->toDateString().' '.$from, $date->timezone);
        $end = CarbonImmutable::parse($date->toDateString().' '.$until, $date->timezone);
        if ($end->lte($start)) {
            $end = $end->addDay();
        }
        // Nonexistent daylight-saving wall times must not become bookable ranges.
        if ($start->format('H:i') !== substr($from, 0, 5) || $end->format('H:i') !== substr($until, 0, 5)) {
            return null;
        }

        return ['startsAt' => $start->toIso8601String(), 'endsAt' => $end->toIso8601String()];
    }

    private function clip(string $from, string $until, CarbonImmutable $day): ?array
    {
        $start = CarbonImmutable::parse($from, 'UTC')->setTimezone($day->timezone)->max($day);
        $end = CarbonImmutable::parse($until, 'UTC')->setTimezone($day->timezone)->min($day->addDay());

        return $start->lt($end) ? ['startsAt' => $start->toIso8601String(), 'endsAt' => $end->toIso8601String()] : null;
    }
}
