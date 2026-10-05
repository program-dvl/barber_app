<?php

namespace App\Domain\MoneyCommerce\Services;

use App\Domain\BusinessConfiguration\Models\Service;
use App\Domain\BusinessConfiguration\Services\EffectiveServiceResolver;
use App\Domain\Inventory\Models\InventoryLevel;
use App\Domain\Inventory\Models\InventoryProduct;
use App\Domain\MoneyCommerce\Models\CommerceSetting;
use App\Domain\MoneyCommerce\Models\Deposit;
use App\Domain\MoneyCommerce\Models\Sale;
use App\Domain\PlatformAccess\Enums\PermissionName;
use App\Domain\PlatformAccess\Models\StaffProfile;
use App\Domain\SchedulingOperations\Models\Appointment;
use App\Domain\SchedulingOperations\Models\AppointmentServiceLine;
use App\Support\Audit\AuditWriter;
use App\Support\Money\MoneyCalculator;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Resolves trusted source rows and previews the exact authoritative calculation. */
class CheckoutBasketService
{
    public function __construct(private readonly MoneyCalculator $money, private readonly EffectiveServiceResolver $services, private readonly CheckoutService $checkout, private readonly TenantContext $tenancy, private readonly AuditWriter $audit) {}

    public function preview(Appointment $appointment, array $data): array
    {
        $appointment->loadMissing(['serviceLines.primaryStaff', 'location']);
        $settings = CommerceSetting::query()->where('business_id', $appointment->business_id)->first();
        $elevated = $this->tenancy->membership()?->hasRole(['owner', 'manager'], 'web') ?? false;
        $canDiscount = request()->user()?->can(PermissionName::DiscountApply->value) ?? false;
        $lines = [];
        $seen = [];
        $changes = [];
        $stock = [];
        $addedAddons = [];
        foreach ($data['items'] as $index => $item) {
            if (count(array_filter([$item['booked_line_id'] ?? null, $item['service_public_id'] ?? null, $item['product_public_id'] ?? null])) !== 1) {
                $this->fail('Each sale item must have one source.');
            }
            $staffId = $item['staff_profile_id'] ?? null;
            $historicalStaff = ! empty($item['booked_line_id']) && $appointment->serviceLines->firstWhere('id', (int) $item['booked_line_id'])?->primary_staff_profile_id === $staffId;
            if ($staffId && ! $historicalStaff && ! StaffProfile::query()->forBusiness($appointment->business_id)->whereKey($staffId)->whereHas('locations', fn ($q) => $q->where('locations.id', $appointment->location_id))->exists()) {
                $this->fail('Staff must belong to this business and location.');
            }
            if (empty($item['product_public_id']) && (int) $item['quantity'] !== 1) {
                $this->fail('Service quantity must be one; add another performed service separately.');
            }
            if (! empty($item['booked_line_id'])) {
                $booked = $appointment->serviceLines->firstWhere('id', (int) $item['booked_line_id']);
                if (! $booked || in_array($booked->id, $seen, true)) {
                    $this->fail('A booked service is missing or repeated. Refresh the visit.');
                }
                $seen[] = $booked->id;
                // Retain historical primary attribution unless an elevated user explicitly changes it.
                $staffId = array_key_exists('staff_profile_id', $item) ? $staffId : $booked->primary_staff_profile_id;
                if ($staffId !== $booked->primary_staff_profile_id) {
                    if (! $elevated || ! $staffId) {
                        $this->fail('Only an owner or manager may change booked staff attribution.');
                    }
                    $this->services->resolve(Service::query()->forBusiness($appointment->business_id)->findOrFail($booked->service_id), StaffProfile::query()->findOrFail($staffId), $appointment->location);
                    $changes[] = 'Staff attribution changed';
                }
                $line = ['kind' => 'service', 'source_type' => AppointmentServiceLine::class, 'source_id' => $booked->id, 'service_id' => $booked->service_id, 'description' => $booked->name, 'quantity' => 1, 'unit_price_minor' => $booked->price_minor, 'tax_rate_bps' => (int) data_get($booked->configuration_snapshot, 'taxRateBps', $settings?->default_tax_rate_bps ?? 0), 'source_snapshot' => [...$booked->configuration_snapshot, 'service_id' => $booked->service_id, 'booked_line_id' => $booked->id, 'catalog_price_minor' => $booked->price_minor]];
            } elseif (! empty($item['service_public_id'])) {
                if (! $staffId) {
                    $this->fail('Choose the staff member who performed the added service.');
                }
                $service = Service::query()->forBusiness($appointment->business_id)->where('public_id', $item['service_public_id'])->firstOrFail();
                if ($service->currency_code !== $appointment->currency_code) {
                    $this->fail('Service currency must match this visit.');
                }
                if ($service->kind === 'addon') {
                    $addedAddons[] = $service->id;
                }
                $effective = $this->services->resolve($service, StaffProfile::query()->findOrFail($staffId), $appointment->location);
                $line = ['kind' => 'service', 'source_type' => Service::class, 'source_id' => $service->id, 'service_id' => $service->id, 'description' => $service->name, 'quantity' => 1, 'unit_price_minor' => $effective->priceMinor, 'tax_rate_bps' => $settings?->default_tax_rate_bps ?? 0, 'source_snapshot' => [...collect($effective->snapshot())->except('resolvedAt')->all(), 'service_id' => $service->id, 'catalog_price_minor' => $effective->priceMinor]];
            } elseif (! empty($item['product_public_id'])) {
                $product = InventoryProduct::query()->forBusiness($appointment->business_id)->where('public_id', $item['product_public_id'])->where('status', 'active')->firstOrFail();
                if ($product->currency_code !== $appointment->currency_code) {
                    $this->fail('Product currency must match this visit.');
                }
                $quantity = (int) ($item['quantity'] ?? 1);
                $stock[$product->id] = ($stock[$product->id] ?? 0) + $quantity;
                if ($stock[$product->id] > $this->availableStock($product, $appointment->location_id)) {
                    $this->fail("Insufficient stock for {$product->name} at this location.");
                }
                $line = ['kind' => 'product', 'source_type' => InventoryProduct::class, 'source_id' => $product->id, 'description' => $product->name, 'quantity' => $quantity, 'unit_price_minor' => $product->sale_price_minor, 'tax_rate_bps' => $product->tax_rate_bps, 'source_snapshot' => ['product_id' => $product->id, 'sku' => $product->sku, 'barcode' => $product->barcode, 'cost_minor' => $product->cost_minor, 'catalog_price_minor' => $product->sale_price_minor]];
            } else {
                $this->fail('Choose a booked service, catalogue service or retail product.');
            }
            $basePrice = $line['unit_price_minor'];
            if (isset($item['unit_price_minor']) && (int) $item['unit_price_minor'] !== $basePrice) {
                if (! $elevated) {
                    $this->fail('Only an owner or manager may override a price.');
                }
                $line['unit_price_minor'] = (int) $item['unit_price_minor'];
                $changes[] = 'Price overridden';
            }
            $discount = (int) ($item['discount_minor'] ?? 0);
            if ($discount > 0 && ! $canDiscount) {
                $this->fail('Your role cannot apply discounts. Ask a manager.');
            }
            $lines[] = [...$line, 'staff_profile_id' => $staffId, 'discount_minor' => $discount, 'source_snapshot' => [...$line['source_snapshot'], 'base_price_minor' => $basePrice, 'adjustment_reason' => $data['reason'] ?? null]];
        }
        foreach ($addedAddons as $addonId) {
            if (! DB::table('service_addons')->where('business_id', $appointment->business_id)->where('addon_service_id', $addonId)->whereIn('service_id', array_column($lines, 'service_id'))->exists()) {
                $this->fail('Add this add-on together with an eligible parent service.');
            }
        }
        if (count($seen) !== $appointment->serviceLines->count()) {
            $changes[] = 'Booked service removed';
        }
        if ($changes && trim($data['reason'] ?? '') === '') {
            $this->fail('Add a reason for service removal, price or staff changes.');
        }
        $tips = array_values(array_filter($data['tips'] ?? [], fn ($tip) => $tip['amount_minor'] > 0));
        if (count(array_unique(array_column($tips, 'staff_profile_id'))) !== count($tips)) {
            $this->fail('Use one tip allocation for each staff member.');
        }
        foreach ($tips as $tip) {
            if (! StaffProfile::query()->forBusiness($appointment->business_id)->whereKey($tip['staff_profile_id'])->whereHas('locations', fn ($q) => $q->where('locations.id', $appointment->location_id))->exists()) {
                $this->fail('Choose a valid location staff member for each tip.');
            }
        }
        $calculation = $this->money->calculate($lines, $appointment->currency_code, (bool) ($settings ? ($settings->tax_inclusive ?? false) : true), array_sum(array_column($tips, 'amount_minor')));
        $requiresApproval = $calculation['subtotal_minor'] > 0 && $calculation['discount_minor'] * 10000 > $calculation['subtotal_minor'] * ($settings?->discount_manager_limit_bps ?? 2000);
        if ($requiresApproval && (! $elevated || trim($data['reason'] ?? '') === '')) {
            $this->fail('This discount exceeds the configured limit. An owner or manager must review it and give a reason.');
        }
        $deposits = Deposit::query()->where('business_id', $appointment->business_id)->where('appointment_id', $appointment->id)->where('currency_code', $appointment->currency_code)->verified()->orderBy('id')->get();
        $available = $deposits->sum(fn ($deposit) => $deposit->remainingMinor());
        $apply = ($data['apply_deposit'] ?? true) ? min($available, $calculation['total_minor']) : 0;
        $quote = [...$calculation, 'deposit_available_minor' => $available, 'deposit_applied_minor' => $apply, 'deposit_excess_minor' => max(0, $available - $apply), 'balance_minor' => $calculation['total_minor'] - $apply, 'tips' => $tips, 'changes' => $changes, 'approval_required' => $requiresApproval];
        $quote['quote_key'] = hash('sha256', json_encode([$appointment->id, $quote, $data['reason'] ?? null], JSON_THROW_ON_ERROR));

        return $quote;
    }

    public function prepare(Appointment $appointment, array $data): Sale
    {
        return DB::transaction(function () use ($appointment, $data) {
            $appointment = Appointment::query()->lockForUpdate()->findOrFail($appointment->id);
            if ($appointment->status !== 'completed') {
                $this->fail('Complete the appointment before checkout.');
            }
            $existing = Sale::query()->where('appointment_id', $appointment->id)->where('business_id', $appointment->business_id)->first();
            if ($existing) {
                if (data_get($existing->calculation_snapshot, 'workspace_quote_key') !== $data['quote_key']) {
                    $this->fail('This visit already has a saved sale. Reload it before recording payment.');
                }

                return $existing;
            }
            $quote = $this->preview($appointment, $data);
            if (! hash_equals($quote['quote_key'], $data['quote_key'])) {
                $this->fail('Prices, stock or deposit changed. Review the refreshed totals before saving.');
            }
            $sale = $this->checkout->createSale($appointment, $quote['lines'], $quote['tips'], true, ['workspace_quote_key' => $quote['quote_key'], 'adjustment_reason' => $data['reason'] ?? null]);
            if ($quote['deposit_applied_minor'] > 0) {
                $remaining = $quote['deposit_applied_minor'];
                foreach (Deposit::query()->where('business_id', $sale->business_id)->where('appointment_id', $appointment->id)->where('currency_code', $sale->currency_code)->verified()->orderBy('id')->lockForUpdate()->get() as $deposit) {
                    $apply = min($remaining, $deposit->remainingMinor());
                    if ($apply > 0) {
                        $sale = $this->checkout->applyDeposit($sale, $deposit, $apply, "workspace:{$sale->id}:deposit:{$deposit->id}");
                        $remaining -= $apply;
                    }
                }
            }
            if ($sale->balance_minor === 0) {
                $this->checkout->completeIfPaid($sale);
            }
            $this->audit->write('sale.prepared', target: $sale, reason: $data['reason'] ?? null, after: ['total_minor' => $sale->total_minor, 'discount_minor' => $sale->discount_minor, 'deposit_applied_minor' => $sale->deposit_applied_minor, 'changes' => $quote['changes']], metadata: ['quote_key' => $quote['quote_key']]);

            return $sale->fresh();
        });
    }

    public function availableStock(InventoryProduct $product, int $locationId): int
    {
        $level = InventoryLevel::query()->forBusiness($product->business_id)->where('inventory_product_id', $product->id)->where('location_id', $locationId)->first();

        return $level?->current_stock ?? (InventoryLevel::query()->forBusiness($product->business_id)->where('inventory_product_id', $product->id)->exists() ? 0 : $product->current_stock);
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['checkout' => $message]);
    }
}
