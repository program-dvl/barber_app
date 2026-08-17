<?php

namespace App\Domain\MoneyCommerce\Models;

use App\Support\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class SaleReceipt extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['business_id', 'sale_id', 'receipt_number', 'content_hash', 'snapshot', 'issued_at'];

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'issued_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Receipts are immutable reproductions.'));
        static::deleting(fn () => throw new LogicException('Receipts are retained financial evidence.'));
    }
}
