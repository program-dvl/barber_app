<?php

namespace App\Domain\Reporting\Services;

use App\Domain\PlatformAccess\Enums\PermissionName;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\Membership;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ReportService
{
    /** @param array<string,mixed> $filters
     * @return array<string,mixed>
     */
    public function run(Business $business, Membership $membership, string $reportKey, array $filters = []): array
    {
        if (! in_array($reportKey, MetricCatalog::reportKeys(), true)) {
            throw new DomainException('Unknown Phase 1 report.');
        }
        $scope = $this->scope($business, $membership, $reportKey, $filters);
        $result = $this->query($business, $reportKey, $scope);
        $previous = null;
        if (! in_array($reportKey, ['stock', 'utilisation'], true)) {
            $days = $scope['from']->diffInDays($scope['to']) + 1;
            $previousScope = $scope;
            $previousScope['to'] = $scope['from']->subDay()->endOfDay();
            $previousScope['from'] = $previousScope['to']->subDays($days - 1)->startOfDay();
            $previousResult = $this->query($business, $reportKey, $previousScope);
            $previous = ['from' => $previousScope['from']->toDateString(), 'to' => $previousScope['to']->toDateString(), 'totals' => $previousResult['totals']];
        }

        return [
            'report_key' => $reportKey,
            'metric_version' => MetricCatalog::VERSION,
            'effective_from' => MetricCatalog::EFFECTIVE_FROM,
            'fresh_at' => now()->utc()->toIso8601String(),
            'time_zone' => $scope['time_zone'],
            'filters' => ['start_date' => $scope['from']->toDateString(), 'end_date' => $scope['to']->toDateString(), 'location_ids' => $scope['location_ids'], 'staff_ids' => $scope['staff_ids'], 'service_ids' => $scope['service_ids'], 'statuses' => $scope['statuses']],
            'source' => $result['source'],
            'columns' => $result['rows'] === [] ? [] : array_keys($result['rows'][0]),
            'rows' => $result['rows'],
            'totals' => $result['totals'],
            'previous_period' => $previous,
        ];
    }

    /** @param array<string,mixed> $filters
     * @return array<string,mixed>
     */
    public function scope(Business $business, Membership $membership, string $reportKey, array $filters): array
    {
        if ((int) $membership->business_id !== (int) $business->id) {
            throw new AccessDeniedHttpException('Report tenant scope does not match.');
        }
        $financial = ['sales', 'service_revenue', 'staff_revenue', 'payment_method', 'location', 'discount', 'refund', 'client_classification', 'visit_frequency', 'product_sales', 'cash_close'];
        if (in_array($reportKey, $financial, true) && ! $membership->hasPermissionTo(PermissionName::RevenueView->value, 'web')) {
            throw new AccessDeniedHttpException('Financial report permission is required.');
        }
        if (in_array($reportKey, ['appointments', 'cancellation_no_show', 'popular_service', 'utilisation'], true)
            && ! $membership->hasAnyPermission([PermissionName::CalendarViewAll->value, PermissionName::CalendarViewOwn->value])) {
            throw new AccessDeniedHttpException('Calendar report permission is required.');
        }
        if ($reportKey === 'stock' && ! $membership->hasPermissionTo(PermissionName::InventoryManage->value, 'web')) {
            throw new AccessDeniedHttpException('Inventory permission is required.');
        }
        if (in_array($reportKey, ['commission', 'tip', 'payroll'], true)
            && ! $membership->hasAnyPermission([PermissionName::CommissionsViewAll->value, PermissionName::CommissionsViewOwn->value])) {
            throw new AccessDeniedHttpException('Commission permission is required.');
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
        $ownOnly = $membership->hasPermissionTo(PermissionName::CommissionsViewOwn->value, 'web')
            && ! $membership->hasAnyPermission([PermissionName::CommissionsViewAll->value, PermissionName::CalendarViewAll->value, PermissionName::RevenueView->value]);
        if ($ownOnly) {
            $ownStaffId = $membership->staffProfile?->id;
            if (! $ownStaffId || ($staffIds && $staffIds !== [$ownStaffId])) {
                throw new AccessDeniedHttpException('This role can report only its own staff record.');
            }
            $staffIds = [$ownStaffId];
        }
        $locationTimeZones = DB::table('locations')->where('business_id', $business->id)->whereIn('id', $locationIds)->pluck('time_zone')->filter()->unique()->values();
        if ($locationTimeZones->count() > 1) {
            throw new DomainException('A report spanning different location time zones must be run one location at a time.');
        }
        $timeZone = (string) ($locationTimeZones->first() ?? $business->time_zone ?? config('app.timezone'));
        $from = CarbonImmutable::parse($filters['start_date'] ?? 'today', $timeZone)->startOfDay();
        $to = CarbonImmutable::parse($filters['end_date'] ?? $from->toDateString(), $timeZone)->endOfDay();
        if ($to->lessThan($from) || $from->diffInDays($to) > 366) {
            throw new DomainException('Report date range must be ordered and no longer than 367 days.');
        }

        $serviceIds = array_values(array_map('intval', (array) ($filters['service_ids'] ?? [])));
        if ($staffIds && DB::table('staff_profiles')->where('business_id', $business->id)->whereIn('id', $staffIds)->count() !== count(array_unique($staffIds))) {
            throw new AccessDeniedHttpException('A requested staff filter is outside tenant scope.');
        }
        if ($serviceIds && DB::table('services')->where('business_id', $business->id)->whereIn('id', $serviceIds)->count() !== count(array_unique($serviceIds))) {
            throw new AccessDeniedHttpException('A requested service filter is outside tenant scope.');
        }

        return ['time_zone' => $timeZone, 'from' => $from, 'to' => $to, 'from_utc' => $from->utc(), 'to_utc' => $to->utc(), 'location_ids' => $locationIds, 'all_locations' => count($locationIds) === count($allLocationIds) && array_diff($allLocationIds, $locationIds) === [], 'staff_ids' => $staffIds, 'service_ids' => $serviceIds, 'statuses' => array_values(array_filter((array) ($filters['statuses'] ?? []), 'is_string'))];
    }

    /** @param array<string,mixed> $scope
     * @return array{source:string,rows:list<array<string,mixed>>,totals:array<string,int|float>}
     */
    private function query(Business $business, string $key, array $scope): array
    {
        $rows = match ($key) {
            'appointments' => $this->appointments($business, $scope),
            'sales' => $this->sales($business, $scope),
            'service_revenue', 'popular_service' => $this->serviceLines($business, $scope, $key === 'popular_service'),
            'staff_revenue' => $this->staffRevenue($business, $scope),
            'payment_method' => $this->payments($business, $scope, true),
            'location' => $this->locations($business, $scope),
            'discount' => $this->discounts($business, $scope),
            'refund' => $this->payments($business, $scope, false),
            'tip' => $this->ledgerEntries($business, $scope, 'tip_entries'),
            'commission' => $this->ledgerEntries($business, $scope, 'commission_entries'),
            'payroll' => collect($this->ledgerEntries($business, $scope, 'commission_entries'))->concat($this->ledgerEntries($business, $scope, 'tip_entries'))->sortBy('occurred_at')->values()->all(),
            'client_classification' => $this->clientClassification($business, $scope),
            'cancellation_no_show' => $this->cancellations($business, $scope),
            'utilisation' => $this->utilisation($business, $scope),
            'visit_frequency' => $this->visitFrequency($business, $scope),
            'product_sales' => $this->productSales($business, $scope),
            'stock' => $this->stock($business, $scope),
            'cash_close' => $this->cashCloses($business, $scope),
        };
        $rows = array_map(fn (array $row): array => collect($row)->map(function ($value, string $column) {
            if (! is_numeric($value)) {
                return $value;
            }

            return str_contains((string) $value, '.') || str_ends_with($column, '_percent') ? (float) $value : (int) $value;
        })->all(), $rows);

        return ['source' => $this->sourceFor($key), 'rows' => $rows, 'totals' => $this->totals($rows)];
    }

    private function baseSales(Business $business, array $scope): Builder
    {
        $query = DB::table('sales')->where('sales.business_id', $business->id)
            ->whereBetween(DB::raw('coalesce(sales.completed_at, sales.created_at)'), [$scope['from_utc'], $scope['to_utc']])->whereIn('sales.location_id', $scope['location_ids']);
        if ($scope['statuses']) {
            $query->whereIn('sales.status', $scope['statuses']);
        } else {
            $query->where('sales.status', 'completed');
        }
        if ($scope['staff_ids']) {
            $query->whereExists(fn ($q) => $q->selectRaw('1')->from('sale_lines')->whereColumn('sale_lines.sale_id', 'sales.id')->whereIn('sale_lines.staff_profile_id', $scope['staff_ids']));
        }
        if ($scope['service_ids']) {
            $query->whereExists(fn ($q) => $q->selectRaw('1')->from('sale_lines')->whereColumn('sale_lines.sale_id', 'sales.id')->where('sale_lines.kind', 'service')->whereIn('sale_lines.service_id', $scope['service_ids']));
        }

        return $query;
    }

    private function appointments(Business $business, array $scope): array
    {
        $query = DB::table('appointments')->where('appointments.business_id', $business->id)->whereBetween('starts_at_utc', [$scope['from_utc'], $scope['to_utc']])->whereIn('location_id', $scope['location_ids']);
        if ($scope['statuses']) {
            $query->whereIn('status', $scope['statuses']);
        }
        if ($scope['staff_ids']) {
            $query->whereExists(fn ($q) => $q->selectRaw('1')->from('appointment_segments')->whereColumn('appointment_segments.appointment_id', 'appointments.id')->whereIn('staff_profile_id', $scope['staff_ids']));
        }
        if ($scope['service_ids']) {
            $query->whereExists(fn ($q) => $q->selectRaw('1')->from('appointment_service_lines')->whereColumn('appointment_service_lines.appointment_id', 'appointments.id')->whereIn('service_id', $scope['service_ids']));
        }

        return $query->orderBy('starts_at_utc')->get(['id', 'public_id', 'location_id', 'status', 'source', 'starts_at_utc', 'ends_at_utc', 'price_minor'])->map(fn ($row) => ['source_id' => $row->id, 'appointment' => $row->public_id, 'location_id' => $row->location_id, 'status' => $row->status, 'source' => $row->source, 'starts_at' => $row->starts_at_utc, 'ends_at' => $row->ends_at_utc, 'expected_minor' => $row->price_minor, 'count' => 1, 'drill' => "/businesses/{$business->public_id}/app/calendar?appointment={$row->public_id}"])->all();
    }

    private function sales(Business $business, array $scope): array
    {
        return $this->baseSales($business, $scope)->orderBy('completed_at')->get(['id', 'public_id', 'location_id', 'appointment_id', 'client_id', 'subtotal_minor', 'discount_minor', 'tax_minor', 'tip_minor', 'total_minor', 'deposit_applied_minor', 'paid_minor', 'refunded_minor', 'balance_minor', 'completed_at'])->map(fn ($row) => ['source_id' => $row->id, 'sale' => $row->public_id, 'location_id' => $row->location_id, 'appointment_id' => $row->appointment_id, 'client_id' => $row->client_id, 'gross_minor' => $row->subtotal_minor, 'discount_minor' => $row->discount_minor, 'tax_minor' => $row->tax_minor, 'tip_minor' => $row->tip_minor, 'refund_minor' => $row->refunded_minor, 'net_minor' => $row->subtotal_minor - $row->discount_minor - $row->refunded_minor, 'collected_minor' => $row->paid_minor + $row->deposit_applied_minor - $row->refunded_minor, 'outstanding_minor' => $row->balance_minor, 'completed_at' => $row->completed_at, 'drill' => "/businesses/{$business->public_id}/app/checkout-sales?sale={$row->public_id}"])->all();
    }

    private function serviceLines(Business $business, array $scope, bool $group): array
    {
        $sales = $this->baseSales($business, $scope)->select('sales.id');
        $query = DB::table('sale_lines')->joinSub($sales, 'filtered_sales', 'filtered_sales.id', '=', 'sale_lines.sale_id')->where('sale_lines.kind', 'service');
        if ($scope['staff_ids']) {
            $query->whereIn('sale_lines.staff_profile_id', $scope['staff_ids']);
        }
        if ($scope['service_ids']) {
            $query->whereIn('sale_lines.service_id', $scope['service_ids']);
        }
        if ($group) {
            return $query->select('description')->selectRaw('count(*) as sale_count, sum(quantity) as quantity, sum(quantity * unit_price_minor) as gross_minor, sum(discount_minor) as discount_minor')->groupBy('description')->orderByDesc('quantity')->get()->map(fn ($row) => ['service' => $row->description, 'sale_count' => $row->sale_count, 'quantity' => $row->quantity, 'gross_minor' => $row->gross_minor, 'discount_minor' => $row->discount_minor, 'net_minor' => $row->gross_minor - $row->discount_minor, 'drill' => "/businesses/{$business->public_id}/app/reports?report=service_revenue&service=".urlencode($row->description)])->all();
        }

        return $query->orderBy('sale_id')->get(['sale_lines.id', 'sale_id', 'service_id', 'staff_profile_id', 'description', 'quantity', 'unit_price_minor', 'discount_minor'])->map(fn ($row) => ['source_id' => $row->id, 'sale_id' => $row->sale_id, 'service_id' => $row->service_id, 'staff_id' => $row->staff_profile_id, 'service' => $row->description, 'quantity' => $row->quantity, 'gross_minor' => $row->quantity * $row->unit_price_minor, 'discount_minor' => $row->discount_minor, 'net_minor' => ($row->quantity * $row->unit_price_minor) - $row->discount_minor, 'drill' => "/businesses/{$business->public_id}/app/checkout-sales?sale_id={$row->sale_id}"])->all();
    }

    private function staffRevenue(Business $business, array $scope): array
    {
        $sales = $this->baseSales($business, $scope)->select('sales.id');
        $query = DB::table('sale_lines')->joinSub($sales, 'filtered_sales', 'filtered_sales.id', '=', 'sale_lines.sale_id')->leftJoin('staff_profiles', 'staff_profiles.id', '=', 'sale_lines.staff_profile_id')->whereNotNull('sale_lines.staff_profile_id');
        if ($scope['staff_ids']) {
            $query->whereIn('sale_lines.staff_profile_id', $scope['staff_ids']);
        }

        return $query->select('sale_lines.staff_profile_id', 'staff_profiles.display_name')->selectRaw('count(distinct sale_id) as sale_count, sum(quantity * unit_price_minor) as gross_minor, sum(discount_minor) as discount_minor')->groupBy('sale_lines.staff_profile_id', 'staff_profiles.display_name')->get()->map(fn ($row) => ['staff_id' => $row->staff_profile_id, 'staff' => $row->display_name, 'sale_count' => $row->sale_count, 'gross_minor' => $row->gross_minor, 'discount_minor' => $row->discount_minor, 'net_minor' => $row->gross_minor - $row->discount_minor, 'drill' => "/businesses/{$business->public_id}/app/reports?report=service_revenue&staff_ids[]={$row->staff_profile_id}"])->all();
    }

    private function payments(Business $business, array $scope, bool $byMethod): array
    {
        $sales = $this->baseSales($business, $scope)->select('sales.id');
        $query = DB::table('payment_transactions')->joinSub($sales, 'filtered_sales', 'filtered_sales.id', '=', 'payment_transactions.sale_id')->where('payment_transactions.status', 'succeeded');
        if ($byMethod) {
            return $query->select('method')->selectRaw("sum(case when kind = 'payment' then amount_minor when kind in ('refund','void') then -amount_minor else 0 end) as collected_minor, count(*) as transaction_count")->groupBy('method')->get()->map(fn ($row) => ['method' => $row->method, 'transaction_count' => $row->transaction_count, 'collected_minor' => $row->collected_minor, 'drill' => "/businesses/{$business->public_id}/app/reports?report=sales&payment_method={$row->method}"])->all();
        }

        return $query->whereIn('kind', ['refund', 'void'])->orderBy('occurred_at')->get(['payment_transactions.id', 'sale_id', 'public_id', 'kind', 'method', 'amount_minor', 'reason', 'occurred_at'])->map(fn ($row) => ['source_id' => $row->id, 'transaction' => $row->public_id, 'sale_id' => $row->sale_id, 'kind' => $row->kind, 'method' => $row->method, 'refund_minor' => $row->amount_minor, 'reason' => $row->reason, 'occurred_at' => $row->occurred_at, 'drill' => "/businesses/{$business->public_id}/app/checkout-sales?sale_id={$row->sale_id}"])->all();
    }

    private function locations(Business $business, array $scope): array
    {
        return $this->baseSales($business, $scope)->join('locations', 'locations.id', '=', 'sales.location_id')->select('sales.location_id', 'locations.name')->selectRaw('count(*) as sale_count, sum(sales.total_minor) as expected_minor, sum(sales.paid_minor + sales.deposit_applied_minor - sales.refunded_minor) as collected_minor')->groupBy('sales.location_id', 'locations.name')->get()->map(fn ($row) => ['location_id' => $row->location_id, 'location' => $row->name, 'sale_count' => $row->sale_count, 'expected_minor' => $row->expected_minor, 'collected_minor' => $row->collected_minor, 'drill' => "/businesses/{$business->public_id}/app/reports?report=sales&location_ids[]={$row->location_id}"])->all();
    }

    private function discounts(Business $business, array $scope): array
    {
        $sales = $this->baseSales($business, $scope)->select('sales.id');

        return DB::table('sale_lines')->joinSub($sales, 'filtered_sales', 'filtered_sales.id', '=', 'sale_lines.sale_id')->where('discount_minor', '>', 0)->get(['sale_lines.id', 'sale_id', 'description', 'kind', 'discount_minor'])->map(fn ($row) => ['source_id' => $row->id, 'sale_id' => $row->sale_id, 'description' => $row->description, 'kind' => $row->kind, 'discount_minor' => $row->discount_minor, 'drill' => "/businesses/{$business->public_id}/app/checkout-sales?sale_id={$row->sale_id}"])->all();
    }

    private function ledgerEntries(Business $business, array $scope, string $table): array
    {
        $query = DB::table($table)->where("{$table}.business_id", $business->id)->whereBetween('occurred_at', [$scope['from_utc'], $scope['to_utc']]);
        if ($scope['staff_ids']) {
            $query->whereIn('staff_profile_id', $scope['staff_ids']);
        }

        return $query->orderBy('occurred_at')->get()->map(fn ($row) => ['source_id' => $row->id, 'staff_id' => $row->staff_profile_id, 'sale_line_id' => $row->sale_line_id ?? null, 'payment_transaction_id' => $row->payment_transaction_id, 'type' => $row->type, 'amount_minor' => $row->amount_minor, 'reason' => $row->reason, 'occurred_at' => $row->occurred_at, 'drill' => "/businesses/{$business->public_id}/app/reports?report=".($table === 'tip_entries' ? 'tip' : 'commission')."&source_id={$row->id}"])->all();
    }

    private function clientClassification(Business $business, array $scope): array
    {
        $firstSales = DB::table('sales')->where('business_id', $business->id)->where('status', 'completed')->whereNotNull('client_id')->select('client_id')->selectRaw('min(completed_at) as first_completed_at')->groupBy('client_id');

        return $this->baseSales($business, $scope)->whereNotNull('sales.client_id')->joinSub($firstSales, 'first_sales', 'first_sales.client_id', '=', 'sales.client_id')->join('clients', function ($join) use ($business): void {
            $join->on('clients.id', '=', 'sales.client_id')->where('clients.business_id', $business->id);
        })->get(['sales.id', 'sales.public_id', 'sales.client_id', 'clients.public_id as client_public_id', 'sales.completed_at', 'first_sales.first_completed_at', 'sales.total_minor'])->map(fn ($row) => ['source_id' => $row->id, 'sale' => $row->public_id, 'client_id' => $row->client_id, 'classification' => $row->completed_at === $row->first_completed_at ? 'new' : 'returning', 'revenue_minor' => $row->total_minor, 'completed_at' => $row->completed_at, 'count' => 1, 'drill' => "/businesses/{$business->public_id}/app/clients/{$row->client_public_id}"])->all();
    }

    private function cancellations(Business $business, array $scope): array
    {
        $eligible = DB::table('appointments')->where('business_id', $business->id)->whereBetween('starts_at_utc', [$scope['from_utc'], $scope['to_utc']])->whereIn('location_id', $scope['location_ids'])->whereNotIn('status', ['cancelled_by_client', 'cancelled_by_shop', 'rescheduled'])->count();
        $rows = DB::table('appointments')->where('business_id', $business->id)->whereBetween('starts_at_utc', [$scope['from_utc'], $scope['to_utc']])->whereIn('location_id', $scope['location_ids'])->whereIn('status', ['cancelled_by_client', 'cancelled_by_shop', 'no_show'])->select('status')->selectRaw('count(*) as appointment_count')->groupBy('status')->get()->map(fn ($row) => ['status' => $row->status, 'appointment_count' => $row->appointment_count, 'eligible_count' => $eligible, 'rate_percent' => $eligible > 0 && $row->status === 'no_show' ? round($row->appointment_count * 100 / $eligible, 2) : 0, 'drill' => "/businesses/{$business->public_id}/app/calendar?status={$row->status}"])->all();

        return $rows;
    }

    private function utilisation(Business $business, array $scope): array
    {
        $booked = DB::table('appointment_segments')->join('appointments', 'appointments.id', '=', 'appointment_segments.appointment_id')->where('appointment_segments.business_id', $business->id)->where('occupies_staff', true)->whereNotNull('staff_profile_id')->whereIn('appointments.location_id', $scope['location_ids'])->whereBetween('appointment_segments.starts_at_utc', [$scope['from_utc'], $scope['to_utc']])->whereNotIn('appointments.status', ['cancelled_by_client', 'cancelled_by_shop', 'no_show', 'rescheduled'])->get(['appointment_segments.staff_profile_id', 'appointment_segments.starts_at_utc', 'appointment_segments.ends_at_utc'])->groupBy('staff_profile_id')->map(fn ($segments) => (int) $segments->sum(fn ($segment) => CarbonImmutable::parse($segment->starts_at_utc)->diffInMinutes(CarbonImmutable::parse($segment->ends_at_utc))));
        $rules = DB::table('staff_availability_rules')->where('business_id', $business->id)->where('kind', 'working')->whereIn('location_id', $scope['location_ids'])->when($scope['staff_ids'], fn ($q) => $q->whereIn('staff_profile_id', $scope['staff_ids']))->get();
        $available = [];
        foreach ($rules as $rule) {
            for ($date = $scope['from']->startOfDay(); $date->lessThanOrEqualTo($scope['to']); $date = $date->addDay()) {
                if ((int) $rule->day_of_week !== $date->dayOfWeek || ($rule->starts_on && $date->lt($rule->starts_on)) || ($rule->ends_on && $date->gt($rule->ends_on))) {
                    continue;
                }
                $available[$rule->staff_profile_id] = ($available[$rule->staff_profile_id] ?? 0) + CarbonImmutable::parse($rule->starts_at)->diffInMinutes(CarbonImmutable::parse($rule->ends_at));
            }
        }
        $staffIds = collect(array_unique([...array_keys($available), ...$booked->keys()->all()]))->when($scope['staff_ids'], fn ($ids) => $ids->intersect($scope['staff_ids']));

        return $staffIds->map(function ($staffId) use ($available, $booked, $business): array {
            $availableMinutes = (int) ($available[$staffId] ?? 0);
            $bookedMinutes = (int) ($booked[$staffId] ?? 0);

            return ['staff_id' => (int) $staffId, 'available_minutes' => $availableMinutes, 'booked_minutes' => $bookedMinutes, 'utilisation_percent' => $availableMinutes > 0 ? round($bookedMinutes * 100 / $availableMinutes, 2) : 0, 'drill' => "/businesses/{$business->public_id}/app/calendar?staff={$staffId}"];
        })->values()->all();
    }

    private function visitFrequency(Business $business, array $scope): array
    {
        return $this->baseSales($business, $scope)->whereNotNull('sales.client_id')->join('clients', function ($join) use ($business): void {
            $join->on('clients.id', '=', 'sales.client_id')->where('clients.business_id', $business->id);
        })->select('sales.client_id', 'clients.public_id as client_public_id')->selectRaw('count(*) as visit_count, sum(sales.total_minor) as revenue_minor, min(sales.completed_at) as first_visit_at, max(sales.completed_at) as last_visit_at')->groupBy('sales.client_id', 'clients.public_id')->orderByDesc('visit_count')->get()->map(fn ($row) => ['client_id' => $row->client_id, 'visit_count' => $row->visit_count, 'revenue_minor' => $row->revenue_minor, 'first_visit_at' => $row->first_visit_at, 'last_visit_at' => $row->last_visit_at, 'drill' => "/businesses/{$business->public_id}/app/clients/{$row->client_public_id}"])->all();
    }

    private function productSales(Business $business, array $scope): array
    {
        $sales = $this->baseSales($business, $scope)->select('sales.id');

        return DB::table('sale_lines')->joinSub($sales, 'filtered_sales', 'filtered_sales.id', '=', 'sale_lines.sale_id')->where('sale_lines.kind', 'product')->select('source_id', 'description')->selectRaw('sum(quantity) as quantity, sum(quantity * unit_price_minor) as gross_minor, sum(discount_minor) as discount_minor')->groupBy('source_id', 'description')->get()->map(fn ($row) => ['product_id' => $row->source_id, 'product' => $row->description, 'quantity' => $row->quantity, 'gross_minor' => $row->gross_minor, 'discount_minor' => $row->discount_minor, 'net_minor' => $row->gross_minor - $row->discount_minor, 'drill' => "/businesses/{$business->public_id}/app/inventory?product_id={$row->source_id}"])->all();
    }

    private function stock(Business $business, array $scope): array
    {
        $levels = DB::table('inventory_levels')->where('business_id', $business->id)->whereIn('location_id', $scope['location_ids'])->select('inventory_product_id')->selectRaw('sum(current_stock) as scoped_stock')->groupBy('inventory_product_id')->pluck('scoped_stock', 'inventory_product_id');

        return DB::table('inventory_products')->leftJoin('product_categories', 'product_categories.id', '=', 'inventory_products.product_category_id')->where('inventory_products.business_id', $business->id)->orderBy('inventory_products.name')->get(['inventory_products.id', 'inventory_products.public_id', 'inventory_products.name', 'product_categories.name as category', 'sku', 'inventory_products.status', 'inventory_products.current_stock', 'low_stock_threshold', 'cost_minor', 'sale_price_minor'])->map(function ($row) use ($levels, $scope, $business): array {
            $stock = isset($levels[$row->id]) ? (int) $levels[$row->id] : ($scope['all_locations'] ? (int) $row->current_stock : 0);

            return ['source_id' => $row->id, 'product' => $row->public_id, 'name' => $row->name, 'category' => $row->category, 'sku' => $row->sku, 'status' => $row->status, 'current_stock' => $stock, 'low_stock_threshold' => $row->low_stock_threshold, 'low_stock_count' => $stock <= $row->low_stock_threshold ? 1 : 0, 'cost_minor' => $row->cost_minor, 'valuation_minor' => $stock * $row->cost_minor, 'sale_price_minor' => $row->sale_price_minor, 'drill' => "/businesses/{$business->public_id}/app/inventory?product={$row->public_id}"];
        })->all();
    }

    private function cashCloses(Business $business, array $scope): array
    {
        return DB::table('cash_closes')->where('business_id', $business->id)->whereIn('location_id', $scope['location_ids'])->whereBetween('business_date', [$scope['from']->toDateString(), $scope['to']->toDateString()])->orderBy('business_date')->get()->map(fn ($row) => ['source_id' => $row->id, 'location_id' => $row->location_id, 'business_date' => $row->business_date, 'opening_cash_minor' => $row->opening_cash_minor, 'expected_cash_minor' => $row->expected_cash_minor, 'actual_cash_minor' => $row->actual_cash_minor, 'variance_minor' => $row->variance_minor, 'outstanding_minor' => $row->outstanding_balance_minor, 'drill' => "/businesses/{$business->public_id}/app/checkout-sales?cash_close={$row->id}"])->all();
    }

    /** @param list<array<string,mixed>> $rows
     * @return array<string,int|float>
     */
    private function totals(array $rows): array
    {
        $totals = ['row_count' => count($rows)];
        foreach ($rows as $row) {
            foreach ($row as $key => $value) {
                if (is_numeric($value) && (str_ends_with($key, '_minor') || str_ends_with($key, '_count') || in_array($key, ['quantity', 'available_minutes', 'booked_minutes'], true))) {
                    $totals[$key] = ($totals[$key] ?? 0) + $value;
                }
            }
        }

        return $totals;
    }

    private function sourceFor(string $key): string
    {
        return match ($key) {
            'appointments', 'cancellation_no_show' => 'appointments',
            'service_revenue', 'staff_revenue', 'popular_service', 'product_sales', 'discount' => 'completed sales + immutable sale lines',
            'payment_method', 'refund' => 'completed sales + immutable payment transactions',
            'tip' => 'append-only tip entries',
            'commission', 'payroll' => 'append-only commission and tip entries',
            'utilisation' => 'appointment segments + effective staff availability',
            'stock' => 'inventory products + append-only movements',
            'cash_close' => 'immutable cash closes',
            default => 'completed sales + payment events',
        };
    }
}
