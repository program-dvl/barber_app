<?php

namespace App\Domain\Reporting\Services;

/** Presentation and filter contracts; query projections never infer financial definitions. */
final class ReportCatalog
{
    public static function all(): array
    {
        $items = [
            'line_items' => ['Sales & revenue', 'Sale line items', 'Frozen service and retail items, with original performers and return allocations.', ['net_minor', 'quantity', 'discount_minor', 'allocated_refund_minor'], ['description', 'kind', 'staff', 'quantity', 'net_minor', 'allocated_refund_minor']],
            'overview' => ['Overview', 'Business overview', 'Follow the changes behind your business performance.', ['collected_minor', 'row_count', 'average_ticket_minor', 'discount_minor'], ['completed_at', 'sale', 'location', 'collected_minor', 'outstanding_minor']],
            'sales' => ['Sales & revenue', 'Sales detail', 'Recorded sale values, receipts and outstanding balances.', ['collected_minor', 'row_count', 'average_ticket_minor', 'outstanding_minor'], ['completed_at', 'sale', 'location', 'gross_minor', 'discount_minor', 'collected_minor', 'outstanding_minor']],
            'service_revenue' => ['Services', 'Service sales', 'Historical service lines, with recorded prices and performers.', ['net_minor', 'quantity', 'discount_minor', 'allocated_refund_minor'], ['service', 'staff', 'quantity', 'gross_minor', 'discount_minor', 'net_minor']],
            'staff_revenue' => ['Team', 'Team sales', 'Sales attributed to recorded performers; no composite scores.', ['net_minor', 'quantity', 'discount_minor', 'allocated_refund_minor'], ['staff', 'sale_count', 'quantity', 'gross_minor', 'discount_minor', 'net_minor']],
            'payment_method' => ['Payments', 'Payment mix', 'Successful tenders and returns for sales in the selected sale period.', ['collected_minor', 'transaction_count', 'payment_minor', 'refund_minor'], ['method', 'transaction_count', 'payment_minor', 'refund_minor', 'collected_minor']],
            'payment_activity' => ['Payments', 'Payment activity', 'Payments and returns recorded during this period, including partial sales.', ['collected_minor', 'row_count', 'payment_minor', 'refund_minor'], ['occurred_at', 'transaction', 'kind', 'method', 'payment_minor', 'refund_minor', 'collected_minor']],
            'location' => ['Sales & revenue', 'Location comparison', 'Compare permitted branches on the same local date boundaries.', ['collected_minor', 'sale_count', 'discount_minor', 'outstanding_minor'], ['location', 'sale_count', 'gross_minor', 'discount_minor', 'collected_minor', 'outstanding_minor']],
            'discount' => ['Sales & revenue', 'Discounts', 'Frozen line discounts; investigate the original sale without inferring intent.', ['discount_minor', 'row_count', 'gross_minor'], ['completed_at', 'description', 'staff', 'gross_minor', 'discount_minor']],
            'refund' => ['Payments', 'Refunds & voids', 'Successful returns by transaction date, with the recorded reason.', ['refund_minor', 'row_count'], ['occurred_at', 'transaction', 'kind', 'method', 'refund_minor', 'reason']],
            'tax' => ['Payments', 'Tax & sale values', 'Frozen transaction values and tax basis; returns shown separately.', ['tax_minor', 'discount_minor', 'refund_minor', 'tip_minor'], ['completed_at', 'sale', 'tax_basis', 'gross_minor', 'discount_minor', 'tax_minor', 'tip_minor', 'refund_minor']],
            'appointments' => ['Appointments', 'Appointment analysis', 'Scheduled visits by status, source and local date.', ['row_count', 'completed_count', 'no_show_percent', 'average_duration_minutes'], ['starts_at', 'appointment', 'location', 'status', 'source', 'duration_minutes']],
            'cancellation_no_show' => ['Appointments', 'Cancellations & no-shows', 'Recorded outcomes, with filtered and explicit denominators.', ['cancelled_count', 'no_show_count', 'no_show_percent', 'cancellation_percent'], ['starts_at', 'appointment', 'status', 'source', 'reason']],
            'walk_ins' => ['Appointments', 'Walk-in analysis', 'Arrivals, recorded service starts and measured wait times.', ['row_count', 'started_count', 'average_wait_minutes', 'longest_wait_minutes'], ['arrived_at', 'entry', 'location', 'status', 'staff', 'actual_wait_minutes']],
            'client_classification' => ['Clients', 'New & returning clients', 'Distinct paying clients, classified at the start of this period.', ['new_count', 'returning_count', 'row_count', 'visit_count'], ['client', 'classification', 'visit_count', 'revenue_minor', 'last_visit_at']],
            'visit_frequency' => ['Clients', 'Client frequency', 'Completed sales per identified client; anonymous sales excluded.', ['row_count', 'visit_count', 'revenue_minor'], ['client', 'visit_count', 'revenue_minor', 'first_visit_at', 'last_visit_at']],
            'popular_service' => ['Services', 'Service mix', 'Compare recorded service names and units sold.', ['quantity', 'gross_minor', 'discount_minor', 'net_minor'], ['service', 'sale_count', 'quantity', 'gross_minor', 'discount_minor', 'net_minor']],
            'utilisation' => ['Team', 'Capacity & utilisation', 'Occupied service time against configured bookable working windows.', ['utilisation_percent', 'available_minutes', 'booked_minutes', 'free_minutes'], ['staff', 'available_minutes', 'recorded_minutes', 'booked_minutes', 'free_minutes', 'utilisation_percent']],
            'tip' => ['Team', 'Tip statement', 'Earned tips, append-only reversals and recorded adjustments.', ['amount_minor', 'row_count'], ['occurred_at', 'staff', 'type', 'amount_minor', 'reason']],
            'commission' => ['Team', 'Commission statement', 'The existing commission ledger, including reversals and adjustments.', ['amount_minor', 'row_count'], ['occurred_at', 'staff', 'type', 'amount_minor', 'reason']],
            'payroll' => ['Team', 'Compensation records', 'Separate commission and tip source entries; no payroll calculation.', ['commission_minor', 'tips_minor', 'row_count'], ['occurred_at', 'staff', 'ledger', 'type', 'amount_minor', 'reason']],
            'cash_close' => ['Operations', 'Cash reconciliation', 'Immutable cash closes with expected, actual and variance.', ['expected_cash_minor', 'actual_cash_minor', 'variance_minor', 'row_count'], ['business_date', 'location', 'opening_cash_minor', 'expected_cash_minor', 'actual_cash_minor', 'variance_minor']],
            'product_sales' => ['Products', 'Retail sales', 'Recorded product line values.', ['quantity', 'net_minor', 'discount_minor'], ['product', 'quantity', 'gross_minor', 'discount_minor', 'net_minor']],
            'stock' => ['Products', 'Stock', 'Current scoped stock, separate from sale-period reporting.', ['valuation_minor', 'low_stock_count'], ['name', 'category', 'current_stock', 'low_stock_threshold', 'valuation_minor']],
        ];
        $definitions = self::definitions();
        $out = [];
        foreach ($items as $key => [$group, $title, $description, $metrics, $columns]) {
            $filters = ['location'];
            if (! in_array($key, ['stock', 'cash_close'], true)) {
                $filters[] = 'staff';
            }
            if (in_array($key, ['line_items', 'overview', 'sales', 'service_revenue', 'staff_revenue', 'popular_service', 'appointments', 'cancellation_no_show', 'discount', 'payment_method', 'payment_activity', 'refund', 'client_classification', 'visit_frequency', 'location', 'tax'], true)) {
                $filters[] = 'service';
            }
            if (in_array($key, ['overview', 'sales', 'payment_method', 'appointments', 'cancellation_no_show'], true)) {
                $filters[] = 'status';
            }
            if (in_array($key, ['payment_method', 'payment_activity', 'refund', 'sales'], true)) {
                $filters[] = 'method';
            }
            $out[$key] = compact('group', 'title', 'description', 'metrics', 'columns', 'filters') + ['definitions' => array_intersect_key($definitions, array_flip($metrics))];
        }

        return $out;
    }

    public static function definitions(): array
    {
        return [
            'row_count' => 'All matching records, independently of the displayed page.',
            'collected_minor' => 'Sale-period view: recorded paid value + deposits applied − all returns for the selected sales. Payment activity instead uses transaction occurrence dates and excludes deposit collections.',
            'gross_minor' => 'Frozen quantity × unit price. Prices retain each sale’s inclusive/exclusive tax basis; excludes tips.',
            'net_minor' => 'Frozen line prices − frozen discounts, before returns. Tax may be included in recorded prices. It is not tax-exclusive revenue.',
            'average_ticket_minor' => 'Frozen subtotal − discounts, divided by matching sales. Before returns and tips; retains recorded tax basis.',
            'outstanding_minor' => 'Recorded outstanding balance on matching sales. Completed sales usually have zero balance; include open sales to inspect partial payments.',
            'discount_minor' => 'Sum of recorded discounts. Uses the immutable line allocation and never current catalogue prices.',
            'refund_minor' => 'Successful refund and void amounts. Refunds & voids uses event dates; sales reports use all returns on the selected sales. Can include tax and tips.',
            'allocated_refund_minor' => 'Recorded item-return allocations on selected sale lines. Unallocated return amounts are not inferred or attributed to a performer.',
            'payment_minor' => 'Successful recorded payment tenders; excludes deposit collection and failed transactions.',
            'tax_minor' => 'Original frozen sale tax. Returns are separate because tax-return allocations are not recorded. Reflects platform transactions.',
            'tip_minor' => 'Original frozen sale tips, before subsequent reversals. Net allocated tips are available in the permissioned Tip statement.',
            'no_show_percent' => 'No-shows ÷ all filtered visits excluding cancellations and rescheduled originals. No eligible visits yields an unavailable rate.',
            'cancellation_percent' => 'Cancelled visits ÷ all filtered visits excluding rescheduled originals. Describes recorded outcomes without assigning cause.',
            'utilisation_percent' => 'Unioned occupied service minutes intersecting configured bookable windows ÷ configured available minutes. Breaks, leave and branch closures are excluded. Zero availability yields an unavailable rate.',
            'available_minutes' => 'Configured working windows intersected with branch opening hours, minus breaks, leave and schedule blocks. Based on retained configuration, not a historical attendance ledger.',
            'booked_minutes' => 'Unioned occupied segments within available windows; excludes cancelled, no-show and rescheduled visits. Overlaps are counted once.',
            'free_minutes' => 'Configured available minutes − occupied service minutes. Past empty time is unused capacity; future time is unbooked capacity.',
            'new_count' => 'Distinct clients with a first completed sale during the selected period, within accessible branches. Anonymous sales excluded.',
            'returning_count' => 'Distinct clients with a completed sale before the selected period in accessible branches, and a sale during this period.',
            'visit_count' => 'Completed sale count per identified client. This is paying-client frequency, not appointment retention.',
            'average_duration_minutes' => 'Mean stored scheduled duration of matching appointments, across included statuses.',
            'average_wait_minutes' => 'Mean recorded actual_wait_minutes on entries with a measured wait; unmeasured entries excluded.',
            'longest_wait_minutes' => 'Maximum recorded actual wait; unmeasured entries excluded.',
            'started_count' => 'Arrivals in this period with a recorded service_started_at. This is a recorded-start count, not a funnel forecast.',
            'amount_minor' => 'Append-only earned entries + refund/void reversals + recorded adjustments, using existing compensation logic.',
            'commission_minor' => 'Sum of existing commission entries. No commission rule is recalculated in Reports.',
            'tips_minor' => 'Sum of existing tip entries, separately from commission.',
        ];
    }
}
