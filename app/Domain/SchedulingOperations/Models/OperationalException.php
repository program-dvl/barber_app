<?php

namespace App\Domain\SchedulingOperations\Models;

use App\Support\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

class OperationalException extends Model
{
    use BelongsToBusiness;

    protected $fillable = [
        'business_id', 'location_id', 'appointment_id', 'kind', 'status', 'reason',
        'impact', 'resolution', 'actor_type', 'actor_id', 'occurred_at', 'resolved_at',
    ];

    protected function casts(): array
    {
        return ['impact' => 'array', 'resolution' => 'array', 'occurred_at' => 'immutable_datetime', 'resolved_at' => 'immutable_datetime'];
    }
}
