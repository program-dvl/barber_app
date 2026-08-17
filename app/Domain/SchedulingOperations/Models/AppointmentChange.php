<?php

namespace App\Domain\SchedulingOperations\Models;

use App\Support\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class AppointmentChange extends Model
{
    use BelongsToBusiness;

    protected $fillable = [
        'business_id', 'appointment_id', 'kind', 'source', 'actor_type', 'actor_id',
        'reason', 'before', 'after', 'metadata', 'occurred_at',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Appointment changes are append-only.'));
        static::deleting(fn () => throw new LogicException('Appointment changes are append-only.'));
    }

    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array', 'metadata' => 'array', 'occurred_at' => 'immutable_datetime'];
    }
}
