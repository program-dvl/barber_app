<?php

namespace App\Domain\BusinessConfiguration\Services;

use App\Domain\BusinessConfiguration\Models\ConfigurationChangePreview;
use App\Domain\BusinessConfiguration\Models\StaffAvailabilityRule;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\StaffProfile;
use App\Domain\SchedulingOperations\Services\CalendarWorkspaceQuery;
use App\Models\User;
use App\Support\Audit\AuditWriter;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Exact reviewed commands. The staff lock is shared with atomic booking. */
class StaffWorkforceManager
{
    public function __construct(private readonly StaffScheduleValidator $validator, private readonly CalendarWorkspaceQuery $calendar, private readonly AuditWriter $audit) {}

    public function profileRevision(StaffProfile $staff): string
    {
        return hash('sha256', json_encode([$staff->only(['display_name', 'title', 'email', 'mobile', 'biography', 'status', 'online_visible']), $staff->locations->pluck('id')->sort()->values()->all()], JSON_THROW_ON_ERROR));
    }

    public function servicesRevision(StaffProfile $staff): string
    {
        return hash('sha256', json_encode($staff->serviceAssignments->sortBy('id')->map->getAttributes()->values()->all(), JSON_THROW_ON_ERROR));
    }

    public function rules(StaffProfile $staff): array
    {
        return $staff->availabilityRules->sortBy('id')->map(fn ($rule) => [
            'kind' => $rule->kind, 'location_id' => $rule->location_id, 'day_of_week' => $rule->day_of_week,
            'starts_on' => $rule->starts_on?->toDateString(), 'ends_on' => $rule->ends_on?->toDateString(),
            'starts_at' => $rule->starts_at ? substr($rule->starts_at, 0, 5) : null,
            'ends_at' => $rule->ends_at ? substr($rule->ends_at, 0, 5) : null,
            'sequence' => $rule->sequence ?? 1, 'reason' => $rule->reason,
        ])->values()->all();
    }

    public function revision(StaffProfile $staff): string
    {
        return hash('sha256', json_encode([$this->rules($staff), $staff->locations->pluck('id')->sort()->values()->all()], JSON_THROW_ON_ERROR));
    }

    public function validate(StaffProfile $staff, array $rules): array
    {
        $data = Validator::make(['rules' => $rules], [
            'rules' => ['present', 'array', 'max:150'],
            'rules.*.kind' => ['required', Rule::in(['working', 'break', 'leave', 'holiday', 'sick_leave', 'temporary_change', 'personal_block'])],
            'rules.*.location_id' => ['nullable', 'integer', Rule::in($staff->locations->pluck('id')->all())],
            'rules.*.day_of_week' => ['nullable', 'integer', 'between:1,7'],
            'rules.*.starts_on' => ['nullable', 'date_format:Y-m-d'],
            'rules.*.ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:rules.*.starts_on'],
            'rules.*.starts_at' => ['nullable', 'date_format:H:i'], 'rules.*.ends_at' => ['nullable', 'date_format:H:i'],
            'rules.*.sequence' => ['nullable', 'integer', 'between:1,150'], 'rules.*.reason' => ['nullable', 'string', 'max:255'],
        ])->validate()['rules'];
        $normalized = array_map(fn ($r) => [
            'kind' => $r['kind'], 'location_id' => isset($r['location_id']) ? (int) $r['location_id'] : null,
            'day_of_week' => isset($r['day_of_week']) ? (int) $r['day_of_week'] : null,
            'starts_on' => $r['starts_on'] ?? null, 'ends_on' => $r['ends_on'] ?? $r['starts_on'] ?? null,
            'starts_at' => $r['starts_at'] ?? null, 'ends_at' => $r['ends_at'] ?? null,
            'sequence' => (int) ($r['sequence'] ?? 1), 'reason' => filled($r['reason'] ?? null) ? trim($r['reason']) : null,
        ], $data);
        foreach ($normalized as $rule) {
            $zone = $staff->locations->firstWhere('id', $rule['location_id'])?->time_zone ?: $staff->business->time_zone;
            if ($rule['ends_on'] && $rule['ends_on'] < CarbonImmutable::today($zone)->toDateString()) {
                throw ValidationException::withMessages(['rules' => 'Past dated exceptions are preserved as history. Use a current or future end date for new exceptions.']);
            }
        }
        $this->validator->validate($normalized, $staff->locations);

        return $normalized;
    }

    public function preview(StaffProfile $staff, array $rules, string $revision): array
    {
        $staff->loadMissing(['locations.hours', 'locations.scheduleExceptions', 'availabilityRules']);
        $rules = $this->validate($staff, $rules);
        $this->assertRevision($staff, $revision);
        $impact = $this->impact($staff, $rules);
        $preview = ConfigurationChangePreview::query()->create([
            'business_id' => $staff->business_id, 'change_type' => 'workforce_schedule',
            'subject_type' => $staff->getMorphClass(), 'subject_id' => $staff->id,
            'proposed_change' => ['rules' => $rules, 'revision' => $revision, 'impact_signature' => $impact['signature'], 'rules_signature' => hash('sha256', json_encode($rules, JSON_THROW_ON_ERROR))],
            'affected_appointment_ids' => $impact['ids'], 'affected_count' => count($impact['ids']),
            'status' => 'previewed', 'expires_at' => now()->addMinutes(15),
        ]);

        return ['public_id' => $preview->public_id, 'expires_at' => $preview->expires_at->toIso8601String(),
            'affected_count' => count($impact['ids']), 'appointments' => $impact['appointments'],
            'held_count' => $impact['held_count'], 'outside_hours' => $this->outsideHours($staff, $rules)];
    }

    public function save(StaffProfile $staff, array $rules, string $revision, string $previewId, string $reason, User $actor): void
    {
        DB::transaction(function () use ($staff, $rules, $revision, $previewId, $reason, $actor): void {
            // Match booking root order: location before staff. Do not upgrade a held staff lock.
            $staff->locations()->orderBy('locations.id')->sharedLock()->get();
            $staff = StaffProfile::query()->where('business_id', $staff->business_id)->lockForUpdate()->findOrFail($staff->id);
            $staff->load(['locations.hours', 'locations.scheduleExceptions', 'availabilityRules']);
            $rules = $this->validate($staff, $rules);
            $preview = ConfigurationChangePreview::query()->where('business_id', $staff->business_id)
                ->where('subject_type', $staff->getMorphClass())->where('subject_id', $staff->id)
                ->where('change_type', 'workforce_schedule')->where('public_id', $previewId)->lockForUpdate()->first();
            if (! $preview || ($preview->proposed_change['rules_signature'] ?? null) !== hash('sha256', json_encode($rules, JSON_THROW_ON_ERROR)) || ($preview->proposed_change['revision'] ?? null) !== $revision) {
                throw ValidationException::withMessages(['preview' => 'The schedule changed after review. Review its impact again.']);
            }
            // A retry of the already applied exact command creates no extra audit/history.
            if ($preview->status === 'applied') {
                return;
            }
            $this->assertRevision($staff, $revision);
            if ($preview->expires_at->lte(now())) {
                throw ValidationException::withMessages(['preview' => 'This review expired. Review the schedule again.']);
            }
            $impact = $this->impact($staff, $rules);
            if ($impact['ids'] !== $preview->affected_appointment_ids || $impact['signature'] !== ($preview->proposed_change['impact_signature'] ?? null)) {
                throw ValidationException::withMessages(['preview' => 'Appointments changed since review. Review the impact again.']);
            }
            if ($impact['held_count'] > 0) {
                throw ValidationException::withMessages(['preview' => 'A booking is temporarily holding affected time. Wait for it to finish or expire, then review again.']);
            }
            if ($impact['ids'] !== [] && trim($reason) === '') {
                throw ValidationException::withMessages(['impact_reason' => 'Resolve these appointments in Calendar or give a reason to retain them as exceptions.']);
            }
            $before = $this->rules($staff);
            // Preserve expired dated records; the editor changes current/future rules only.
            $zones = $staff->business->locations()->get(['id', 'time_zone'])->keyBy('id');
            $archived = $staff->availabilityRules->filter(fn ($r) => $r->ends_on && $r->ends_on->toDateString() < CarbonImmutable::today($zones->get($r->location_id)?->time_zone ?: $staff->business->time_zone)->toDateString());
            $staff->availabilityRules()->whereNotIn('id', $archived->pluck('id'))->delete();
            foreach ($rules as $rule) {
                StaffAvailabilityRule::query()->create([...$rule, 'business_id' => $staff->business_id, 'staff_profile_id' => $staff->id]);
            }
            $preview->update(['status' => 'applied', 'resolution_note' => $impact['ids'] ? 'retain_exception '.trim($reason) : 'No affected appointments.']);
            $this->audit->write('configuration.staff_availability.updated', Business::query()->findOrFail($staff->business_id), $actor, $staff,
                $reason ?: 'Working schedule reviewed.', ['rules' => $before], ['rules' => $this->rules($staff->fresh(['availabilityRules'])), 'retained_appointments' => $impact['ids']]);
        }, 3);
    }

    private function assertRevision(StaffProfile $staff, string $revision): void
    {
        if (! hash_equals($this->revision($staff), $revision)) {
            throw ValidationException::withMessages(['revision' => 'Another manager changed this schedule. Close and reopen the profile to load the latest version.']);
        }
    }

    /** Check only future occupying segments against changed authoritative windows. */
    private function impact(StaffProfile $staff, array $rules): array
    {
        $segments = DB::table('appointment_segments as s')->join('appointments as a', 'a.id', '=', 's.appointment_id')
            ->where('s.business_id', $staff->business_id)->where('a.business_id', $staff->business_id)
            ->where('s.staff_profile_id', $staff->id)->where('s.occupies_staff', true)
            ->whereIn('a.status', ['pending_confirmation', 'confirmed', 'arrived', 'checked_in', 'in_service', 'late'])
            ->where('s.ends_at_utc', '>', now()->utc())
            ->orderBy('a.public_id')->orderBy('s.starts_at_utc')->orderBy('s.ends_at_utc')->get(['a.public_id', 'a.location_id', 'a.status', 'a.version', 's.starts_at_utc', 's.ends_at_utc']);
        $holds = DB::table('capacity_hold_segments as s')->join('capacity_holds as h', 'h.id', '=', 's.capacity_hold_id')
            ->where('s.business_id', $staff->business_id)->where('h.business_id', $staff->business_id)
            ->where('s.staff_profile_id', $staff->id)->where('s.occupies_staff', true)
            ->where('h.status', 'active')->where('h.expires_at', '>', now()->utc())
            ->get(['h.public_id', 'h.location_id', 's.starts_at_utc', 's.ends_at_utc']);
        $original = $staff->availabilityRules;
        $proposed = collect($rules)->map(fn ($r) => new StaffAvailabilityRule([...$r, 'business_id' => $staff->business_id, 'staff_profile_id' => $staff->id]));
        $cache = [];
        $appointments = [];
        $held = [];
        foreach ([[$segments, false], [$holds, true]] as [$records, $isHold]) {
            foreach ($records as $record) {
                $location = $staff->locations->firstWhere('id', $record->location_id);
                if (! $location) {
                    continue; // An existing detached-branch visit is not changed by these rules.
                }
                $from = CarbonImmutable::parse($record->starts_at_utc, 'UTC');
                $until = CarbonImmutable::parse($record->ends_at_utc, 'UTC');
                $day = $from->setTimezone($location->time_zone)->startOfDay();
                $days = max(1, (int) $day->diffInDays($until->setTimezone($location->time_zone)->startOfDay()) + 1);
                $key = $location->id.':'.$day->toDateString().':'.$days;
                if (! isset($cache[$key])) {
                    $staff->setRelation('availabilityRules', new Collection($proposed->all()));
                    $after = collect($this->calendar->build($location, collect([$staff]), $day, $days, false)['days'])->flatMap(fn ($d) => $d['staff'][0]['windows'])->all();
                    $cache[$key] = $after;
                }
                $after = $cache[$key];
                if (! $this->covered($after, $from, $until)) {
                    if ($isHold) {
                        $held[$record->public_id] = true;
                    } else {
                        $appointments[$record->public_id] = ['id' => $record->public_id, 'location' => $location->public_id,
                            'location_name' => $location->name, 'time_zone' => $location->time_zone, 'date' => $day->toDateString(), 'starts_at' => $from->toIso8601String()];
                    }
                }
            }
        }
        $staff->setRelation('availabilityRules', $original);
        ksort($appointments);

        return ['ids' => array_keys($appointments), 'appointments' => array_values($appointments), 'held_count' => count($held), 'signature' => hash('sha256', json_encode($segments->all(), JSON_THROW_ON_ERROR))];
    }

    private function covered(array $windows, CarbonImmutable $from, CarbonImmutable $until): bool
    {
        foreach (collect($windows)->sortBy('startsAt') as $window) {
            $start = CarbonImmutable::parse($window['startsAt']);
            $end = CarbonImmutable::parse($window['endsAt']);
            if ($start->lte($from) && $end->gt($from)) {
                $from = $end;
            }
            if ($from->gte($until)) {
                return true;
            }
        }

        return false;
    }

    private function outsideHours(StaffProfile $staff, array $rules): array
    {
        $warnings = [];
        foreach ($rules as $r) {
            if (! in_array($r['kind'], ['working', 'temporary_change'], true)) {
                continue;
            }
            foreach ($staff->locations->filter(fn ($l) => ! $r['location_id'] || $l->id === $r['location_id']) as $location) {
                $from = $r['starts_on'] ? CarbonImmutable::parse($r['starts_on'], $location->time_zone) : CarbonImmutable::today($location->time_zone)->startOfWeek()->addDays($r['day_of_week'] - 1);
                $until = $r['ends_on'] ? CarbonImmutable::parse($r['ends_on'], $location->time_zone) : $from;
                for ($date = $from; $date->lte($until); $date = $date->addDay()) {
                    $hours = collect(app(LocalHoursResolver::class)->windows($location, $date));
                    if (! $hours->contains(fn ($h) => substr($h['opens_at'], 0, 5) <= $r['starts_at'] && substr($h['closes_at'], 0, 5) >= $r['ends_at'])) {
                        $warnings[] = $location->name.': '.($r['starts_on'] ? $date->toDateString() : ['', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'][$r['day_of_week']]).' '.$r['starts_at'].'–'.$r['ends_at'];
                    }
                }
            }
        }

        return array_values(array_unique($warnings));
    }
}
