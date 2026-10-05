<?php

namespace App\Domain\MoneyCommerce\Services;

use App\Domain\MoneyCommerce\Models\Sale;
use App\Domain\MoneyCommerce\Models\SaleReceipt;
use App\Domain\PlatformAccess\Models\StaffProfile;
use Illuminate\Support\Facades\DB;

class ReceiptService
{
    public function issue(Sale $sale): SaleReceipt
    {
        return DB::transaction(function () use ($sale): SaleReceipt {
            $sale = Sale::query()->lockForUpdate()->findOrFail($sale->id);
            $existing = SaleReceipt::query()->where('sale_id', $sale->id)->first();
            if ($existing) {
                return $existing;
            }
            $sale->loadMissing(['lines', 'appointment.business', 'appointment.client', 'appointment.location', 'transactions']);
            $staff = StaffProfile::query()->forBusiness($sale->business_id)->whereIn('id', $sale->lines->pluck('staff_profile_id')->filter())->pluck('display_name', 'id');
            $snapshot = ['receipt_version' => 2, 'client' => $sale->appointment?->client?->name ?? $sale->appointment?->client_name, 'visit_reference' => $sale->appointment?->booking_reference, 'location' => $sale->appointment?->location?->name, 'tax_inclusive' => data_get($sale->calculation_snapshot, 'tax_inclusive', true), 'issued_at' => now()->utc()->toIso8601String(), 'business' => $sale->appointment?->business?->only(['name', 'address', 'phone', 'email', 'currency_code']), 'sale' => $sale->only(['public_id', 'currency_code', 'subtotal_minor', 'discount_minor', 'tax_minor', 'tip_minor', 'total_minor', 'deposit_applied_minor', 'paid_minor', 'refunded_minor', 'balance_minor']), 'lines' => $sale->lines->values()->map(fn ($line, $index) => [...$line->only(['kind', 'description', 'quantity', 'unit_price_minor', 'discount_minor', 'tax_rate_bps']), 'staff' => $staff[$line->staff_profile_id] ?? null, 'line_total_minor' => data_get($sale->calculation_snapshot, 'lines.'.$index.'.line_total_minor', $line->quantity * $line->unit_price_minor - $line->discount_minor)])->all(), 'payments' => $sale->transactions->map->only(['kind', 'method', 'amount_minor', 'occurred_at', 'reason'])->all()];

            return SaleReceipt::query()->create(['business_id' => $sale->business_id, 'sale_id' => $sale->id, 'receipt_number' => 'GH-'.now()->format('Ymd').'-'.$sale->public_id, 'content_hash' => hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR)), 'snapshot' => $snapshot, 'issued_at' => now()]);
        });
    }
}
