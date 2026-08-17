<?php

namespace App\Http\Controllers\Shop;

use App\Domain\BusinessConfiguration\Services\ReadinessEvaluator;
use App\Domain\PlatformAccess\Enums\PermissionName;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\Location;
use App\Domain\Reporting\Services\TodayDashboardService;
use App\Domain\SchedulingOperations\Contracts\CalendarQuery;
use App\Domain\SchedulingOperations\Data\CalendarFilter;
use App\Http\Controllers\Controller;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
        Business $business,
        CalendarQuery $calendar,
        TenantContext $context,
        ReadinessEvaluator $readiness,
        TodayDashboardService $todayDashboard,
    ): Response {
        $membership = $context->membership();
        abort_unless($membership, 403);

        $locations = Location::query()
            ->where('business_id', $business->id)
            ->where('is_active', true)
            ->when(! $membership->hasRole('owner', 'web'), fn ($query) => $query->whereIn('id', $membership->locations()->pluck('locations.id')))
            ->orderBy('name')
            ->get(['id', 'public_id', 'name', 'time_zone']);

        $location = $request->filled('location')
            ? $locations->firstWhere('public_id', $request->string('location')->toString())
            : $locations->first();

        $calendarData = [
            'timeZone' => $business->time_zone ?: config('app.timezone'),
            'currentTime' => now()->toIso8601String(),
            'events' => [],
            'counts' => ['appointments' => 0, 'blocks' => 0, 'walkInsWaiting' => 0, 'unassigned' => 0],
        ];
        $date = CarbonImmutable::today($calendarData['timeZone']);

        if ($location) {
            $date = CarbonImmutable::parse($request->input('date', 'today'), $location->time_zone);
            $canViewAll = $membership->hasPermissionTo(PermissionName::CalendarViewAll->value, 'web');
            $canViewOwn = $membership->hasPermissionTo(PermissionName::CalendarViewOwn->value, 'web');
            $staffIds = $canViewAll ? [] : ($canViewOwn && $membership->staffProfile ? [$membership->staffProfile->id] : null);

            if ($staffIds !== null) {
                $calendarData = $calendar->calendar(new CalendarFilter(
                    $business->id,
                    $location->id,
                    'today',
                    $date,
                    $staffIds,
                ));
            }
        }

        $readinessResult = $readiness->evaluate($business);
        $today = $location ? $todayDashboard->forLocation($business, $membership, $location, $date) : null;

        return Inertia::render('Dashboard', [
            'businessLabel' => $business->name,
            'location' => $location?->only(['public_id', 'name', 'time_zone']),
            'locations' => $locations->map->only(['public_id', 'name', 'time_zone']),
            'date' => $date->toDateString(),
            'calendar' => $calendarData,
            'readiness' => [
                'publishable' => $readinessResult->publishable,
                'blockers' => array_slice($readinessResult->blockers, 0, 3),
                'nextStep' => $readinessResult->nextStep,
            ],
            'todayMetrics' => $today,
            'permissions' => [
                'calendar' => $membership->hasPermissionTo(PermissionName::CalendarViewAll->value, 'web')
                    || $membership->hasPermissionTo(PermissionName::CalendarViewOwn->value, 'web'),
                'createAppointment' => $membership->hasPermissionTo(PermissionName::AppointmentsManageAll->value, 'web')
                    || $membership->hasPermissionTo(PermissionName::AppointmentsManageOwn->value, 'web'),
            ],
        ]);
    }
}
