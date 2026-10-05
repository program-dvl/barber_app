<?php

namespace App\Domain\Reporting\Services;

use App\Domain\PlatformAccess\Enums\PermissionName;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\Membership;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ReportService
{
    /** @return list<string> */
    public function allowedReportKeys(Membership $membership): array
    {
        return array_values(array_filter(
            MetricCatalog::reportKeys(),
            fn (string $reportKey): bool => $this->hasReportPermission($membership, $reportKey),
        ));
    }

    public function __construct(private readonly ReportQuery $queries, private readonly ReportAnalysis $analysis, private readonly CapacityReport $capacity) {}

    /** SQL totals and charts cover the full scope; only investigation rows paginate. */
    public function run(Business $business, Membership $membership, string $reportKey, array $filters = []): array
    {
        if (! in_array($reportKey, MetricCatalog::reportKeys(), true)) {
            throw new DomainException('Unknown report.');
        }

        return DB::transaction(function () use ($business, $membership, $reportKey, $filters) {
            $scope = $this->scope($business, $membership, $reportKey, $filters);

            return $this->analysis->build($business, $membership, $reportKey, $scope, $filters);
        });
    }

    /** Stream export records in bounded chunks with the same permissions and filters. */
    public function records(Business $business, Membership $membership, string $reportKey, array $filters): iterable
    {
        $scope = $this->scope($business, $membership, $reportKey, $filters);
        if ($reportKey === 'utilisation') {
            foreach ($this->capacity->rows($business, $scope) as $row) {
                if (! empty($filters['search']) && ! str_contains(mb_strtolower($row['staff']), mb_strtolower($filters['search']))) {
                    continue;
                }
                yield $this->analysis->decorate($business, $membership, $reportKey, $scope, $row);
            }

            return;
        }
        $columns = $this->analysis->reportColumns($reportKey, $scope);
        $query = $this->analysis->filtered($this->queries->build($business, $reportKey, $scope), $filters, $columns);
        $query->orderBy($columns[0]);
        if ($reportKey === 'popular_service') {
            $query->orderBy('service');
        }
        if ($reportKey === 'payroll') {
            $query->orderBy('ledger');
        }
        foreach ($query->lazy(500) as $row) {
            yield $this->analysis->decorate($business, $membership, $reportKey, $scope, (array) $row);
        }
    }

    /** @param array<string,mixed> $filters
     * @return array<string,mixed>
     */
    public function scope(Business $business, Membership $membership, string $reportKey, array $filters): array
    {
        if ((int) $membership->business_id !== (int) $business->id) {
            throw new AccessDeniedHttpException('Report tenant scope does not match.');
        }
        if (! $this->hasReportPermission($membership, $reportKey)) {
            throw new AccessDeniedHttpException('This membership does not include the requested report.');
        }

        $allLocationIds = DB::table('locations')->where('business_id', $business->id)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $allowedLocationIds = $membership->hasRole('owner', 'web') ? $allLocationIds : $membership->locations()->pluck('locations.id')->map(fn ($id) => (int) $id)->all();
        $requestedLocations = array_values(array_map('intval', (array) ($filters['location_ids'] ?? [])));
        if (array_diff($requestedLocations, $allowedLocationIds)) {
            throw new AccessDeniedHttpException('A requested report location is outside membership scope.');
        }
        $locationIds = $requestedLocations ?: $allowedLocationIds;
        if ($locationIds === []) {
            throw new AccessDeniedHttpException('No report location is assigned to this membership.');
        }
        $staffIds = array_values(array_map('intval', (array) ($filters['staff_ids'] ?? [])));
        $ownOnly = (in_array($reportKey, ['appointments', 'cancellation_no_show', 'popular_service', 'utilisation'], true)
            && ! $membership->hasPermissionTo(PermissionName::CalendarViewAll->value, 'web'))
            || (in_array($reportKey, ['commission', 'tip', 'payroll'], true)
                && ! $membership->hasPermissionTo(PermissionName::CommissionsViewAll->value, 'web'));
        if ($ownOnly) {
            $ownStaffId = $membership->staffProfile?->id;
            if (! $ownStaffId || ($staffIds && array_unique($staffIds) !== [$ownStaffId])) {
                throw new AccessDeniedHttpException('This role can report only its own staff record.');
            }
            $staffIds = [$ownStaffId];
        }
        $locationTimeZones = DB::table('locations')->where('business_id', $business->id)->whereIn('id', $locationIds)->pluck('time_zone')->filter()->unique()->values();
        if ($locationTimeZones->count() > 1) {
            throw new DomainException('A report spanning different location time zones must be run one location at a time.');
        }
        $timeZone = (string) ($locationTimeZones->first() ?? $business->time_zone ?? config('app.timezone'));
        $from = CarbonImmutable::parse($filters['start_date'] ?? CarbonImmutable::now($timeZone)->startOfMonth()->toDateString(), $timeZone)->startOfDay();
        $to = CarbonImmutable::parse($filters['end_date'] ?? CarbonImmutable::now($timeZone)->toDateString(), $timeZone)->endOfDay();
        if ($to->lessThan($from) || $from->startOfDay()->diffInDays($to->startOfDay()) > 366) {
            throw new DomainException('Report date range must be ordered and no longer than 367 days.');
        }

        $serviceIds = array_values(array_map('intval', (array) ($filters['service_ids'] ?? [])));
        if ($staffIds && DB::table('staff_profiles')->where('business_id', $business->id)->whereIn('id', $staffIds)->where(function ($staff) use ($locationIds, $business) {
            $staff->whereIn('id', DB::table('location_staff_profile')->where('business_id', $business->id)->whereIn('location_id', $locationIds)->select('staff_profile_id'))
                ->orWhereIn('id', DB::table('sale_lines')->join('sales', 'sales.id', '=', 'sale_lines.sale_id')->where('sales.business_id', $business->id)->whereIn('sales.location_id', $locationIds)->select('sale_lines.staff_profile_id'))
                ->orWhereIn('id', DB::table('appointment_segments')->join('appointments', 'appointments.id', '=', 'appointment_segments.appointment_id')->where('appointments.business_id', $business->id)->whereIn('appointments.location_id', $locationIds)->select('appointment_segments.staff_profile_id'));
        })->count() !== count(array_unique($staffIds))) {
            throw new AccessDeniedHttpException('A requested staff filter is outside tenant scope.');
        }
        if ($serviceIds && DB::table('services')->where('business_id', $business->id)->whereIn('id', $serviceIds)->count() !== count(array_unique($serviceIds))) {
            throw new AccessDeniedHttpException('A requested service filter is outside tenant scope.');
        }

        return ['business_id' => $business->id, 'currency_code' => strtoupper($filters['currency_code'] ?? $business->currency_code), 'allowed_location_ids' => $allowedLocationIds, 'method' => $filters['method'] ?? '', 'can_reasons' => $membership->hasPermissionTo(PermissionName::ClientNotesManage->value, 'web'), 'can_finance' => $membership->hasPermissionTo(PermissionName::RevenueView->value, 'web'), 'time_zone' => $timeZone, 'from' => $from, 'to' => $to, 'from_utc' => $from->utc(), 'to_utc' => $to->utc(), 'until_utc' => $to->addDay()->startOfDay()->utc(), 'location_ids' => $locationIds, 'all_locations' => count($locationIds) === count($allLocationIds) && array_diff($allLocationIds, $locationIds) === [], 'staff_ids' => $staffIds, 'service_ids' => $serviceIds, 'statuses' => array_values(array_filter((array) ($filters['statuses'] ?? []), 'is_string'))];
    }

    private function hasReportPermission(Membership $membership, string $reportKey): bool
    {
        if (in_array($reportKey, ['line_items', 'overview', 'payment_activity', 'tax', 'sales', 'service_revenue', 'staff_revenue', 'payment_method', 'location', 'discount', 'refund', 'client_classification', 'visit_frequency', 'product_sales', 'cash_close'], true)) {
            return $membership->hasPermissionTo(PermissionName::RevenueView->value, 'web');
        }

        if (in_array($reportKey, ['appointments', 'cancellation_no_show', 'popular_service', 'utilisation'], true)) {
            return $membership->hasAnyPermission([
                PermissionName::CalendarViewAll->value,
                PermissionName::CalendarViewOwn->value,
            ]);
        }

        if ($reportKey === 'walk_ins') {
            return $membership->hasPermissionTo(PermissionName::WalkInsManage->value, 'web');
        }

        if ($reportKey === 'stock') {
            return $membership->hasPermissionTo(PermissionName::InventoryManage->value, 'web');
        }

        if (in_array($reportKey, ['commission', 'tip', 'payroll'], true)) {
            return $membership->hasAnyPermission([
                PermissionName::CommissionsViewAll->value,
                PermissionName::CommissionsViewOwn->value,
            ]);
        }

        return false;
    }
}
