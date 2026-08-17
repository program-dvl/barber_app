<?php

namespace App\Domain\SchedulingOperations\Models;

use App\Support\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class AppointmentStatusHistory extends Model
{
    use BelongsToBusiness;

    protected $table = 'appointment_status_history';

    protected $fillable = [
        'business_id', 'appointment_id', 'previous_status', 'status', 'source',
        'actor_type', 'actor_id', 'reason', 'occurred_at',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Appointment status history is append-only.'));
        static::deleting(fn () => throw new LogicException('Appointment status history is append-only.'));
    }

    protected function casts(): array
    {
        return ['occurred_at' => 'immutable_datetime'];
    }
}
