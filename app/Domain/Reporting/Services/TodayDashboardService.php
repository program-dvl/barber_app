<?php

namespace App\Domain\Reporting\Services;

use App\Domain\PlatformAccess\Enums\PermissionName;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\Location;
use App\Domain\PlatformAccess\Models\Membership;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class TodayDashboardService
{
    /** @return array<string,mixed> */
    public function forLocation(Business $business, Membership $membership, Location $location, CarbonImmutable $date): array
    {
        $start = $date->setTimezone($location->time_zone)->startOfDay()->utc();
        $end = $date->setTimezone($location->time_zone)->endOfDay()->utc();
        $canCalendar = $membership->hasAnyPermission([PermissionName::CalendarViewAll->value, PermissionName::CalendarViewOwn->value]);
        $canFinance = $membership->hasPermissionTo(PermissionName::RevenueView->value, 'web');
        $canInventory = $membership->hasPermissionTo(PermissionName::InventoryManage->value, 'web');
        $ownStaffId = ! $membership->hasPermissionTo(PermissionName::CalendarViewAll->value, 'web') ? $membership->staffProfile?->id : null;

        $appointmentQuery = DB::table('appointments')->where('business_id', $business->id)->where('location_id', $location->id)->whereBetween('starts_at_utc', [$start, $end]);
        if ($ownStaffId) {
            $appointmentQuery->whereExists(fn ($query) => $query->selectRaw('1')->from('appointment_segments')->whereColumn('appointment_segments.appointment_id', 'appointments.id')->where('staff_profile_id', $ownStaffId));
        }
        $appointmentStatuses = $canCalendar ? $appointmentQuery->select('status')->selectRaw('count(*) as total')->groupBy('status')->pluck('total', 'status')->map(fn ($count) => (int) $count)->all() : [];
        $walkIns = $canCalendar ? DB::table('walk_in_entries')->where('business_id', $business->id)->where('location_id', $location->id)->where('status', 'waiting')->count() : null;
        $staffAvailable = $canCalendar ? DB::table('staff_profiles')->join('location_staff_profile', 'location_staff_profile.staff_profile_id', '=', 'staff_profiles.id')->where('staff_profiles.business_id', $business->id)->where('location_staff_profile.location_id', $location->id)->where('staff_profiles.status', 'active')->distinct()->count('staff_profiles.id') : null;

        $sales = DB::table('sales')->where('business_id', $business->id)->where('location_id', $location->id)->whereIn('status', ['open', 'completed'])->whereBetween(DB::raw('coalesce(completed_at, created_at)'), [$start, $end]);
        $financial = $canFinance ? (clone $sales)->selectRaw('coalesce(sum(total_minor),0) as expected_minor, coalesce(sum(paid_minor + deposit_applied_minor - refunded_minor),0) as collected_minor, coalesce(sum(balance_minor),0) as outstanding_minor')->first() : null;
        $newClients = $canFinance ? DB::table('sales as today_sales')->where('today_sales.business_id', $business->id)->where('today_sales.location_id', $location->id)->where('today_sales.status', 'completed')->whereBetween('today_sales.completed_at', [$start, $end])->whereNotNull('today_sales.client_id')->whereNotExists(fn ($query) => $query->selectRaw('1')->from('sales as prior_sales')->whereColumn('prior_sales.client_id', 'today_sales.client_id')->whereColumn('prior_sales.business_id', 'today_sales.business_id')->where('prior_sales.status', 'completed')->whereColumn('prior_sales.completed_at', '<', 'today_sales.completed_at'))->distinct()->count('today_sales.client_id') : null;
        $lowStock = $canInventory ? DB::table('inventory_products')->where('business_id', $business->id)->where('status', 'active')->whereColumn('current_stock', '<=', 'low_stock_threshold')->count() : null;
        $base = "/businesses/{$business->public_id}";

        return [
            'metric_version' => MetricCatalog::VERSION,
            'fresh_at' => now()->utc()->toIso8601String(),
            'time_zone' => $location->time_zone,
            'local_date' => $date->toDateString(),
            'cards' => [
                'appointments' => ['value' => array_sum($appointmentStatuses), 'by_status' => $appointmentStatuses, 'visible' => $canCalendar, 'drill' => "{$base}/app/reports?report=appointments&start_date={$date->toDateString()}&end_date={$date->toDateString()}&location_ids[]={$location->id}"],
                'walk_ins_waiting' => ['value' => $walkIns, 'visible' => $canCalendar, 'drill' => "{$base}/app/walk-in-queue"],
                'staff_available' => ['value' => $staffAvailable, 'visible' => $canCalendar, 'drill' => "{$base}/app/reports?report=utilisation&start_date={$date->toDateString()}&end_date={$date->toDateString()}&location_ids[]={$location->id}"],
                'expected_revenue_minor' => ['value' => $financial ? (int) $financial->expected_minor : null, 'visible' => $canFinance, 'drill' => "{$base}/app/reports?report=sales&statuses[]=open&statuses[]=completed&start_date={$date->toDateString()}&end_date={$date->toDateString()}&location_ids[]={$location->id}"],
                'collected_revenue_minor' => ['value' => $financial ? (int) $financial->collected_minor : null, 'visible' => $canFinance, 'drill' => "{$base}/app/reports?report=payment_method&statuses[]=open&statuses[]=completed&start_date={$date->toDateString()}&end_date={$date->toDateString()}&location_ids[]={$location->id}"],
                'outstanding_minor' => ['value' => $financial ? (int) $financial->outstanding_minor : null, 'visible' => $canFinance, 'drill' => "{$base}/app/reports?report=sales&statuses[]=open&start_date={$date->toDateString()}&end_date={$date->toDateString()}&location_ids[]={$location->id}"],
                'new_clients' => ['value' => $newClients, 'visible' => $canFinance, 'drill' => "{$base}/app/reports?report=client_classification&start_date={$date->toDateString()}&end_date={$date->toDateString()}&location_ids[]={$location->id}"],
                'low_stock' => ['value' => $lowStock, 'visible' => $canInventory, 'drill' => "{$base}/app/reports?report=stock"],
            ],
        ];
    }
}
