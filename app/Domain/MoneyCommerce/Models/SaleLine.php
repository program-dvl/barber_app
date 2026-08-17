<?php

namespace App\Domain\MoneyCommerce\Models;

use App\Support\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class SaleLine extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['business_id', 'sale_id', 'kind', 'source_type', 'source_id', 'service_id', 'staff_profile_id', 'sequence', 'description', 'quantity', 'unit_price_minor', 'tax_rate_bps', 'discount_minor', 'source_snapshot'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'unit_price_minor' => 'integer', 'tax_rate_bps' => 'integer', 'discount_minor' => 'integer', 'source_snapshot' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Sale lines are immutable; create a compensating adjustment.'));
        static::deleting(fn () => throw new LogicException('Sale lines are immutable.'));
    }
}
