<?php

namespace App\Domain\Billing\Models;

use App\Domain\PlatformAccess\Models\Business;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

class BillingInvoice extends Model
{
    protected $fillable = ['public_id', 'business_id', 'business_subscription_id', 'provider', 'provider_invoice_id', 'number', 'status', 'currency', 'subtotal_minor', 'discount_minor', 'tax_minor', 'total_minor', 'amount_due_minor', 'amount_paid_minor', 'issued_at', 'due_at', 'paid_at', 'hosted_url', 'pdf_url', 'line_items'];

    protected static function booted(): void
    {
        static::creating(fn (BillingInvoice $invoice) => $invoice->public_id ??= (string) Str::ulid());
        static::deleting(fn () => throw new LogicException('Billing invoices are append-only.'));
    }

    protected function casts(): array
    {
        return ['issued_at' => 'immutable_datetime', 'due_at' => 'immutable_datetime', 'paid_at' => 'immutable_datetime', 'line_items' => 'array'];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(BusinessSubscription::class, 'business_subscription_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(BillingPayment::class);
    }
}
