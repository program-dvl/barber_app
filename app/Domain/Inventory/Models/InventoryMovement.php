<?php

namespace App\Domain\Inventory\Models;

use App\Support\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class InventoryMovement extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['business_id', 'location_id', 'inventory_product_id', 'sale_line_id', 'payment_transaction_id', 'actor_membership_id', 'type', 'disposition', 'quantity_delta', 'quantity_before', 'quantity_after', 'idempotency_key', 'reason', 'occurred_at'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Inventory movements are append-only.'));
        static::deleting(fn () => throw new LogicException('Inventory movements are append-only.'));
    }

    protected function casts(): array
    {
        return ['quantity_delta' => 'integer', 'quantity_before' => 'integer', 'quantity_after' => 'integer', 'occurred_at' => 'immutable_datetime'];
    }
}
