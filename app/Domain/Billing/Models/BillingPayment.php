<?php

namespace App\Domain\Billing\Models;

use App\Domain\PlatformAccess\Models\Business;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

class BillingPayment extends Model
{
    protected $fillable = ['public_id', 'business_id', 'billing_invoice_id', 'provider', 'provider_payment_id', 'status', 'currency', 'amount_minor', 'failure_code', 'failure_message', 'attempted_at', 'paid_at'];

    protected static function booted(): void
    {
        static::creating(fn (BillingPayment $payment) => $payment->public_id ??= (string) Str::ulid());
        static::deleting(fn () => throw new LogicException('Billing payments are append-only.'));
    }

    protected function casts(): array
    {
        return ['attempted_at' => 'immutable_datetime', 'paid_at' => 'immutable_datetime'];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(BillingInvoice::class, 'billing_invoice_id');
    }
}
