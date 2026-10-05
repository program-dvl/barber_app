<?php

namespace App\Domain\MoneyCommerce\Models;

use App\Support\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Deposit extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['business_id', 'appointment_id', 'client_id', 'payment_transaction_id', 'original_amount_minor', 'applied_minor', 'refunded_minor', 'forfeited_minor', 'credited_minor', 'currency_code', 'status', 'policy_snapshot'];

    protected function casts(): array
    {
        return ['original_amount_minor' => 'integer', 'applied_minor' => 'integer', 'refunded_minor' => 'integer', 'forfeited_minor' => 'integer', 'credited_minor' => 'integer', 'policy_snapshot' => 'array'];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class, 'payment_transaction_id');
    }

    public function scopeVerified(Builder $query): Builder
    {
        return $query->whereHas('payment', fn ($payment) => $payment->where('kind', 'payment')->where('status', 'succeeded')
            ->whereColumn('payment_transactions.business_id', 'deposits.business_id')
            ->whereColumn('payment_transactions.appointment_id', 'deposits.appointment_id')
            ->whereColumn('payment_transactions.currency_code', 'deposits.currency_code')
            ->whereColumn('payment_transactions.amount_minor', '>=', 'deposits.original_amount_minor'));
    }

    public function remainingMinor(): int
    {
        return $this->original_amount_minor - $this->applied_minor - $this->refunded_minor - $this->forfeited_minor - $this->credited_minor;
    }
}
