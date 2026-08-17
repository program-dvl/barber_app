<?php

namespace App\Domain\MoneyCommerce\Models;

use App\Domain\SchedulingOperations\Models\Appointment;
use App\Domain\SchedulingOperations\Models\CapacityHold;
use App\Support\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PaymentIntent extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['business_id', 'appointment_id', 'capacity_hold_id', 'client_id', 'public_id', 'purpose', 'provider', 'provider_intent_id', 'idempotency_key', 'request_hash', 'status', 'amount_minor', 'currency_code', 'provider_state_at', 'expires_at', 'source_snapshot', 'pending_booking_payload', 'provider_evidence'];

    protected static function booted(): void
    {
        static::creating(fn (self $intent) => $intent->public_id ??= (string) Str::ulid());
    }

    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'provider_state_at' => 'immutable_datetime', 'expires_at' => 'immutable_datetime', 'source_snapshot' => 'array', 'pending_booking_payload' => 'encrypted:array', 'provider_evidence' => 'array'];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function hold(): BelongsTo
    {
        return $this->belongsTo(CapacityHold::class, 'capacity_hold_id');
    }
}
