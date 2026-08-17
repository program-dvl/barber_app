<?php

namespace App\Domain\SchedulingOperations\Models;

use App\Domain\PlatformAccess\Models\Location;
use App\Support\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CapacityHold extends Model
{
    use BelongsToBusiness;

    protected $fillable = [
        'business_id', 'location_id', 'appointment_id', 'public_id', 'idempotency_key',
        'request_hash', 'status', 'source', 'client_eligibility', 'owner_key', 'actor_type', 'actor_id',
        'starts_at_utc', 'ends_at_utc', 'expires_at',
        'confirmed_at', 'expired_at',
    ];

    protected static function booted(): void
    {
        static::creating(fn (CapacityHold $hold) => $hold->public_id ??= (string) Str::ulid());
    }

    protected function casts(): array
    {
        return [
            'starts_at_utc' => 'immutable_datetime', 'ends_at_utc' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime', 'confirmed_at' => 'immutable_datetime',
            'expired_at' => 'immutable_datetime',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(CapacityHoldLine::class)->orderBy('sequence');
    }

    public function segments(): HasMany
    {
        return $this->hasMany(CapacityHoldSegment::class)->orderBy('starts_at_utc');
    }

    public function resourceClaims(): HasMany
    {
        return $this->hasMany(CapacityHoldResourceClaim::class);
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
