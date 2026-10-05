<?php

namespace App\Domain\Reporting\Services;

use App\Domain\PlatformAccess\Enums\PermissionName;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\Membership;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class ReportAnalysis
{
    public function __construct(private readonly ReportQuery $queries, private readonly CapacityReport $capacity) {}

    public function build(Business $business, Membership $membership, string $key, array $scope, array $filters): array
    {
        if ($key === 'utilisation') {
            return $this->capacityResult($business, $membership, $scope, $filters);
        }
        $columns = $this->reportColumns($key, $scope);
        $query = $this->filtered($this->queries->build($business, $key, $scope), $filters, $columns);
        $totals = $this->totals($business, $key, $query, $scope, $columns, $filters);
        $previous = null;
        $previousQuery = null;
        if (($filters['compare'] ?? 'previous') !== 'none' && $key !== 'stock') {
            $previousScope = $this->previousScope($scope, $filters['compare'] ?? 'previous');
            $previousQuery = $this->filtered($this->queries->build($business, $key, $previousScope), $filters, $columns);
            $previous = ['from' => $previousScope['from']->toDateString(), 'to' => $previousScope['to']->toDateString(), 'totals' => $this->totals($business, $key, $previousQuery, $previousScope, $columns, $filters)];
        }
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = min(100, max(10, (int) ($filters['per_page'] ?? 30)));
        $lastPage = max(1, (int) ceil($totals['row_count'] / $perPage));
        $page = min($page, $lastPage);
        $sort = in_array($filters['sort'] ?? '', $columns, true) ? $filters['sort'] : $this->defaultSort($key, $columns);
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $records = (clone $query)->orderBy($sort, $direction);
        if ($sort !== $columns[0]) {
            $records->orderBy($columns[0]);
        }
        if ($key === 'popular_service' && $sort !== 'service') {
            $records->orderBy('service');
        }
        if ($key === 'payroll') {
            $records->orderBy('ledger');
        }
        $rows = $records->offset(($page - 1) * $perPage)->limit($perPage)->get()->map(fn ($row) => $this->decorate($business, $membership, $key, $scope, (array) $row))->all();
        $chartError = null;
        $trend = [];
        $ranking = [];
        $patterns = [];
        // Only ancillary analysis degrades independently; query/permission
        // failures in the financial source must still fail closed.
        try {
            $trend = $this->trend($key, $query, $columns, $scope, $previousQuery, isset($previousScope) ? $previousScope : null, $filters);
            $ranking = $this->ranking($business, $key, $scope, $query, $columns, $filters);
            if (in_array($key, ['overview', 'appointments'], true)) {
                $patterns = $this->patterns($query, $columns, $scope);
            }
        } catch (QueryException $e) {
            report($e);
            $chartError = 'Trend analysis could not be loaded. The record totals are available.';
        }
        $result = $this->envelope($key, $scope, $filters) + compact('rows', 'totals', 'columns', 'trend', 'ranking', 'patterns');
        $result['columns'] = [...$columns, 'drill'];
        $result['pagination'] = ['current_page' => $page, 'last_page' => $lastPage, 'per_page' => $perPage, 'total' => $totals['row_count'], 'from' => $rows ? ($page - 1) * $perPage + 1 : 0, 'to' => ($page - 1) * $perPage + count($rows), 'sort' => $sort, 'direction' => $direction];
        $result['previous_period'] = $previous;
        $result['chart_error'] = $chartError;
        $result['insights'] = $this->insights($totals, $previous, $ranking);

        return $result;
    }

    public function reportColumns(string $key, array $scope): array
    {
        $columns = match ($key) {
            'overview', 'sales', 'tax' => ['source_id', 'sale', 'location_id', 'location', 'status', 'currency_code', 'gross_minor', 'discount_minor', 'tax_minor', 'tip_minor', 'refund_minor', 'deposit_applied_minor', 'outstanding_minor', 'completed_at', 'discounted_minor', 'net_minor', 'collected_minor', 'tax_basis'],
            'appointments', 'cancellation_no_show' => ['source_id', 'appointment', 'location_id', 'location', 'status', 'source', 'starts_at', 'ends_at', 'duration_minutes', 'reason'],
            'line_items', 'service_revenue', 'discount' => ['source_id', 'sale_id', 'sale', 'service_id', 'staff_id', 'staff', 'service', 'description', 'kind', 'quantity', 'discount_minor', 'completed_at', 'currency_code', 'gross_minor', 'net_minor', 'allocated_refund_minor'],
            'popular_service' => ['service_id', 'service', 'sale_count', 'quantity', 'gross_minor', 'discount_minor', 'net_minor', 'allocated_refund_minor'],
            'staff_revenue' => ['staff_id', 'staff', 'sale_count', 'quantity', 'gross_minor', 'discount_minor', 'net_minor', 'allocated_refund_minor'],
            'product_sales' => ['product_id', 'product', 'sale_count', 'quantity', 'gross_minor', 'discount_minor', 'net_minor', 'allocated_refund_minor'],
            'payment_method' => ['method', 'transaction_count', 'payment_minor', 'refund_minor', 'collected_minor'],
            'payment_activity', 'refund' => ['source_id', 'transaction', 'sale_id', 'sale', 'kind', 'method', 'reason', 'occurred_at', 'currency_code', 'payment_minor', 'refund_minor', 'collected_minor'],
            'location' => ['location_id', 'location', 'sale_count', 'gross_minor', 'discount_minor', 'expected_minor', 'collected_minor', 'outstanding_minor'],
            'client_classification', 'visit_frequency' => ['client_id', 'client', 'classification', 'visit_count', 'revenue_minor', 'first_visit_at', 'last_visit_at'],
            'tip', 'commission', 'payroll' => ['source_id', 'staff_id', 'staff', 'type', 'amount_minor', 'reason', 'occurred_at', 'currency_code', 'sale', 'ledger', 'entry_kind'],
            'walk_ins' => ['source_id', 'entry', 'location_id', 'location', 'status', 'arrived_at', 'service_started_at', 'actual_wait_minutes', 'staff_id', 'staff', 'appointment'],
            'cash_close' => ['source_id', 'location_id', 'location', 'business_date', 'opening_cash_minor', 'expected_cash_minor', 'actual_cash_minor', 'variance_minor', 'outstanding_minor', 'currency_code'],
            'stock' => ['source_id', 'product', 'name', 'category', 'sku', 'status', 'low_stock_threshold', 'currency_code', 'current_stock', 'valuation_minor', 'low_stock_count'],
            default => [],
        };
        if ($key === 'popular_service' && ! $scope['can_finance']) {
            $columns = array_values(array_filter($columns, fn ($column) => ! str_ends_with($column, '_minor')));
        }

        return $columns;
    }

    public function filtered(Builder $source, array $filters, array $columns = []): Builder
    {
        $q = DB::query()->fromSub($source, 'report_data');
        $term = trim((string) ($filters['search'] ?? ''));
        $searchable = array_intersect($columns, ['sale', 'service', 'description', 'staff', 'method', 'transaction', 'appointment', 'status', 'classification', 'reason', 'location', 'product', 'name', 'client', 'type', 'ledger']);
        $dimension = $filters['dimension'] ?? '';
        if (in_array($dimension, $searchable, true) && isset($filters['dimension_value'])) {
            $filters['dimension_value'] === '__unattributed' ? $q->whereNull($dimension) : $q->where($dimension, $filters['dimension_value']);
        }
        if ($term !== '' && $searchable) {
            $q->where(function ($query) use ($term, $searchable) {
                // Bound values, no user-controlled SQL column or direction.
                foreach ($searchable as $column) {
                    $query->orWhere($column, 'like', '%'.$term.'%');
                }
            });
        }

        return $q;
    }

    private function totals(Business $business, string $key, Builder $query, array $scope, array $columns, array $filters): array
    {
        $q = (clone $query)->selectRaw('count(*) as row_count');
        foreach ($columns as $column) {
            if ((str_ends_with($column, '_minor') || str_ends_with($column, '_count') || $column === 'quantity') && ! ($column === 'sale_count' && in_array($key, ['staff_revenue', 'popular_service', 'product_sales'], true))) {
                $q->selectRaw("coalesce(sum({$column}), 0) as {$column}");
            }
        }
        if (in_array($key, ['overview', 'sales', 'tax'], true)) {
            $q->selectRaw('round(avg(discounted_minor), 0) as average_ticket_minor');
        }
        if (in_array($key, ['appointments', 'cancellation_no_show'], true)) {
            $q->selectRaw("sum(case when status = 'completed' then 1 else 0 end) as completed_count, sum(case when status in ('cancelled_by_client','cancelled_by_shop') then 1 else 0 end) as cancelled_count, sum(case when status = 'no_show' then 1 else 0 end) as no_show_count, round(avg(duration_minutes), 1) as average_duration_minutes");
        }
        if ($key === 'client_classification') {
            $q->selectRaw("sum(case when classification = 'new' then 1 else 0 end) as new_count, sum(case when classification = 'returning' then 1 else 0 end) as returning_count");
        }
        if ($key === 'walk_ins') {
            $q->selectRaw('sum(case when service_started_at is not null then 1 else 0 end) as started_count, round(avg(actual_wait_minutes), 1) as average_wait_minutes, max(actual_wait_minutes) as longest_wait_minutes');
        }
        if ($key === 'payroll') {
            $q->selectRaw("coalesce(sum(case when ledger = 'commission' then amount_minor else 0 end),0) as commission_minor, coalesce(sum(case when ledger = 'tip' then amount_minor else 0 end),0) as tips_minor");
        }
        $totals = $this->numbers((array) $q->first());
        foreach (['completed_count', 'cancelled_count', 'no_show_count', 'new_count', 'returning_count', 'started_count'] as $count) {
            if (array_key_exists($count, $totals)) {
                $totals[$count] ??= 0;
            }
        }
        if (in_array($key, ['appointments', 'cancellation_no_show'], true)) {
            $denominator = $this->filtered($this->queries->build($business, 'appointments', [...$scope, 'statuses' => []]), $filters, $columns)->selectRaw("sum(case when status not in ('cancelled_by_client','cancelled_by_shop','rescheduled') then 1 else 0 end) as eligible_count, sum(case when status != 'rescheduled' then 1 else 0 end) as cancellation_base")->first();
            $totals['eligible_count'] = (int) $denominator->eligible_count;
            $totals['no_show_percent'] = $denominator->eligible_count > 0 ? round($totals['no_show_count'] * 100 / $denominator->eligible_count, 1) : null;
            $totals['cancellation_percent'] = $denominator->cancellation_base > 0 ? round($totals['cancelled_count'] * 100 / $denominator->cancellation_base, 1) : null;
        }

        return $totals;
    }

    public function previousScope(array $scope, string $compare): array
    {
        $days = (int) $scope['from']->startOfDay()->diffInDays($scope['to']->startOfDay()) + 1;
        $from = $compare === 'year' ? $scope['from']->subYearNoOverflow() : $scope['from']->subDays($days);
        $to = $compare === 'year' ? $scope['to']->subYearNoOverflow() : $scope['from']->subDay()->endOfDay();

        return [...$scope, 'from' => $from, 'to' => $to, 'from_utc' => $from->startOfDay()->utc(), 'to_utc' => $to->utc(), 'until_utc' => $to->addDay()->startOfDay()->utc()];
    }

    private function defaultSort(string $key, array $columns): string
    {
        foreach (['completed_at', 'starts_at', 'occurred_at', 'arrived_at', 'business_date', 'net_minor', 'quantity', 'revenue_minor', 'collected_minor', 'valuation_minor'] as $column) {
            if (in_array($column, $columns, true)) {
                return $column;
            }
        }

        return $columns[0];
    }

    private function trend(string $key, Builder $query, array $columns, array $scope, ?Builder $previousQuery, ?array $previousScope, array $filters): array
    {
        $dateColumn = collect(['completed_at', 'starts_at', 'occurred_at', 'arrived_at'])->first(fn ($column) => in_array($column, $columns, true));
        if (! $dateColumn) {
            return [];
        }
        $metric = collect(['collected_minor', 'net_minor', 'refund_minor', 'amount_minor'])->first(fn ($column) => in_array($column, $columns, true)) ?? 'row_count';
        if ($key === 'refund') {
            $metric = 'refund_minor';
        }
        $days = (int) $scope['from']->startOfDay()->diffInDays($scope['to']->startOfDay()) + 1;
        $granularity = $filters['granularity'] ?? 'auto';
        if ($granularity === 'auto') {
            $granularity = $days === 1 ? 'hour' : ($days <= 31 ? 'day' : ($days <= 120 ? 'week' : 'month'));
        }
        // Bound bucket count even if a client requests unsuitable granularity.
        if ($granularity === 'hour' && $days > 1) {
            $granularity = $days <= 31 ? 'day' : 'week';
        }
        if ($granularity === 'day' && $days > 93) {
            $granularity = 'week';
        }
        $buckets = $this->buckets($scope, $granularity);
        $values = $this->bucketValues($query, $dateColumn, $metric, $buckets);
        $previousValues = [];
        $previousBuckets = $previousQuery && $previousScope ? $this->buckets($previousScope, $granularity) : [];
        if ($previousQuery && $previousScope) {
            $previousValues = $this->bucketValues($previousQuery, $dateColumn, $metric, $previousBuckets);
        }
        $previousHours = [];
        $occurrences = [];
        foreach ($previousBuckets as $index => $bucket) {
            $hour = $bucket['from']->format('H:i');
            $occurrence = $occurrences[$hour] ?? 0;
            $previousHours[$hour.':'.$occurrence] = ['value' => $previousValues[$index] ?? 0, 'label' => $bucket['from']->format('j M Y H:i T')];
            $occurrences[$hour] = $occurrence + 1;
        }
        $points = [];
        $occurrences = [];
        foreach ($buckets as $index => $bucket) {
            $hour = $bucket['from']->format('H:i');
            $occurrence = $occurrences[$hour] ?? 0;
            $occurrences[$hour] = $occurrence + 1;
            // Align local hours, including repeated fall-back hours. A missing
            // counterpart is unavailable; a valid empty bucket is exactly zero.
            $counterpart = $granularity === 'hour' ? ($previousHours[$hour.':'.$occurrence] ?? null) : (isset($previousBuckets[$index]) ? ['value' => $previousValues[$index] ?? 0, 'label' => $previousBuckets[$index]['from']->format('j M Y')] : null);
            $points[] = ['label' => $bucket['label'], 'from' => $bucket['from']->toDateString(), 'to' => $bucket['until']->subSecond()->toDateString(), 'value' => $values[$index] ?? 0, 'previous' => $counterpart['value'] ?? null, 'previous_label' => $counterpart['label'] ?? null];
        }

        return ['metric' => $metric, 'granularity' => $granularity, 'points' => $points];
    }

    private function buckets(array $scope, string $granularity): array
    {
        $out = [];
        for ($from = $scope['from']; $from->lt($scope['until_utc']->setTimezone($scope['time_zone'])); $from = $until) {
            $until = match ($granularity) {
                'hour' => $from->addHour(), 'week' => $from->addDays(7), 'month' => $from->addMonthNoOverflow(), default => $from->addDay()
            };
            $until = $until->min($scope['until_utc']->setTimezone($scope['time_zone']));
            $out[] = ['from' => $from, 'until' => $until, 'label' => $from->format($granularity === 'hour' ? 'H:i T' : ($granularity === 'month' ? 'M Y' : 'j M'))];
        }

        return $out;
    }

    private function bucketValues(Builder $query, string $dateColumn, string $metric, array $buckets): array
    {
        $sql = 'case';
        $bindings = [];
        foreach ($buckets as $index => $bucket) {
            $sql .= " when {$dateColumn} >= ? and {$dateColumn} < ? then {$index}";
            $bindings[] = $bucket['from']->utc();
            $bindings[] = $bucket['until']->utc();
        }
        $sql .= ' else -1 end';
        $value = $metric === 'row_count' ? 'count(*)' : "sum({$metric})";

        return (clone $query)->selectRaw($sql.' as bucket', $bindings)->selectRaw($value.' as value')->groupBy('bucket')->get()->mapWithKeys(fn ($row) => [(int) $row->bucket => (int) $row->value])->all();
    }

    private function ranking(Business $business, string $key, array $scope, Builder $query, array $columns, array $filters): array
    {
        if ($key === 'overview') {
            $key = 'service_revenue';
            $columns = $this->reportColumns($key, $scope);
            $query = $this->filtered($this->queries->build($business, $key, $scope), $filters, $columns);
        }
        $category = collect(['service', 'staff', 'method', 'classification', 'status', 'location', 'type'])->first(fn ($column) => in_array($column, $columns, true));
        if (! $category) {
            return [];
        }
        $metric = collect(['net_minor', 'collected_minor', 'revenue_minor', 'amount_minor', 'quantity', 'visit_count'])->first(fn ($column) => in_array($column, $columns, true)) ?? 'row_count';
        $value = $metric === 'row_count' ? 'count(*)' : "sum({$metric})";
        $rows = (clone $query)->select($category.' as label')->selectRaw($value.' as value')->groupBy($category)->orderByDesc('value')->limit(6)->get()->map(fn ($row) => ['label' => $row->label ?? 'Unattributed', 'value' => (int) $row->value, 'search' => $row->label ?? '', 'dimension_value' => $row->label ?? '__unattributed'])->all();

        return ['category' => $category, 'metric' => $metric, 'report' => $key, 'rows' => $rows];
    }

    private function patterns(Builder $query, array $columns, array $scope): array
    {
        $dateColumn = in_array('completed_at', $columns, true) ? 'completed_at' : 'starts_at';
        $metric = in_array('collected_minor', $columns, true) ? 'collected_minor' : 'row_count';
        $sql = 'case';
        $bindings = [];
        for ($day = $scope['from']; $day->lte($scope['to']); $day = $day->addDay()) {
            $sql .= " when {$dateColumn} >= ? and {$dateColumn} < ? then ".$day->dayOfWeekIso;
            $bindings[] = $day->utc();
            $bindings[] = $day->addDay()->utc();
        }
        $sql .= ' else 0 end';
        $value = $metric === 'row_count' ? 'count(*)' : "sum({$metric})";
        $rows = (clone $query)->selectRaw($sql.' as weekday', $bindings)->selectRaw($value.' as value, count(*) as records')->groupBy('weekday')->get()->keyBy('weekday');

        return ['metric' => $metric, 'days' => array_map(fn ($day) => ['label' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'][$day - 1], 'value' => (int) ($rows->get($day)?->value ?? 0), 'records' => (int) ($rows->get($day)?->records ?? 0)], range(1, 7))];
    }

    public function decorate(Business $business, Membership $membership, string $key, array $scope, array $row): array
    {
        $row = $this->numbers($row);
        $base = "/businesses/{$business->public_id}/app";
        $filters = ['start_date' => $scope['from']->toDateString(), 'end_date' => $scope['to']->toDateString(), 'location_ids' => $scope['location_ids'], 'staff_ids' => $scope['staff_ids'], 'service_ids' => $scope['service_ids'], 'currency_code' => $scope['currency_code']];
        $row['drill'] = null;
        if (! empty($row['sale']) && $scope['can_finance']) {
            $row['drill'] = $base.'/checkout-sales?section=sales&sale='.urlencode($row['sale']);
        } elseif (! empty($row['appointment']) && $membership->hasAnyPermission([PermissionName::CalendarViewAll->value, PermissionName::CalendarViewOwn->value])) {
            $row['drill'] = $base.'/calendar?appointment='.urlencode($row['appointment']);
        } elseif (in_array($key, ['client_classification', 'visit_frequency'], true) && $membership->hasPermissionTo(PermissionName::ClientView->value, 'web')) {
            $row['drill'] = $base.'/clients/'.urlencode($row['client']);
        } elseif ($key === 'popular_service' && $scope['can_finance']) {
            $row['drill'] = $base.'/reports?'.http_build_query([...$filters, 'report' => 'service_revenue', 'service_ids' => $row['service_id'] ? [$row['service_id']] : [], 'search' => $row['service']]);
        } elseif ($key === 'staff_revenue') {
            $row['drill'] = $base.'/reports?'.http_build_query([...$filters, 'report' => 'line_items', 'staff_ids' => $row['staff_id'] ? [$row['staff_id']] : [], 'search' => $row['staff']]);
        } elseif ($key === 'location') {
            $row['drill'] = $base.'/reports?'.http_build_query([...$filters, 'report' => 'sales', 'location_ids' => [$row['location_id']]]);
        } elseif ($key === 'payment_method') {
            $row['drill'] = $base.'/reports?'.http_build_query([...$filters, 'report' => 'sales', 'method' => $row['method']]);
        } elseif ($key === 'utilisation') {
            $row['drill'] = $base.'/reports?'.http_build_query([...$filters, 'report' => 'appointments', 'staff_ids' => [$row['staff_id']]]);
        }

        return $row;
    }

    private function numbers(array $row): array
    {
        foreach ($row as $key => $value) {
            if ($value !== null && is_numeric($value) && ! in_array($key, ['sale', 'appointment', 'transaction', 'client', 'entry'], true)) {
                $row[$key] = str_contains((string) $value, '.') ? (float) $value : (int) $value;
            }
        }

        return $row;
    }

    private function envelope(string $key, array $scope, array $filters): array
    {
        return ['report_key' => $key, 'metric_version' => MetricCatalog::VERSION, 'effective_from' => MetricCatalog::EFFECTIVE_FROM, 'fresh_at' => now()->utc()->toIso8601String(), 'time_zone' => $scope['time_zone'], 'currency_code' => $scope['currency_code'],
            'filters' => ['start_date' => $scope['from']->toDateString(), 'end_date' => $scope['to']->toDateString(), 'location_ids' => $scope['location_ids'], 'staff_ids' => $scope['staff_ids'], 'service_ids' => $scope['service_ids'], 'statuses' => $scope['statuses'], 'method' => $scope['method'], 'currency_code' => $scope['currency_code'], 'search' => $filters['search'] ?? '', 'compare' => $filters['compare'] ?? 'previous', 'granularity' => $filters['granularity'] ?? 'auto', 'dimension' => $filters['dimension'] ?? '', 'dimension_value' => $filters['dimension_value'] ?? ''],
            'source' => match ($key) {
                'utilisation' => 'Calendar effective windows + occupied appointment segments', 'appointments', 'cancellation_no_show' => 'Scheduled appointment start dates', 'walk_ins' => 'Walk-in arrival dates + measured waits', 'payment_activity', 'refund' => 'Successful payment transaction occurrence dates; deposit collection excluded', 'tip', 'commission', 'payroll' => 'Append-only compensation occurrence dates', 'stock' => 'Current scoped stock', default => 'Sale completion period; open sales use creation dates when selected'
            }];
    }

    private function capacityResult(Business $business, Membership $membership, array $scope, array $filters): array
    {
        $rows = $this->capacity->rows($business, $scope);
        $term = mb_strtolower(trim($filters['search'] ?? ''));
        if ($term) {
            $rows = array_values(array_filter($rows, fn ($row) => str_contains(mb_strtolower($row['staff']), $term)));
        }
        $totals = ['row_count' => count($rows), 'available_minutes' => array_sum(array_column($rows, 'available_minutes')), 'booked_minutes' => array_sum(array_column($rows, 'booked_minutes')), 'free_minutes' => array_sum(array_column($rows, 'free_minutes')), 'recorded_minutes' => array_sum(array_column($rows, 'recorded_minutes')), 'outside_windows_minutes' => array_sum(array_column($rows, 'outside_windows_minutes'))];
        $totals['utilisation_percent'] = $totals['available_minutes'] ? round($totals['booked_minutes'] * 100 / $totals['available_minutes'], 1) : null;
        $sort = in_array($filters['sort'] ?? '', ['staff', 'available_minutes', 'recorded_minutes', 'booked_minutes', 'free_minutes', 'outside_windows_minutes', 'utilisation_percent', 'schedule_status'], true) ? $filters['sort'] : 'booked_minutes';
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $rankRows = $rows;
        usort($rankRows, fn ($a, $b) => ($b['booked_minutes'] <=> $a['booked_minutes']) ?: ($a['staff_id'] <=> $b['staff_id']));
        usort($rows, fn ($a, $b) => (($a[$sort] <=> $b[$sort]) * ($direction === 'asc' ? 1 : -1)) ?: ($a['staff_id'] <=> $b['staff_id']));
        $perPage = min(100, max(10, (int) ($filters['per_page'] ?? 30)));
        $page = min(max(1, (int) ($filters['page'] ?? 1)), max(1, (int) ceil(count($rows) / $perPage)));
        $pageRows = array_map(fn ($row) => $this->decorate($business, $membership, 'utilisation', $scope, $row), array_slice($rows, ($page - 1) * $perPage, $perPage));

        return $this->envelope('utilisation', $scope, $filters) + ['rows' => $pageRows, 'columns' => ['staff_id', 'staff', 'available_minutes', 'recorded_minutes', 'booked_minutes', 'free_minutes', 'outside_windows_minutes', 'utilisation_percent', 'schedule_status', 'drill'], 'totals' => $totals, 'previous_period' => null, 'trend' => [], 'ranking' => ['category' => 'staff', 'metric' => 'booked_minutes', 'report' => 'utilisation', 'rows' => array_map(fn ($row) => ['label' => $row['staff'], 'value' => $row['booked_minutes'], 'search' => $row['staff'], 'staff_id' => $row['staff_id']], array_slice($rankRows, 0, 6))], 'insights' => [], 'chart_error' => null, 'pagination' => ['current_page' => $page, 'last_page' => max(1, (int) ceil(count($rows) / $perPage)), 'per_page' => $perPage, 'total' => count($rows), 'from' => $rows ? ($page - 1) * $perPage + 1 : 0, 'to' => min($page * $perPage, count($rows)), 'sort' => $sort, 'direction' => $direction]];
    }

    private function insights(array $totals, ?array $previous, array $ranking): array
    {
        $out = [];
        if ($previous && isset($totals['average_ticket_minor'], $previous['totals']['average_ticket_minor'])) {
            $out[] = ['metric' => 'average_ticket_minor', 'current' => $totals['average_ticket_minor'], 'previous' => $previous['totals']['average_ticket_minor']];
        }
        if ($previous) {
            $out[] = ['metric' => 'row_count', 'current' => $totals['row_count'], 'previous' => $previous['totals']['row_count']];
        }

        return $out;
    }
}
