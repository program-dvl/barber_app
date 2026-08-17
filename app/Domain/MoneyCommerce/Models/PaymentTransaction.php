<?php

namespace App\Domain\MoneyCommerce\Models;

use App\Support\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

class PaymentTransaction extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['business_id', 'sale_id', 'appointment_id', 'payment_intent_id', 'parent_transaction_id', 'public_id', 'kind', 'status', 'method', 'provider', 'provider_reference', 'idempotency_key', 'amount_minor', 'currency_code', 'evidence', 'reason', 'occurred_at'];

    protected static function booted(): void
    {
        static::creating(fn (self $transaction) => $transaction->public_id ??= (string) Str::ulid());
        static::updating(fn () => throw new LogicException('Payment transactions are append-only.'));
        static::deleting(fn () => throw new LogicException('Payment transactions are append-only.'));
    }

    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'evidence' => 'array', 'occurred_at' => 'immutable_datetime'];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
