<?php

namespace App\Http\Controllers\Shop;

use App\Domain\BusinessConfiguration\Models\Service;
use App\Domain\PlatformAccess\Enums\PermissionName;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\Location;
use App\Domain\PlatformAccess\Models\StaffProfile;
use App\Domain\SchedulingOperations\Contracts\CalendarQuery;
use App\Domain\SchedulingOperations\Data\CalendarFilter;
use App\Http\Controllers\Controller;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CalendarController extends Controller
{
    public function __invoke(Request $request, Business $business, CalendarQuery $calendar, TenantContext $context): Response
    {
        $membership = $context->membership();
        abort_unless($membership && ($membership->hasPermissionTo(PermissionName::CalendarViewAll->value, 'web') || $membership->hasPermissionTo(PermissionName::CalendarViewOwn->value, 'web')), 403);

        $locations = Location::query()
            ->where('business_id', $business->id)
            ->where('is_active', true)
            ->when(! $membership->hasRole('owner', 'web'), fn ($query) => $query->whereIn('id', $membership->locations()->pluck('locations.id')))
            ->orderBy('name')
            ->get(['id', 'public_id', 'name', 'time_zone']);
        abort_if($locations->isEmpty(), 403);
        $location = $request->filled('location')
            ? $locations->firstWhere('public_id', $request->string('location')->toString())
            : $locations->first();
        abort_unless($location, 404);

        $staff = StaffProfile::query()
            ->where('business_id', $business->id)
            ->where('status', 'active')
            ->whereHas('locations', fn ($query) => $query->whereKey($location->id))
            ->orderBy('display_name')
            ->get(['id', 'public_id', 'display_name']);
        $service = Service::query()->where('business_id', $business->id)->where('is_active', true)->orderBy('name')->get(['id', 'public_id', 'name', 'minimum_notice_minutes']);
        $staffIds = $staff->whereIn('public_id', (array) $request->input('staff', []))->pluck('id')->all();
        if (! $membership->hasPermissionTo(PermissionName::CalendarViewAll->value, 'web')) {
            abort_unless($membership->staffProfile, 403);
            $staffIds = [$membership->staffProfile->id];
        }
        $serviceIds = $service->whereIn('public_id', (array) $request->input('service', []))->pluck('id')->all();
        $statuses = array_values(array_intersect((array) $request->input('status', []), [
            'pending_confirmation', 'confirmed', 'arrived', 'checked_in', 'in_service',
            'completed', 'cancelled_by_client', 'cancelled_by_shop', 'no_show', 'late', 'rescheduled',
        ]));
        $view = in_array($request->input('view'), ['today', 'day', 'week', 'staff'], true) ? $request->input('view') : 'today';
        $date = CarbonImmutable::parse($request->input('date', 'today'), $location->time_zone);

        return Inertia::render('Operations/Calendar', [
            'businessLabel' => $business->name,
            'calendar' => $calendar->calendar(new CalendarFilter($business->id, $location->id, $view, $date, $staffIds, $serviceIds, $statuses)),
            'filters' => [
                'view' => $view, 'date' => $date->toDateString(), 'location' => $location->public_id,
                'staff' => (array) $request->input('staff', []), 'service' => (array) $request->input('service', []), 'status' => $statuses,
            ],
            'options' => [
                'locations' => $locations->map->only(['public_id', 'name', 'time_zone']),
                'staff' => $staff->map->only(['public_id', 'display_name']),
                'services' => $service->map->only(['public_id', 'name', 'minimum_notice_minutes']),
            ],
            'bookingRules' => [
                'intervalMinutes' => max(1, (int) ($business->appointment_interval_minutes ?: 15)),
                'serverNow' => now()->utc()->toIso8601String(),
            ],
            'permissions' => [
                'manage' => $membership->hasPermissionTo(PermissionName::AppointmentsManageAll->value, 'web') || $membership->hasPermissionTo(PermissionName::AppointmentsManageOwn->value, 'web'),
                'override' => $membership->hasPermissionTo(PermissionName::ScheduleOverride->value, 'web'),
            ],
        ]);
    }
}
