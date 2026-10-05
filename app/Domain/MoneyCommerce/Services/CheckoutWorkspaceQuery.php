<?php

namespace App\Domain\MoneyCommerce\Services;

use App\Domain\BusinessConfiguration\Models\Service;
use App\Domain\Inventory\Models\InventoryLevel;
use App\Domain\Inventory\Models\InventoryProduct;
use App\Domain\MoneyCommerce\Models\CommerceSetting;
use App\Domain\MoneyCommerce\Models\Deposit;
use App\Domain\MoneyCommerce\Models\Sale;
use App\Domain\MoneyCommerce\Models\SaleReceipt;
use App\Domain\PlatformAccess\Enums\PermissionName;
use App\Domain\PlatformAccess\Models\StaffProfile;
use App\Domain\SchedulingOperations\Models\Appointment;
use Illuminate\Support\Facades\DB;

class CheckoutWorkspaceQuery
{
    public function appointment(Appointment $appointment): array
    {
        $appointment->loadMissing(['client', 'location', 'serviceLines.primaryStaff', 'sale']);

        return ['public_id' => $appointment->public_id, 'reference' => $appointment->booking_reference, 'client' => $appointment->client?->name ?? $appointment->client_name ?? 'Walk-in client',
            'client_public_id' => request()->user()?->can(PermissionName::ClientView->value) ? $appointment->client?->public_id : null,
            'status' => $appointment->status, 'source' => $appointment->source, 'starts_at' => $appointment->starts_at_utc?->toIso8601String(), 'time_zone' => $appointment->time_zone,
            'location' => $appointment->location?->name, 'location_public_id' => $appointment->location?->public_id, 'currency_code' => $appointment->currency_code, 'price_minor' => $appointment->price_minor,
            'staff' => $appointment->serviceLines->pluck('primaryStaff.display_name')->filter()->unique()->values()->all(),
            'services' => $appointment->serviceLines->pluck('name')->all(),
            'sale' => $appointment->sale?->only(['public_id', 'status', 'total_minor', 'paid_minor', 'balance_minor', 'deposit_applied_minor'])];
    }

    public function visit(Appointment $appointment): array
    {
        $context = $this->appointment($appointment);
        $settings = CommerceSetting::query()->where('business_id', $appointment->business_id)->first();
        $items = $appointment->serviceLines->map(fn ($line) => ['key' => 'booked-'.$line->id, 'booked_line_id' => $line->id, 'description' => $line->name, 'kind' => 'service', 'quantity' => 1, 'staff_profile_id' => $line->primary_staff_profile_id, 'staff' => $line->primaryStaff?->display_name, 'base_price_minor' => $line->price_minor, 'unit_price_minor' => $line->price_minor, 'discount_minor' => 0, 'duration_minutes' => $line->bookable_minutes])->values()->all();
        $staff = StaffProfile::query()->forBusiness($appointment->business_id)->where(fn ($q) => $q->where(fn ($active) => $active->where('status', 'active')->whereHas('locations', fn ($branch) => $branch->where('locations.id', $appointment->location_id)))->orWhereIn('id', $appointment->serviceLines->pluck('primary_staff_profile_id')->filter()))->with(['locations' => fn ($q) => $q->where('locations.id', $appointment->location_id)])->orderBy('display_name')->get(['id', 'display_name', 'status'])->map(fn ($person) => [...$person->only(['id', 'display_name', 'status']), 'eligible_for_addition' => $person->status === 'active' && $person->locations->isNotEmpty()]);
        $availableDeposit = Deposit::query()->where('business_id', $appointment->business_id)->where('appointment_id', $appointment->id)->where('currency_code', $appointment->currency_code)->verified()->get()->sum(fn ($deposit) => $deposit->remainingMinor());

        return ['appointment' => $context, 'items' => $items, 'staff' => $staff, 'deposit_available_minor' => $availableDeposit,
            'tax_inclusive' => (bool) ($settings ? ($settings->tax_inclusive ?? false) : true), 'discount_limit_bps' => $settings?->discount_manager_limit_bps ?? 2000,
            'sale' => $appointment->sale ? $this->sale($appointment->sale) : null];
    }

    public function sale(Sale $sale): array
    {
        $sale->loadMissing(['lines', 'transactions', 'appointment.client', 'appointment.location', 'appointment.serviceLines.primaryStaff']);
        $staff = StaffProfile::query()->forBusiness($sale->business_id)->whereIn('id', $sale->lines->pluck('staff_profile_id')->filter())->pluck('display_name', 'id');
        $lineRefunds = DB::table('sale_line_refunds')->where('business_id', $sale->business_id)->whereIn('sale_line_id', $sale->lines->pluck('id'))->get()->groupBy('sale_line_id');
        $snapshotLines = data_get($sale->calculation_snapshot, 'lines', []);
        $transactions = $sale->transactions->sortBy('id')->values()->map(function ($payment) use ($sale) {
            $refunded = $sale->transactions->where('parent_transaction_id', $payment->id)->whereIn('kind', ['refund', 'void'])->sum('amount_minor');

            return [...$payment->only(['id', 'public_id', 'kind', 'status', 'method', 'amount_minor', 'reason']), 'occurred_at' => $payment->occurred_at?->toIso8601String(), 'refundable_minor' => $payment->kind === 'payment' && $payment->status === 'succeeded' ? max(0, $payment->amount_minor - $refunded) : 0, 'provider_managed' => $payment->provider !== null, 'recorded_by' => data_get($payment->evidence, 'actor_name')];
        })->all();
        $receipt = SaleReceipt::query()->where('sale_id', $sale->id)->first();
        $tips = DB::table('sale_tip_allocations')->where('business_id', $sale->business_id)->where('sale_id', $sale->id)->get();
        $tipStaff = StaffProfile::query()->forBusiness($sale->business_id)->whereIn('id', $tips->pluck('staff_profile_id')->filter())->pluck('display_name', 'id');

        return [...$sale->only(['public_id', 'status', 'currency_code', 'subtotal_minor', 'discount_minor', 'tax_minor', 'tip_minor', 'total_minor', 'deposit_applied_minor', 'paid_minor', 'refunded_minor', 'balance_minor']),
            'created_at' => $sale->created_at?->toIso8601String(), 'completed_at' => $sale->completed_at?->toIso8601String(), 'tax_inclusive' => (bool) data_get($sale->calculation_snapshot, 'tax_inclusive', true),
            'appointment' => $sale->appointment ? $this->appointment($sale->appointment) : null,
            'lines' => $sale->lines->values()->map(fn ($line, $index) => [...$line->only(['id', 'kind', 'description', 'quantity', 'unit_price_minor', 'discount_minor', 'tax_rate_bps', 'staff_profile_id']), 'staff' => $staff[$line->staff_profile_id] ?? null,
                'base_price_minor' => data_get($line->source_snapshot, 'base_price_minor', data_get($line->source_snapshot, 'catalog_price_minor', $line->unit_price_minor)),
                'line_total_minor' => data_get($snapshotLines, $index.'.line_total_minor', $line->quantity * $line->unit_price_minor - $line->discount_minor),
                'refunded_minor' => $lineRefunds->get($line->id)?->sum('amount_minor') ?? 0, 'returned_quantity' => $lineRefunds->get($line->id)?->sum('quantity') ?? 0])->all(),
            'tips' => $tips->map(fn ($tip) => ['staff' => $tipStaff[$tip->staff_profile_id] ?? 'Unattributed', 'amount_minor' => $tip->amount_minor])->all(),
            'transactions' => $transactions, 'receipt_number' => $receipt?->receipt_number,
            'adjustment_reason' => data_get($sale->calculation_snapshot, 'adjustment_reason'),
            'deposit_available_minor' => Deposit::query()->where('business_id', $sale->business_id)->where('appointment_id', $sale->appointment_id)->where('currency_code', $sale->currency_code)->verified()->get()->sum(fn ($deposit) => max(0, $deposit->remainingMinor())),
        ];
    }

    public function catalogue(Appointment $appointment, string $search, string $kind): array
    {
        if ($kind === 'product') {
            $products = InventoryProduct::query()->forBusiness($appointment->business_id)->where('status', 'active')->where('currency_code', $appointment->currency_code)->with('category');
            if ($search !== '') {
                $products->where(fn ($q) => $q->where('name', 'like', '%'.$search.'%')->orWhere('sku', 'like', '%'.$search.'%')->orWhere('barcode', 'like', '%'.$search.'%'));
            }
            $products = $products->orderBy('name')->limit(30)->get();
            $levels = InventoryLevel::query()->forBusiness($appointment->business_id)->whereIn('inventory_product_id', $products->pluck('id'))->get()->groupBy('inventory_product_id');

            return $products->map(function ($product) use ($appointment, $levels) {
                $all = $levels->get($product->id);
                $stock = $all ? ($all->firstWhere('location_id', $appointment->location_id)?->current_stock ?? 0) : $product->current_stock;

                return ['public_id' => $product->public_id, 'kind' => 'product', 'name' => $product->name, 'category' => $product->category?->name, 'sku' => $product->sku, 'barcode' => $product->barcode, 'price_minor' => $product->sale_price_minor, 'stock' => $stock];
            })->all();
        }
        $services = Service::query()->forBusiness($appointment->business_id)->where('is_active', true)->where('currency_code', $appointment->currency_code)->whereHas('locations', fn ($q) => $q->where('locations.id', $appointment->location_id)->where('location_service.is_eligible', true))->where(fn ($q) => $q->whereNull('effective_from')->orWhere('effective_from', '<=', now()))->where(fn ($q) => $q->whereNull('effective_until')->orWhere('effective_until', '>', now()))->with(['category', 'staffAssignments' => fn ($q) => $q->where('is_active', true)->where('is_qualified', true)->where(fn ($q) => $q->whereNull('effective_from')->orWhere('effective_from', '<=', now()))->where(fn ($q) => $q->whereNull('effective_until')->orWhere('effective_until', '>', now())), 'locations' => fn ($q) => $q->where('locations.id', $appointment->location_id)]);
        if ($search !== '') {
            $services->where('name', 'like', '%'.$search.'%');
        }
        $activeStaff = StaffProfile::query()->forBusiness($appointment->business_id)->where('status', 'active')->whereHas('locations', fn ($q) => $q->where('locations.id', $appointment->location_id))->pluck('id')->all();

        return $services->orderBy('name')->limit(30)->get()->map(fn ($service) => ['public_id' => $service->public_id, 'kind' => 'service', 'service_kind' => $service->kind, 'name' => $service->name, 'category' => $service->category?->name, 'duration_minutes' => $service->duration_minutes, 'price_minor' => $service->locations->first()?->pivot->price_minor ?? $service->price_minor,
            'variants' => $service->staffAssignments->whereIn('staff_profile_id', $activeStaff)->map(fn ($assignment) => ['staff_profile_id' => $assignment->staff_profile_id, 'price_minor' => $assignment->price_minor ?? $service->locations->first()?->pivot->price_minor ?? $service->price_minor])->values()->all()])->filter(fn ($item) => count($item['variants']) > 0)->values()->all();
    }
}
