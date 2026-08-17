<?php

namespace App\Domain\Inventory\Services;

use App\Domain\Inventory\Models\InventoryLevel;
use App\Domain\Inventory\Models\InventoryMovement;
use App\Domain\Inventory\Models\InventoryProduct;
use App\Domain\MoneyCommerce\Models\PaymentTransaction;
use App\Domain\MoneyCommerce\Models\Sale;
use App\Domain\MoneyCommerce\Models\SaleLine;
use App\Domain\PlatformAccess\Models\Location;
use App\Domain\PlatformAccess\Models\Membership;
use DomainException;
use Illuminate\Support\Facades\DB;

class InventoryLedger
{
    public function receive(InventoryProduct $product, Location $location, int $quantity, Membership $actor, string $reason, string $idempotencyKey): InventoryMovement
    {
        if ($quantity <= 0) {
            throw new DomainException('A stock receipt quantity must be positive.');
        }

        return $this->move($product, $location, $quantity, 'receipt', $idempotencyKey, $reason, $actor);
    }

    public function adjust(InventoryProduct $product, Location $location, int $delta, Membership $actor, string $reason, string $idempotencyKey): InventoryMovement
    {
        if ($delta === 0 || trim($reason) === '') {
            throw new DomainException('A manual stock adjustment requires a non-zero quantity and reason.');
        }

        return $this->move($product, $location, $delta, 'adjustment', $idempotencyKey, $reason, $actor);
    }

    public function importOpeningStock(InventoryProduct $product, Location $location, int $quantity, string $idempotencyKey): InventoryMovement
    {
        if ($quantity < 0) {
            throw new DomainException('Imported opening stock cannot be negative.');
        }

        return $this->move($product, $location, $quantity, 'import', $idempotencyKey, 'Opening stock imported from reviewed product CSV.');
    }

    public function deductCompletedSale(Sale $sale): void
    {
        $sale->loadMissing('lines');
        foreach ($sale->lines->where('kind', 'product') as $line) {
            if ($line->source_type !== InventoryProduct::class || ! $line->source_id) {
                continue;
            }
            $product = InventoryProduct::query()->forBusiness($sale->business_id)->findOrFail($line->source_id);
            $location = Location::query()->forBusiness($sale->business_id)->findOrFail($sale->location_id);
            $this->move($product, $location, -$line->quantity, 'sale', "sale:{$sale->id}:line:{$line->id}", 'Completed sale deduction.', null, $line);
        }
    }

    public function applyRefundDisposition(SaleLine $line, PaymentTransaction $refund, int $quantity, string $disposition, string $reason): ?InventoryMovement
    {
        if ($line->kind !== 'product' || $line->source_type !== InventoryProduct::class || ! $line->source_id) {
            if ($disposition !== 'not_applicable') {
                throw new DomainException('Only an inventory product can have a stock disposition.');
            }

            return null;
        }
        if (! in_array($disposition, ['restock', 'write_off', 'customer_keeps'], true) || $quantity < 0) {
            throw new DomainException('A product refund requires restock, write_off, or customer_keeps disposition.');
        }

        $sale = Sale::query()->forBusiness($refund->business_id)->findOrFail($line->sale_id);
        $product = InventoryProduct::query()->forBusiness($refund->business_id)->findOrFail($line->source_id);
        $location = Location::query()->forBusiness($refund->business_id)->findOrFail($sale->location_id);
        $delta = $disposition === 'restock' ? $quantity : 0;

        return $this->move($product, $location, $delta, 'refund', "refund:{$refund->id}:line:{$line->id}", $reason, null, $line, $refund, $disposition);
    }

    private function move(InventoryProduct $product, Location $location, int $delta, string $type, string $idempotencyKey, string $reason, ?Membership $actor = null, ?SaleLine $line = null, ?PaymentTransaction $payment = null, ?string $disposition = null): InventoryMovement
    {
        if ($product->business_id !== $location->business_id || ($actor && $actor->business_id !== $product->business_id)) {
            throw new DomainException('Inventory movement tenant lineage does not match.');
        }

        return DB::transaction(function () use ($product, $location, $delta, $type, $idempotencyKey, $reason, $actor, $line, $payment, $disposition): InventoryMovement {
            $existing = InventoryMovement::query()->forBusiness($product->business_id)->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }
            $locked = InventoryProduct::query()->forBusiness($product->business_id)->lockForUpdate()->findOrFail($product->id);
            $existingLevel = InventoryLevel::query()->forBusiness($product->business_id)->where('location_id', $location->id)->where('inventory_product_id', $locked->id)->lockForUpdate()->first();
            if (! $existingLevel) {
                $hasAnotherLevel = InventoryLevel::query()->forBusiness($product->business_id)->where('inventory_product_id', $locked->id)->exists();
                $existingLevel = InventoryLevel::query()->create(['business_id' => $product->business_id, 'location_id' => $location->id, 'inventory_product_id' => $locked->id, 'current_stock' => $hasAnotherLevel ? 0 : $locked->current_stock]);
            }
            $before = $existingLevel->current_stock;
            $after = $before + $delta;
            if ($after < 0) {
                throw new DomainException("Insufficient stock for {$locked->name}.");
            }
            $existingLevel->update(['current_stock' => $after]);
            $locked->update(['current_stock' => (int) InventoryLevel::query()->forBusiness($product->business_id)->where('inventory_product_id', $locked->id)->sum('current_stock')]);

            return InventoryMovement::query()->create([
                'business_id' => $locked->business_id,
                'location_id' => $location->id,
                'inventory_product_id' => $locked->id,
                'sale_line_id' => $line?->id,
                'payment_transaction_id' => $payment?->id,
                'actor_membership_id' => $actor?->id,
                'type' => $type,
                'disposition' => $disposition,
                'quantity_delta' => $delta,
                'quantity_before' => $before,
                'quantity_after' => $after,
                'idempotency_key' => $idempotencyKey,
                'reason' => $reason,
                'occurred_at' => now(),
            ]);
        });
    }
}
