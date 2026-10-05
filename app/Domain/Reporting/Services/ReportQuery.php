<?php

namespace App\Domain\Reporting\Services;

use App\Domain\PlatformAccess\Models\Business;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** Shared SQL projections for summaries, paged investigation, print and export. */
class ReportQuery
{
    public function build(Business $business, string $key, array $scope): Builder
    {
        return match ($key) {
            'overview', 'sales', 'tax' => $this->sales($business, $scope),
            'appointments', 'cancellation_no_show' => $this->appointments($business, $scope, $key),
            'line_items', 'service_revenue', 'discount' => $this->lines($business, $scope, $key),
            'staff_revenue', 'popular_service', 'product_sales' => $this->groupedLines($business, $scope, $key),
            'payment_method' => $this->paymentMix($business, $scope),
            'payment_activity', 'refund' => $this->payments($business, $scope, $key),
            'location' => $this->locations($business, $scope),
            'client_classification', 'visit_frequency' => $this->clients($business, $scope),
            'commission', 'tip' => $this->ledger($business, $scope, $key),
            'payroll' => $this->ledger($business, $scope, 'commission')->unionAll($this->ledger($business, $scope, 'tip')),
            'walk_ins' => $this->walkIns($business, $scope),
            'cash_close' => $this->cashClose($business, $scope),
            'stock' => $this->stock($business, $scope),
            default => throw new \DomainException('Unknown report projection.'),
        };
    }

    public function baseSales(Business $business, array $scope, bool $dateBound = true): Builder
    {
        $q = DB::table('sales')->where('sales.business_id', $business->id)->whereIn('sales.location_id', $scope['location_ids'])->where('sales.currency_code', $scope['currency_code']);
        // Completion timestamp is indexable; open-sale inclusion uses a separate
        // branch rather than COALESCE on every row of the composite date index.
        if ($dateBound) {
            $q->where(function ($dates) use ($scope) {
                $dates->where(fn ($completed) => $completed->whereNotNull('sales.completed_at')->where('sales.completed_at', '>=', $scope['from_utc'])->where('sales.completed_at', '<', $scope['until_utc']));
                if (in_array('open', $scope['statuses'], true)) {
                    $dates->orWhere(fn ($open) => $open->whereNull('sales.completed_at')->where('sales.created_at', '>=', $scope['from_utc'])->where('sales.created_at', '<', $scope['until_utc']));
                }
            });
        }
        $q->whereIn('sales.status', $scope['statuses'] ?: ['completed']);
        if ($scope['staff_ids']) {
            $q->whereExists(fn ($lines) => $lines->selectRaw('1')->from('sale_lines')->whereColumn('sale_lines.sale_id', 'sales.id')->where('sale_lines.business_id', $business->id)->whereIn('sale_lines.staff_profile_id', $scope['staff_ids']));
        }
        if ($scope['service_ids']) {
            $q->whereExists(fn ($lines) => $lines->selectRaw('1')->from('sale_lines')->whereColumn('sale_lines.sale_id', 'sales.id')->where('sale_lines.business_id', $business->id)->whereIn('sale_lines.service_id', $scope['service_ids']));
        }
        if ($scope['method']) {
            $q->whereExists(fn ($payments) => $payments->selectRaw('1')->from('payment_transactions')->whereColumn('payment_transactions.sale_id', 'sales.id')->where('payment_transactions.business_id', $business->id)->where('payment_transactions.method', $scope['method'])->where('payment_transactions.status', 'succeeded'));
        }

        return $q;
    }

    private function taxBasis(): string
    {
        return DB::getDriverName() === 'sqlite'
            ? "case when json_extract(sales.calculation_snapshot, '$.tax_inclusive') = 1 then 'Inclusive' when json_extract(sales.calculation_snapshot, '$.tax_inclusive') = 0 then 'Exclusive' else 'Not recorded' end"
            : "case when JSON_UNQUOTE(JSON_EXTRACT(sales.calculation_snapshot, '$.tax_inclusive')) = 'true' then 'Inclusive' when JSON_UNQUOTE(JSON_EXTRACT(sales.calculation_snapshot, '$.tax_inclusive')) = 'false' then 'Exclusive' else 'Not recorded' end";
    }

    private function sales(Business $business, array $scope): Builder
    {
        return $this->baseSales($business, $scope)->leftJoin('locations', 'locations.id', '=', 'sales.location_id')
            ->select(['sales.id as source_id', 'sales.public_id as sale', 'sales.location_id', 'locations.name as location', 'sales.status', 'sales.currency_code', 'sales.subtotal_minor as gross_minor', 'sales.discount_minor', 'sales.tax_minor', 'sales.tip_minor', 'sales.refunded_minor as refund_minor', 'sales.deposit_applied_minor', 'sales.balance_minor as outstanding_minor'])
            ->selectRaw('coalesce(sales.completed_at, sales.created_at) as completed_at, sales.subtotal_minor - sales.discount_minor as discounted_minor, sales.subtotal_minor - sales.discount_minor - sales.refunded_minor as net_minor, sales.paid_minor + sales.deposit_applied_minor - sales.refunded_minor as collected_minor')
            ->selectRaw($this->taxBasis().' as tax_basis');
    }

    public function appointmentBase(Business $business, array $scope): Builder
    {
        $q = DB::table('appointments as a')->where('a.business_id', $business->id)->whereIn('a.location_id', $scope['location_ids'])->where('a.starts_at_utc', '>=', $scope['from_utc'])->where('a.starts_at_utc', '<', $scope['until_utc']);
        if ($scope['staff_ids']) {
            $q->whereExists(fn ($s) => $s->selectRaw('1')->from('appointment_segments')->whereColumn('appointment_segments.appointment_id', 'a.id')->where('appointment_segments.business_id', $business->id)->whereIn('appointment_segments.staff_profile_id', $scope['staff_ids']));
        }
        if ($scope['service_ids']) {
            $q->whereExists(fn ($s) => $s->selectRaw('1')->from('appointment_service_lines')->whereColumn('appointment_service_lines.appointment_id', 'a.id')->where('appointment_service_lines.business_id', $business->id)->whereIn('appointment_service_lines.service_id', $scope['service_ids']));
        }

        return $q;
    }

    private function appointments(Business $business, array $scope, string $key): Builder
    {
        $q = $this->appointmentBase($business, $scope)->leftJoin('locations', 'locations.id', '=', 'a.location_id');
        if ($scope['statuses']) {
            $q->whereIn('a.status', $scope['statuses']);
        } elseif ($key === 'cancellation_no_show') {
            $q->whereIn('a.status', ['cancelled_by_client', 'cancelled_by_shop', 'no_show']);
        }
        $duration = DB::getDriverName() === 'sqlite' ? '(julianday(a.ends_at_utc) - julianday(a.starts_at_utc)) * 1440' : 'timestampdiff(second, a.starts_at_utc, a.ends_at_utc) / 60';

        return $q->select(['a.id as source_id', 'a.public_id as appointment', 'a.location_id', 'locations.name as location', 'a.status', 'a.source', 'a.starts_at_utc as starts_at', 'a.ends_at_utc as ends_at'])->selectRaw('round('.$duration.', 0) as duration_minutes')
            ->selectSub(DB::table('appointment_status_history as h')->where('h.business_id', $business->id)->whereColumn('h.appointment_id', 'a.id')->whereColumn('h.status', 'a.status')->whereIn('h.status', ['cancelled_by_client', 'cancelled_by_shop', 'no_show'])->select($scope['can_reasons'] ? 'h.reason' : DB::raw('NULL'))->orderByDesc('h.id')->limit(1), 'reason');
    }

    private function lineBase(Business $business, array $scope): Builder
    {
        $q = DB::table('sale_lines as l')->joinSub($this->baseSales($business, $scope)->select('sales.id', 'sales.public_id', 'sales.completed_at', 'sales.currency_code'), 's', 's.id', '=', 'l.sale_id')->where('l.business_id', $business->id);
        if ($scope['staff_ids']) {
            $q->whereIn('l.staff_profile_id', $scope['staff_ids']);
        }
        if ($scope['service_ids']) {
            $q->whereIn('l.service_id', $scope['service_ids']);
        }
        $refunds = DB::table('sale_line_refunds')->where('business_id', $business->id)->select('sale_line_id')->selectRaw('sum(amount_minor) as allocated_refund_minor')->groupBy('sale_line_id');

        return $q->leftJoinSub($refunds, 'r', 'r.sale_line_id', '=', 'l.id');
    }

    private function lines(Business $business, array $scope, string $key): Builder
    {
        $q = $this->lineBase($business, $scope)->leftJoin('staff_profiles as staff', 'staff.id', '=', 'l.staff_profile_id');
        if ($key === 'discount') {
            $q->where('l.discount_minor', '>', 0);
        } elseif ($key !== 'line_items') {
            $q->where('l.kind', 'service');
        }

        return $q->select(['l.id as source_id', 'l.sale_id', 's.public_id as sale', 'l.service_id', 'l.staff_profile_id as staff_id', 'staff.display_name as staff', 'l.description as service', 'l.description', 'l.kind', 'l.quantity', 'l.discount_minor', 's.completed_at', 's.currency_code'])
            ->selectRaw('l.quantity * l.unit_price_minor as gross_minor, l.quantity * l.unit_price_minor - l.discount_minor as net_minor, coalesce(r.allocated_refund_minor, 0) as allocated_refund_minor');
    }

    private function groupedLines(Business $business, array $scope, string $key): Builder
    {
        $q = $this->lineBase($business, $scope)->leftJoin('staff_profiles as staff', 'staff.id', '=', 'l.staff_profile_id');
        if ($key === 'staff_revenue') {
            $q->select('l.staff_profile_id as staff_id')->selectRaw("coalesce(staff.display_name, 'Unattributed') as staff")->groupBy('l.staff_profile_id', 'staff.display_name');
        } elseif ($key === 'product_sales') {
            $q->where('l.kind', 'product')->select('l.source_id as product_id', 'l.description as product')->groupBy('l.source_id', 'l.description');
        } else {
            $q->where('l.kind', 'service')->select('l.service_id', 'l.description as service')->groupBy('l.service_id', 'l.description');
        }
        if ($key === 'popular_service' && ! $scope['can_finance']) {
            return $q->selectRaw('count(distinct l.sale_id) as sale_count, sum(l.quantity) as quantity');
        }

        return $q->selectRaw('count(distinct l.sale_id) as sale_count, sum(l.quantity) as quantity, sum(l.quantity * l.unit_price_minor) as gross_minor, sum(l.discount_minor) as discount_minor, sum(l.quantity * l.unit_price_minor - l.discount_minor) as net_minor, sum(coalesce(r.allocated_refund_minor, 0)) as allocated_refund_minor');
    }

    private function paymentBase(Business $business, array $scope, bool $saleDate): Builder
    {
        $saleScope = $scope;
        if (! $saleDate) {
            $saleScope['statuses'] = ['open', 'completed'];
        }
        $q = DB::table('payment_transactions as p')->joinSub($this->baseSales($business, $saleScope, $saleDate)->select('sales.id', 'sales.public_id'), 's', 's.id', '=', 'p.sale_id')->where('p.business_id', $business->id)->where('p.status', 'succeeded')->whereIn('p.kind', ['payment', 'refund', 'void'])->where('p.currency_code', $scope['currency_code']);
        if (! $saleDate) {
            $q->where('p.occurred_at', '>=', $scope['from_utc'])->where('p.occurred_at', '<', $scope['until_utc']);
        }
        if ($scope['method']) {
            $q->where('p.method', $scope['method']);
        }

        return $q;
    }

    private function paymentMix(Business $business, array $scope): Builder
    {
        return $this->paymentBase($business, $scope, true)->select('p.method')->selectRaw("count(*) as transaction_count, sum(case when p.kind = 'payment' then p.amount_minor else 0 end) as payment_minor, sum(case when p.kind in ('refund','void') then p.amount_minor else 0 end) as refund_minor, sum(case when p.kind = 'payment' then p.amount_minor else -p.amount_minor end) as collected_minor")->groupBy('p.method');
    }

    private function payments(Business $business, array $scope, string $key): Builder
    {
        $q = $this->paymentBase($business, $scope, false);
        if ($key === 'refund') {
            $q->whereIn('p.kind', ['refund', 'void']);
        }

        return $q->select(['p.id as source_id', 'p.public_id as transaction', 'p.sale_id', 's.public_id as sale', 'p.kind', 'p.method', 'p.reason', 'p.occurred_at', 'p.currency_code'])
            ->selectRaw("case when p.kind = 'payment' then p.amount_minor else 0 end as payment_minor, case when p.kind in ('refund','void') then p.amount_minor else 0 end as refund_minor, case when p.kind = 'payment' then p.amount_minor else -p.amount_minor end as collected_minor");
    }

    private function locations(Business $business, array $scope): Builder
    {
        return $this->baseSales($business, $scope)->join('locations', 'locations.id', '=', 'sales.location_id')->select('sales.location_id', 'locations.name as location')
            ->selectRaw('count(*) as sale_count, sum(sales.subtotal_minor) as gross_minor, sum(sales.discount_minor) as discount_minor, sum(sales.total_minor) as expected_minor, sum(sales.paid_minor + sales.deposit_applied_minor - sales.refunded_minor) as collected_minor, sum(sales.balance_minor) as outstanding_minor')->groupBy('sales.location_id', 'locations.name');
    }

    private function clients(Business $business, array $scope): Builder
    {
        // First sale is scoped to branches the actor is allowed to see, rather
        // than revealing that a client visited an inaccessible branch.
        $first = DB::table('sales')->where('business_id', $business->id)->whereIn('location_id', $scope['allowed_location_ids'])->where('status', 'completed')->whereNotNull('client_id')->select('client_id')->selectRaw('min(completed_at) as first_completed_at')->groupBy('client_id');

        return $this->baseSales($business, $scope)->whereNotNull('sales.client_id')->joinSub($first, 'first_sales', 'first_sales.client_id', '=', 'sales.client_id')->join('clients', fn ($join) => $join->on('clients.id', '=', 'sales.client_id')->where('clients.business_id', $business->id))
            ->select('sales.client_id', 'clients.public_id as client')->selectRaw('case when first_sales.first_completed_at >= ? then \'new\' else \'returning\' end as classification', [$scope['from_utc']])
            ->selectRaw('count(*) as visit_count, sum(sales.paid_minor + sales.deposit_applied_minor - sales.refunded_minor) as revenue_minor, min(sales.completed_at) as first_visit_at, max(sales.completed_at) as last_visit_at')
            ->groupBy('sales.client_id', 'clients.public_id', 'first_sales.first_completed_at');
    }

    private function ledger(Business $business, array $scope, string $key): Builder
    {
        $table = $key === 'tip' ? 'tip_entries' : 'commission_entries';
        $q = DB::table($table.' as e')->where('e.business_id', $business->id)->where('e.currency_code', $scope['currency_code'])->where('e.occurred_at', '>=', $scope['from_utc'])->where('e.occurred_at', '<', $scope['until_utc'])->leftJoin('staff_profiles as staff', 'staff.id', '=', 'e.staff_profile_id');
        if ($key === 'tip') {
            $q->leftJoin('sales as direct_sale', fn ($j) => $j->on('direct_sale.id', '=', 'e.sale_id')->where('direct_sale.business_id', $business->id));
        } else {
            $q->leftJoin('sale_lines as l', 'l.id', '=', 'e.sale_line_id')->leftJoin('sales as direct_sale', fn ($j) => $j->on('direct_sale.id', '=', 'l.sale_id')->where('direct_sale.business_id', $business->id));
        }
        $q->leftJoin('payment_transactions as p', fn ($j) => $j->on('p.id', '=', 'e.payment_transaction_id')->where('p.business_id', $business->id))->leftJoin('sales as payment_sale', fn ($j) => $j->on('payment_sale.id', '=', 'p.sale_id')->where('payment_sale.business_id', $business->id));
        $q->where(function ($location) use ($scope) {
            $location->whereIn(DB::raw('coalesce(direct_sale.location_id, payment_sale.location_id)'), $scope['location_ids']);
            // Unlocated manager adjustments are business-wide. Never infer a
            // historical location from the staff member's current assignment.
            if ($scope['all_locations']) {
                $location->orWhere(fn ($unlocated) => $unlocated->whereNull('direct_sale.id')->whereNull('payment_sale.id'));
            }
        });
        if ($scope['staff_ids']) {
            $q->whereIn('e.staff_profile_id', $scope['staff_ids']);
        }

        return $q->select(['e.id as source_id', 'e.staff_profile_id as staff_id', 'staff.display_name as staff', 'e.type', 'e.amount_minor', 'e.reason', 'e.occurred_at', 'e.currency_code'])
            ->selectRaw('coalesce(direct_sale.public_id, payment_sale.public_id) as sale, ? as ledger, ? as entry_kind', [$key, $key]);
    }

    private function walkIns(Business $business, array $scope): Builder
    {
        $q = DB::table('walk_in_entries as w')->where('w.business_id', $business->id)->whereIn('w.location_id', $scope['location_ids'])->where('w.arrived_at', '>=', $scope['from_utc'])->where('w.arrived_at', '<', $scope['until_utc'])->leftJoin('locations', 'locations.id', '=', 'w.location_id')->leftJoin('staff_profiles as staff', 'staff.id', '=', 'w.assigned_staff_profile_id')->leftJoin('appointments as a', 'a.id', '=', 'w.appointment_id');
        if ($scope['staff_ids']) {
            $q->whereIn('w.assigned_staff_profile_id', $scope['staff_ids']);
        }

        return $q->select(['w.id as source_id', 'w.public_id as entry', 'w.location_id', 'locations.name as location', 'w.status', 'w.arrived_at', 'w.service_started_at', 'w.actual_wait_minutes', 'w.assigned_staff_profile_id as staff_id', 'staff.display_name as staff', 'a.public_id as appointment']);
    }

    private function cashClose(Business $business, array $scope): Builder
    {
        return DB::table('cash_closes as c')->where('c.business_id', $business->id)->whereIn('c.location_id', $scope['location_ids'])->whereBetween('c.business_date', [$scope['from']->toDateString(), $scope['to']->toDateString()])->where('c.currency_code', $scope['currency_code'])->leftJoin('locations', 'locations.id', '=', 'c.location_id')
            ->select(['c.id as source_id', 'c.location_id', 'locations.name as location', 'c.business_date', 'c.opening_cash_minor', 'c.expected_cash_minor', 'c.actual_cash_minor', 'c.variance_minor', 'c.outstanding_balance_minor as outstanding_minor', 'c.currency_code']);
    }

    private function stock(Business $business, array $scope): Builder
    {
        $levels = DB::table('inventory_levels')->where('business_id', $business->id)->whereIn('location_id', $scope['location_ids'])->select('inventory_product_id')->selectRaw('sum(current_stock) as stock')->groupBy('inventory_product_id');
        $stock = $scope['all_locations'] ? 'coalesce(levels.stock, p.current_stock)' : 'coalesce(levels.stock, 0)';

        return DB::table('inventory_products as p')->where('p.business_id', $business->id)->where('p.currency_code', $scope['currency_code'])->leftJoinSub($levels, 'levels', 'levels.inventory_product_id', '=', 'p.id')->leftJoin('product_categories as category', 'category.id', '=', 'p.product_category_id')
            ->select(['p.id as source_id', 'p.public_id as product', 'p.name', 'category.name as category', 'p.sku', 'p.status', 'p.low_stock_threshold', 'p.currency_code'])
            ->selectRaw("{$stock} as current_stock, {$stock} * p.cost_minor as valuation_minor, case when {$stock} <= p.low_stock_threshold then 1 else 0 end as low_stock_count");
    }
}
