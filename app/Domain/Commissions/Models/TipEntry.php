<?php

namespace App\Domain\Commissions\Models;

use App\Support\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class TipEntry extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['business_id', 'sale_id', 'staff_profile_id', 'payment_transaction_id', 'approved_by_membership_id', 'type', 'amount_minor', 'currency_code', 'idempotency_key', 'reason', 'occurred_at'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Tip entries are append-only.'));
        static::deleting(fn () => throw new LogicException('Tip entries are append-only.'));
    }

    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'occurred_at' => 'immutable_datetime'];
    }
}
