<?php

namespace App\Domain\Reporting\Services;

use App\Domain\BusinessConfiguration\Services\LocalHoursResolver;
use App\Domain\BusinessConfiguration\Services\StaffAvailabilityResolver;
use App\Domain\PlatformAccess\Enums\PermissionName;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\Location;
use App\Domain\PlatformAccess\Models\Membership;
use App\Domain\PlatformAccess\Models\StaffProfile;
use App\Domain\PlatformAccess\Services\WorkspaceAccessService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class TodayDashboardService
{
    public function __construct(
        private readonly StaffAvailabilityResolver $availability,
        private readonly LocalHoursResolver $hours,
        private readonly WorkspaceAccessService $access,
    ) {}

    /** @return array<string,mixed> */
    public function forLocation(Business $business, Membership $membership, Location $location, CarbonImmutable $date, bool $includeInventory = true): array
    {
        abort_unless($membership->business_id === $business->id && $location->business_id === $business->id && $membership->isActive(), 403);
        abort_unless($membership->hasRole('owner', 'web') || $membership->locations()->whereKey($location->id)->exists(), 403);
        $start = $date->setTimezone($location->time_zone)->startOfDay()->utc();
        $end = $date->setTimezone($location->time_zone)->endOfDay()->utc();
        $canAll = $membership->hasPermissionTo(PermissionName::CalendarViewAll->value, 'web');
        $ownStaffId = $membership->staffProfile?->id;
        $canCalendar = $canAll || ($membership->hasPermissionTo(PermissionName::CalendarViewOwn->value, 'web') && $ownStaffId !== null);
        $canFinance = $membership->hasPermissionTo(PermissionName::RevenueView->value, 'web');
        $canInventory = $includeInventory && $this->access->decide($business, $membership, 'inventory')['allowed'];

        $appointmentQuery = DB::table('appointments')->where('business_id', $business->id)->where('location_id', $location->id)->whereBetween('starts_at_utc', [$start, $end]);
        if (! $canAll) {
            $appointmentQuery->whereExists(fn ($query) => $query->selectRaw('1')->from('appointment_segments')->whereColumn('appointment_segments.appointment_id', 'appointments.id')->where('staff_profile_id', $ownStaffId));
        }
        $appointmentStatuses = $canCalendar ? $appointmentQuery->select('status')->selectRaw('count(*) as total')->groupBy('status')->pluck('total', 'status')->map(fn ($count) => (int) $count)->all() : [];
        $walkIns = $membership->hasPermissionTo(PermissionName::WalkInsManage->value, 'web') ? DB::table('walk_in_entries')->where('business_id', $business->id)->where('location_id', $location->id)->whereIn('status', ['waiting', 'notified', 'assigned'])->count() : null;
        $openingWindows = $this->hours->windows($location, $date);
        $team = $canCalendar ? StaffProfile::query()->where('business_id', $business->id)->where('status', 'active')
            ->whereHas('locations', fn ($query) => $query->whereKey($location->id))
            ->when(! $canAll, fn ($query) => $query->whereKey($ownStaffId))
            ->with(['locations:id', 'availabilityRules'])->orderBy('display_name')->get()
            ->map(function (StaffProfile $staff) use ($location, $date, $openingWindows): array {
                $windows = [];
                foreach ($this->availability->windows($staff, $location, $date) as $work) {
                    foreach ($openingWindows as $open) {
                        $from = max($work['opens_at'], $open['opens_at']);
                        $to = min($work['closes_at'], $open['closes_at']);
                        if ($from < $to) {
                            $windows[] = ['opens_at' => $from, 'closes_at' => $to];
                        }
                    }
                }

                return ['id' => $staff->public_id, 'name' => $staff->display_name, 'windows' => $windows];
            })->all() : [];
        $staffAvailable = $canCalendar ? count(array_filter($team, fn ($person) => $person['windows'] !== [])) : null;

        $sales = DB::table('sales')->where('business_id', $business->id)->where('location_id', $location->id)->whereIn('status', ['open', 'completed'])->whereBetween(DB::raw('coalesce(completed_at, created_at)'), [$start, $end]);
        $financial = $canFinance ? (clone $sales)->selectRaw('coalesce(sum(total_minor),0) as expected_minor, coalesce(sum(paid_minor + deposit_applied_minor - refunded_minor),0) as collected_minor, coalesce(sum(balance_minor),0) as outstanding_minor')->first() : null;
        $newClients = $canFinance ? DB::table('sales as today_sales')->where('today_sales.business_id', $business->id)->where('today_sales.location_id', $location->id)->where('today_sales.status', 'completed')->whereBetween('today_sales.completed_at', [$start, $end])->whereNotNull('today_sales.client_id')->whereNotExists(fn ($query) => $query->selectRaw('1')->from('sales as prior_sales')->whereColumn('prior_sales.client_id', 'today_sales.client_id')->whereColumn('prior_sales.business_id', 'today_sales.business_id')->where('prior_sales.status', 'completed')->whereColumn('prior_sales.completed_at', '<', 'today_sales.completed_at'))->distinct()->count('today_sales.client_id') : null;
        $lowStock = $canInventory ? DB::table('inventory_products as p')->join('inventory_levels as l', fn ($join) => $join->on('l.inventory_product_id', '=', 'p.id')->on('l.business_id', '=', 'p.business_id'))->where('p.business_id', $business->id)->where('l.location_id', $location->id)->where('p.status', 'active')->whereColumn('l.current_stock', '<=', 'p.low_stock_threshold')->count() : null;
        $base = "/businesses/{$business->public_id}";

        return [
            'metric_version' => MetricCatalog::VERSION,
            'fresh_at' => now()->utc()->toIso8601String(),
            'time_zone' => $location->time_zone,
            'local_date' => $date->toDateString(),
            'team' => $team,
            'hours' => $openingWindows,
            'cards' => [
                'appointments' => ['value' => array_sum($appointmentStatuses), 'by_status' => $appointmentStatuses, 'visible' => $canCalendar, 'drill' => "{$base}/app/reports?report=appointments&start_date={$date->toDateString()}&end_date={$date->toDateString()}&location_ids[]={$location->id}"],
                'walk_ins_waiting' => ['value' => $walkIns, 'visible' => $walkIns !== null, 'drill' => "{$base}/app/walk-in-queue?location={$location->public_id}"],
                'staff_available' => ['value' => $staffAvailable, 'visible' => $canCalendar, 'drill' => "{$base}/app/calendar?view=staff&location={$location->public_id}&date={$date->toDateString()}"],
                'expected_revenue_minor' => ['value' => $financial ? (int) $financial->expected_minor : null, 'visible' => $canFinance, 'drill' => "{$base}/app/reports?report=sales&statuses[]=open&statuses[]=completed&start_date={$date->toDateString()}&end_date={$date->toDateString()}&location_ids[]={$location->id}"],
                'collected_revenue_minor' => ['value' => $financial ? (int) $financial->collected_minor : null, 'visible' => $canFinance, 'drill' => "{$base}/app/reports?report=payment_method&statuses[]=open&statuses[]=completed&start_date={$date->toDateString()}&end_date={$date->toDateString()}&location_ids[]={$location->id}"],
                'outstanding_minor' => ['value' => $financial ? (int) $financial->outstanding_minor : null, 'visible' => $canFinance, 'drill' => "{$base}/app/reports?report=sales&statuses[]=open&start_date={$date->toDateString()}&end_date={$date->toDateString()}&location_ids[]={$location->id}"],
                'new_clients' => ['value' => $newClients, 'visible' => $canFinance, 'drill' => "{$base}/app/reports?report=client_classification&start_date={$date->toDateString()}&end_date={$date->toDateString()}&location_ids[]={$location->id}"],
                'low_stock' => ['value' => $lowStock, 'visible' => $canInventory, 'drill' => "{$base}/app/reports?report=stock&location_ids[]={$location->id}"],
            ],
        ];
    }
}
