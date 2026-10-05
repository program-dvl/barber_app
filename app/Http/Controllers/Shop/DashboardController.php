<?php

namespace App\Http\Controllers\Shop;

use App\Domain\BusinessConfiguration\Services\ReadinessEvaluator;
use App\Domain\PlatformAccess\Enums\PermissionName;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\Location;
use App\Domain\PlatformAccess\Services\WorkspaceAccessService;
use App\Domain\Reporting\Services\DailyWorkspaceService;
use App\Domain\Reporting\Services\TodayDashboardService;
use App\Domain\SchedulingOperations\Contracts\CalendarQuery;
use App\Domain\SchedulingOperations\Data\CalendarFilter;
use App\Domain\SchedulingOperations\Enums\AppointmentStatus;
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
        DailyWorkspaceService $dailyWorkspace,
        WorkspaceAccessService $access,
    ): Response {
        $request->validate(['date' => ['nullable', 'date_format:Y-m-d'], 'location' => ['nullable', 'string', 'max:26']]);
        $membership = $context->membership();
        abort_unless($membership, 403);

        $locations = Location::query()
            ->where('business_id', $business->id)
            ->where('is_active', true)
            ->when(! $membership->hasRole('owner', 'web'), fn ($query) => $query->whereIn('id', $membership->locations()->pluck('locations.id')))
            ->orderBy('name')
            ->get(['id', 'business_id', 'public_id', 'name', 'time_zone']);

        $location = $request->filled('location')
            ? $locations->firstWhere('public_id', $request->string('location')->toString())
            : $locations->first();

        abort_if($request->filled('location') && ! $location, 404);

        $permissions = [
            'calendar' => $access->decide($business, $membership, 'calendar')['allowed'] && ($membership->hasPermissionTo(PermissionName::CalendarViewAll->value, 'web') || $membership->staffProfile !== null),
            'createAppointment' => $membership->hasPermissionTo(PermissionName::AppointmentsManageAll->value, 'web') || ($membership->hasPermissionTo(PermissionName::AppointmentsManageOwn->value, 'web') && $membership->staffProfile !== null),
            'walkIns' => $access->decide($business, $membership, 'walk-in-queue')['allowed'],
            'checkout' => $access->decide($business, $membership, 'checkout-sales')['allowed'],
            'clients' => $access->decide($business, $membership, 'clients')['allowed'],
            'reports' => $access->decide($business, $membership, 'reports')['allowed'],
            'revenue' => $membership->hasPermissionTo(PermissionName::RevenueView->value, 'web'),
            'setup' => $membership->hasPermissionTo(PermissionName::SettingsManage->value, 'web'),
        ];

        $calendarData = [
            'timeZone' => $business->time_zone ?: config('app.timezone'),
            'currentTime' => now()->toIso8601String(),
            'events' => [],
            'counts' => ['appointments' => 0, 'blocks' => 0, 'walkInsWaiting' => 0, 'unassigned' => 0],
        ];
        $date = CarbonImmutable::today($calendarData['timeZone']);

        if ($location) {
            $date = $request->filled('date') ? CarbonImmutable::createFromFormat('!Y-m-d', $request->input('date'), $location->time_zone) : CarbonImmutable::today($location->time_zone);
            $calendarData['timeZone'] = $location->time_zone;
            $canViewAll = $membership->hasPermissionTo(PermissionName::CalendarViewAll->value, 'web');
            $canViewOwn = $membership->hasPermissionTo(PermissionName::CalendarViewOwn->value, 'web');
            $staffIds = $canViewAll ? [] : ($canViewOwn && $membership->staffProfile ? [$membership->staffProfile->id] : null);

            if ($permissions['calendar'] && $staffIds !== null) {
                $calendarData = $calendar->calendar(new CalendarFilter(
                    $business->id,
                    $location->id,
                    'today',
                    $date,
                    $staffIds,
                    statuses: array_column(AppointmentStatus::cases(), 'value'),
                    limit: 100,
                    prioritizeActive: true,
                ));
            }
        }

        $readinessResult = $permissions['setup'] ? $readiness->evaluate($business) : null;
        // ADR-036 keeps stock/report workflows outside this visible operations release.
        $today = $location ? $todayDashboard->forLocation($business, $membership, $location, $date, includeInventory: false) : null;

        $workspace = $location ? $dailyWorkspace->build($business, $membership, $location, $date, $calendarData, $today, $permissions) : null;
        if ($today) {
            unset($today['team'], $today['hours']);
        }

        return Inertia::render('Dashboard', [
            'businessLabel' => $business->name,
            'location' => $location?->only(['public_id', 'name', 'time_zone']),
            'locations' => $locations->map->only(['public_id', 'name', 'time_zone']),
            'date' => $date->toDateString(),
            'calendar' => ['timeZone' => $calendarData['timeZone'], 'currentTime' => $calendarData['currentTime']],
            'workspace' => $workspace,
            'readiness' => [
                'publishable' => $readinessResult?->publishable ?? true,
                'blockers' => array_slice($readinessResult?->blockers ?? [], 0, 3),
                'nextStep' => $readinessResult?->nextStep,
            ],
            'todayMetrics' => $today,
            'permissions' => $permissions,
        ]);
    }
}
