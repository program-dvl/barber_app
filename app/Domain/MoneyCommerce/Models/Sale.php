<?php

namespace App\Domain\MoneyCommerce\Models;

use App\Domain\SchedulingOperations\Models\Appointment;
use App\Support\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Sale extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['business_id', 'location_id', 'appointment_id', 'client_id', 'public_id', 'status', 'currency_code', 'subtotal_minor', 'discount_minor', 'tax_minor', 'tip_minor', 'total_minor', 'deposit_applied_minor', 'paid_minor', 'refunded_minor', 'balance_minor', 'calculation_snapshot', 'completed_at'];

    protected static function booted(): void
    {
        static::creating(fn (self $sale) => $sale->public_id ??= (string) Str::ulid());
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    protected function casts(): array
    {
        return ['subtotal_minor' => 'integer', 'discount_minor' => 'integer', 'tax_minor' => 'integer', 'tip_minor' => 'integer', 'total_minor' => 'integer', 'deposit_applied_minor' => 'integer', 'paid_minor' => 'integer', 'refunded_minor' => 'integer', 'balance_minor' => 'integer', 'calculation_snapshot' => 'array', 'completed_at' => 'immutable_datetime'];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SaleLine::class)->orderBy('sequence');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }
}
