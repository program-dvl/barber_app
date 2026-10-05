<?php

namespace App\Domain\SchedulingOperations\Services;

use App\Domain\BusinessConfiguration\Services\StaffWorkforceManager;
use App\Domain\PlatformAccess\Enums\PermissionName;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\Membership;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TeamWorkspaceQuery
{
    public function __construct(private readonly CalendarWorkspaceQuery $calendar, private readonly StaffWorkforceManager $schedules) {}

    public function build(Request $request, Business $business, Membership $actor): array
    {
        $manage = $actor->hasPermissionTo(PermissionName::StaffManage->value, 'web');
        $all = $manage || $actor->hasPermissionTo(PermissionName::CalendarViewAll->value, 'web');
        abort_unless($all || ($actor->hasPermissionTo(PermissionName::CalendarViewOwn->value, 'web') && $actor->staffProfile), 403);
        $request->validate(['date' => ['nullable', 'date_format:Y-m-d'], 'location' => ['nullable', 'string'], 'staff' => ['nullable', 'string']]);
        $locations = $business->locations()->where('is_active', true)
            ->when(! $actor->hasRole('owner', 'web'), fn ($q) => $q->whereIn('id', $actor->locations()->pluck('locations.id')))
            ->with(['hours', 'scheduleExceptions'])->orderBy('name')->get();
        abort_unless($manage || $locations->isNotEmpty(), 403);
        $location = $request->filled('location') ? $locations->firstWhere('public_id', $request->input('location')) : $locations->first();
        abort_if($request->filled('location') && ! $location, 404);
        $date = CarbonImmutable::parse($request->input('date') ?: 'today', $location?->time_zone ?: $business->time_zone)->startOfDay();
        $staff = $business->staffProfiles()->when(! $all, fn ($q) => $q->whereKey($actor->staffProfile->id))
            ->when(! $actor->hasRole('owner', 'web'), fn ($q) => $q->whereHas('locations', fn ($q) => $q->whereIn('locations.id', $locations->pluck('id'))))
            ->with(['locations', 'availabilityRules' => fn ($q) => $q->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', $date->startOfWeek()->subDay()->toDateString())),
                'serviceAssignments.service.category', 'serviceAssignments.service.locations', 'membership.business', 'membership.user', 'membership.roles.permissions', 'membership.permissions', 'membership.locations'])
            ->orderBy('display_name')->get();
        // The editor revision includes ALL rules, even historical rules hidden from read-only lists.
        if ($manage) {
            $staff->load('availabilityRules');
        }
        $selected = $staff->filter(fn ($s) => $location && $s->locations->contains('id', $location->id))->values();
        $weekStart = $date->startOfWeek();
        $schedule = $location ? $this->calendar->build($location, $selected, $weekStart, 14) : ['days' => []];
        $overdue = DB::table('appointment_segments as s')->join('appointments as a', 'a.id', '=', 's.appointment_id')
            ->where('s.business_id', $business->id)->where('a.business_id', $business->id)->whereIn('s.staff_profile_id', $staff->pluck('id'))
            ->where('s.occupies_staff', true)->where('a.status', 'in_service')->where('a.ends_at_utc', '<=', now()->utc())->distinct()->pluck('s.staff_profile_id');
        $appointments = $location ? DB::table('appointments as a')->join('appointment_segments as s', 's.appointment_id', '=', 'a.id')
            ->where('a.business_id', $business->id)->where('s.business_id', $business->id)->where('a.location_id', $location->id)
            ->whereIn('s.staff_profile_id', $selected->pluck('id'))->where('s.occupies_staff', true)
            ->whereNotIn('a.status', ['cancelled_by_client', 'cancelled_by_shop', 'no_show', 'rescheduled'])
            ->where('a.starts_at_utc', '<', $weekStart->addDays(14)->utc())->where('a.ends_at_utc', '>', $weekStart->utc())
            ->select(['a.public_id', 'a.client_name', 'a.starts_at_utc', 'a.ends_at_utc', 'a.status', 's.staff_profile_id'])->distinct()
            ->orderBy('a.starts_at_utc')->limit(1001)->get() : collect();
        $calendarAllowed = $actor->hasPermissionTo(PermissionName::CalendarViewAll->value, 'web') || $actor->hasPermissionTo(PermissionName::CalendarViewOwn->value, 'web');
        if (! $calendarAllowed) {
            foreach ($schedule['days'] as &$d) {
                foreach ($d['staff'] as &$member) {
                    foreach ($member['busy'] as &$range) {
                        $range['appointmentId'] = null;
                    }
                    unset($range);
                }
                unset($member);
            }
            unset($d);
        }
        $staffData = $staff->map(function ($person) use ($actor, $manage, $locations, $appointments, $overdue, $calendarAllowed, $date) {
            $editable = $manage && ($actor->hasRole('owner', 'web') || $person->locations->pluck('id')->diff($locations->pluck('id'))->isEmpty());
            $currentAssignments = $person->serviceAssignments->filter(fn ($a) => $a->is_active && (! $a->effective_from || $a->effective_from->lte(now())) && (! $a->effective_until || $a->effective_until->gt(now())))->sortByDesc('effective_from')->unique('service_id');
            $own = $person->membership_id === $actor->id;
            $rules = $this->schedules->rules($person);

            return [
                ...$person->only(['public_id', 'display_name', 'title', 'status', 'online_visible']),
                'contact_visible' => $manage || $own, 'email' => $manage || $own ? $person->email : null, 'mobile' => $manage || $own ? $person->mobile : null,
                'biography' => $person->biography, 'can_edit' => $editable,
                'revision' => $editable ? $this->schedules->revision($person) : null,
                'profile_revision' => $editable ? $this->schedules->profileRevision($person) : null,
                'services_revision' => $editable ? $this->schedules->servicesRevision($person) : null,
                'has_login' => $manage ? ($person->membership?->isActive() ?? false) : null,
                'membership' => $editable && $person->membership ? $this->membership($person->membership, $actor) : null,
                'locations' => $person->locations->whereIn('id', $locations->pluck('id'))->map->only(['public_id', 'name'])->values(),
                'availability' => collect($rules)->filter(fn ($r) => (! $r['ends_on'] || $r['ends_on'] >= $date->startOfWeek()->subDay()->toDateString()) && (! $r['location_id'] || $locations->contains('id', $r['location_id'])))->map(fn ($r) => $editable ? $r : array_diff_key($r, ['reason' => true]))->values(),
                'services' => $currentAssignments->map(fn ($a) => [
                    'public_id' => $a->service?->public_id, 'name' => $a->service?->name, 'category' => $a->service?->category?->name ?: 'Other services',
                    'is_qualified' => $a->is_qualified, 'online_visible' => $a->online_visible,
                    'is_active' => $a->service?->is_active && (! $a->service?->effective_from || $a->service->effective_from->lte(now())) && (! $a->service?->effective_until || $a->service->effective_until->gt(now())),
                    'locations' => $a->service?->locations->filter(fn ($l) => $l->pivot->is_eligible)->pluck('public_id')->all() ?? [],
                    'duration_minutes' => $a->duration_minutes ?? $a->service?->duration_minutes,
                    'processing_minutes' => $a->processing_minutes ?? $a->service?->processing_minutes,
                    'cleanup_minutes' => $a->cleanup_minutes ?? $a->service?->cleanup_minutes,
                    'duration_override' => $manage ? $a->duration_minutes : null, 'price_override' => $manage ? $a->price_minor : null,
                    'price_minor' => $manage ? ($a->price_minor ?? $a->service?->price_minor) : null,
                    'currency_code' => $a->service?->currency_code,
                ])->filter(fn ($s) => $s['public_id'])->values(),
                'appointments' => ($calendarAllowed ? $appointments : collect())->where('staff_profile_id', $person->id)->take(100)->map(fn ($a) => [
                    'id' => $a->public_id, 'client_name' => $a->client_name, 'status' => $a->status,
                    'starts_at' => CarbonImmutable::parse($a->starts_at_utc, 'UTC')->toIso8601String(),
                    'ends_at' => CarbonImmutable::parse($a->ends_at_utc, 'UTC')->toIso8601String(),
                ])->values(),
                'running_over' => $overdue->contains($person->id),
            ];
        });

        return [
            'business' => $business->only(['public_id', 'name', 'country_code', 'time_zone', 'currency_code']),
            'locations' => $locations->map(fn ($l) => [...$l->only(['id', 'public_id', 'name', 'time_zone']), 'hours' => $l->hours->map->only(['day_of_week', 'opens_at', 'closes_at', 'sequence'])->values()]),
            'staff' => $staffData, 'schedule' => ['days' => array_slice($schedule['days'], 0, 7)],
            'upcomingSchedule' => ['days' => array_slice($schedule['days'], (int) $weekStart->diffInDays($date), 4)],
            'filters' => ['date' => $date->toDateString(), 'location' => $location?->public_id, 'staff' => $request->input('staff'), 'view' => in_array($request->input('view'), ['team', 'week', 'leave', 'access'], true) ? $request->input('view') : 'team'],
            'updatedAt' => now()->toIso8601String(), 'incomplete' => $appointments->count() > 1000,
            'can' => ['manage' => $manage, 'calendar' => $calendarAllowed, 'queue' => $actor->hasPermissionTo(PermissionName::WalkInsManage->value, 'web'),
                'reports' => $actor->hasPermissionTo(PermissionName::RevenueView->value, 'web'), 'audit' => $actor->hasPermissionTo(PermissionName::AuditView->value, 'web'),
                'commission_all' => $actor->hasPermissionTo(PermissionName::CommissionsViewAll->value, 'web'), 'commission_own' => $actor->hasPermissionTo(PermissionName::CommissionsViewOwn->value, 'web'), 'own_staff' => $actor->staffProfile?->public_id],
            'accounts' => $manage ? $business->memberships()->whereDoesntHave('staffProfile')->with(['user', 'roles.permissions', 'permissions', 'locations'])
                ->when(! $actor->hasRole('owner', 'web'), fn ($q) => $q->whereHas('locations', fn ($q) => $q->whereIn('locations.id', $locations->pluck('id'))))->get()->filter(fn ($m) => $actor->hasRole('owner', 'web') || $m->locations->pluck('id')->diff($locations->pluck('id'))->isEmpty())->map(fn ($m) => $this->membership($m, $actor))->values() : [],
        ];
    }

    public function membership(Membership $membership, Membership $actor): array
    {
        return ['public_id' => $membership->public_id, 'status' => $membership->status->value, 'user_name' => $membership->user?->name,
            'email' => $membership->user?->email, 'role' => $membership->getRoleNames()->first(),
            'role_label' => ['owner' => 'Owner', 'manager' => 'Manager', 'receptionist' => 'Receptionist', 'barber_stylist' => 'Professional', 'accountant' => 'Accountant'][$membership->getRoleNames()->first()] ?? 'Custom access',
            'permission_names' => $membership->getAllPermissions()->pluck('name')->sort()->values(),
            'location_ids' => $membership->locations->pluck('public_id')->values(), 'is_current' => $membership->id === $actor->id];
    }
}
