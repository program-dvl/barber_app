<?php

namespace App\Domain\Reporting\Services;

final class MetricCatalog
{
    public const VERSION = '1.0.0';

    public const EFFECTIVE_FROM = '2026-08-15T00:00:00+00:00';

    /** @return array<string,array<string,mixed>> */
    public static function definitions(): array
    {
        return [
            'gross_revenue' => ['label' => 'Gross revenue', 'formula' => 'sum(completed sale line quantity × unit price)', 'source' => 'sales + sale_lines', 'drill' => 'sale_line'],
            'net_revenue' => ['label' => 'Net revenue', 'formula' => 'gross revenue − discounts − refunds/voids', 'source' => 'sale_lines + payment_transactions', 'drill' => 'sale/payment'],
            'collected_revenue' => ['label' => 'Collected revenue', 'formula' => 'successful sale tenders + deposits applied − refunds/voids', 'source' => 'payment_transactions + deposit_allocations', 'drill' => 'payment_transaction'],
            'expected_revenue' => ['label' => 'Expected revenue', 'formula' => 'completed/open sale total for the governed local period', 'source' => 'sales', 'drill' => 'sale'],
            'taxes' => ['label' => 'Taxes', 'formula' => 'sum frozen sale tax_minor', 'source' => 'sales.calculation_snapshot', 'drill' => 'sale_line'],
            'discounts' => ['label' => 'Discounts', 'formula' => 'sum frozen sale line discount_minor', 'source' => 'sale_lines', 'drill' => 'sale_line'],
            'refunds' => ['label' => 'Refunds and voids', 'formula' => 'sum succeeded refund and void payment transactions', 'source' => 'payment_transactions', 'drill' => 'payment_transaction'],
            'deposits' => ['label' => 'Deposits applied', 'formula' => 'sum apply deposit allocations; collection alone is not revenue', 'source' => 'deposit_allocations', 'drill' => 'deposit_allocation'],
            'tips' => ['label' => 'Net tips', 'formula' => 'earned tips + refund/void reversals + manager adjustments', 'source' => 'tip_entries', 'drill' => 'tip_entry'],
            'commission' => ['label' => 'Net commission', 'formula' => 'earned commission + refund/void reversals + manager adjustments', 'source' => 'commission_entries', 'drill' => 'commission_entry'],
            'outstanding_balance' => ['label' => 'Outstanding balance', 'formula' => 'sum sale balance after deposits, tenders, refunds, and corrections', 'source' => 'sales', 'drill' => 'sale'],
            'utilisation' => ['label' => 'Staff utilisation', 'formula' => 'occupied booked service minutes ÷ available working minutes', 'source' => 'appointment_segments + staff_availability_rules', 'drill' => 'appointment_segment'],
            'client_classification' => ['label' => 'Client classification', 'formula' => 'new when this is the client first completed appointment/sale in the business; otherwise returning', 'source' => 'appointments + sales', 'drill' => 'client/sale'],
            'no_show_rate' => ['label' => 'No-show rate', 'formula' => 'no-show appointments ÷ appointments eligible to occur, excluding cancelled and rescheduled visits', 'source' => 'appointments', 'drill' => 'appointment'],
            'reconciliation_exception' => ['label' => 'Payment reconciliation exception', 'formula' => 'open payment reconciliation tasks after provider settlement window', 'source' => 'payment_reconciliation_tasks', 'drill' => 'reconciliation_task'],
        ];
    }

    /** @return array<string,array{category:string,purpose:string}> */
    public static function instrumentation(): array
    {
        return [
            'trial.qualified_started' => ['category' => 'acquisition', 'purpose' => 'Verified business trial start'],
            'booking.published' => ['category' => 'activation', 'purpose' => 'First valid published booking path'],
            'booking.first_slot_available' => ['category' => 'activation', 'purpose' => 'First bookable setup time'],
            'subscription.paid' => ['category' => 'activation', 'purpose' => 'Trial-to-paid conversion'],
            'booking.time_selected' => ['category' => 'booking', 'purpose' => 'Booking completion denominator'],
            'booking.completed' => ['category' => 'booking', 'purpose' => 'Completed online booking'],
            'booking.conflict_detected' => ['category' => 'reliability', 'purpose' => 'Invalid overlap leakage check'],
            'notification.critical_outcome' => ['category' => 'reliability', 'purpose' => 'Confirmation/reminder accepted or delivered'],
            'appointment.no_show' => ['category' => 'revenue_protection', 'purpose' => 'Eligible appointment no-show'],
            'payment.reconciliation_opened' => ['category' => 'revenue_protection', 'purpose' => 'Provider mismatch requiring reconciliation'],
            'staff.available_minutes' => ['category' => 'operations', 'purpose' => 'Utilisation denominator'],
            'staff.booked_minutes' => ['category' => 'operations', 'purpose' => 'Utilisation numerator'],
            'subscription.month_end_active' => ['category' => 'retention', 'purpose' => 'Paid logo retention'],
            'shop.weekly_work_completed' => ['category' => 'usage', 'purpose' => 'Calendar or checkout weekly activity'],
            'support.setup_contact' => ['category' => 'support', 'purpose' => 'Human help required before publish'],
            'checkout.completed' => ['category' => 'usage', 'purpose' => 'Completed checkout activity'],
            'checkout.refunded' => ['category' => 'revenue_protection', 'purpose' => 'Refund/void effect'],
            'report.export_completed' => ['category' => 'usage', 'purpose' => 'Trusted report export usage'],
        ];
    }

    /** @return list<string> */
    public static function reportKeys(): array
    {
        return ['appointments', 'sales', 'service_revenue', 'staff_revenue', 'payment_method', 'location', 'discount', 'refund', 'tip', 'commission', 'payroll', 'client_classification', 'cancellation_no_show', 'utilisation', 'popular_service', 'visit_frequency', 'product_sales', 'stock', 'cash_close'];
    }
}
