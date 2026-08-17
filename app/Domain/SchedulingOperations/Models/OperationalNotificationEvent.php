<?php

namespace App\Domain\SchedulingOperations\Models;

use App\Support\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

class OperationalNotificationEvent extends Model
{
    use BelongsToBusiness;

    protected $fillable = [
        'business_id', 'event_type', 'subject_type', 'subject_id', 'payload',
        'status', 'idempotency_key', 'occurred_at',
    ];

    protected function casts(): array
    {
        return ['payload' => 'array', 'occurred_at' => 'immutable_datetime'];
    }
}
