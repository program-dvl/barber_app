<?php

namespace App\Domain\MoneyCommerce\Models;

use App\Support\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class DepositAllocation extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['business_id', 'deposit_id', 'appointment_id', 'sale_id', 'payment_transaction_id', 'action', 'amount_minor', 'idempotency_key', 'reason', 'evidence', 'occurred_at'];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'evidence' => 'array', 'occurred_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Deposit allocations are append-only.'));
        static::deleting(fn () => throw new LogicException('Deposit allocations are append-only.'));
    }
}
