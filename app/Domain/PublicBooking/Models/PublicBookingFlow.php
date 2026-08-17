<?php

namespace App\Domain\PublicBooking\Models;

use App\Domain\SchedulingOperations\Models\Appointment;
use App\Domain\SchedulingOperations\Models\CapacityHold;
use App\Support\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PublicBookingFlow extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['business_id', 'capacity_hold_id', 'appointment_id', 'public_id', 'secret_hash', 'status', 'last_step', 'policy_version', 'state', 'expires_at', 'confirmed_at'];

    protected static function booted(): void
    {
        static::creating(fn (PublicBookingFlow $flow) => $flow->public_id ??= (string) Str::ulid());
    }

    protected function casts(): array
    {
        return ['state' => 'array', 'last_step' => 'integer', 'policy_version' => 'integer', 'expires_at' => 'immutable_datetime', 'confirmed_at' => 'immutable_datetime'];
    }

    public function hold(): BelongsTo
    {
        return $this->belongsTo(CapacityHold::class, 'capacity_hold_id');
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }
}
