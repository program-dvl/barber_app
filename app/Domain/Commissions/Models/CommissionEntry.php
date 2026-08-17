<?php

namespace App\Domain\Commissions\Models;

use App\Support\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class CommissionEntry extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['business_id', 'staff_profile_id', 'sale_line_id', 'commission_rule_id', 'payment_transaction_id', 'approved_by_membership_id', 'type', 'base_minor', 'rate_bps', 'amount_minor', 'currency_code', 'idempotency_key', 'reason', 'occurred_at'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Commission entries are append-only.'));
        static::deleting(fn () => throw new LogicException('Commission entries are append-only.'));
    }

    protected function casts(): array
    {
        return ['base_minor' => 'integer', 'rate_bps' => 'integer', 'amount_minor' => 'integer', 'occurred_at' => 'immutable_datetime'];
    }
}
