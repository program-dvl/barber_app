<?php

namespace App\Domain\SchedulingOperations\Models;

use App\Support\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class WalkInEntry extends Model
{
    use BelongsToBusiness;

    protected $fillable = [
        'business_id', 'location_id', 'service_id', 'preferred_staff_profile_id',
        'assigned_staff_profile_id', 'appointment_id', 'public_id', 'client_name',
        'client_mobile', 'notes', 'status', 'queue_position', 'arrived_at',
        'estimated_service_at', 'estimated_wait_minutes', 'estimate_evidence',
        'notified_at', 'service_started_at', 'abandoned_at', 'actual_wait_minutes', 'version',
    ];

    protected static function booted(): void
    {
        static::creating(fn (WalkInEntry $entry) => $entry->public_id ??= (string) Str::ulid());
    }

    protected function casts(): array
    {
        return [
            'arrived_at' => 'immutable_datetime',
            'estimated_service_at' => 'immutable_datetime', 'notified_at' => 'immutable_datetime',
            'service_started_at' => 'immutable_datetime', 'abandoned_at' => 'immutable_datetime',
            'estimate_evidence' => 'array', 'queue_position' => 'integer',
            'estimated_wait_minutes' => 'integer', 'actual_wait_minutes' => 'integer', 'version' => 'integer',
        ];
    }

    public function history(): HasMany
    {
        return $this->hasMany(WalkInHistory::class)->orderBy('occurred_at');
    }
}
