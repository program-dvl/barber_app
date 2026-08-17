<?php

namespace App\Domain\SchedulingOperations\Models;

use App\Support\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

class CapacityHoldSegment extends Model
{
    use BelongsToBusiness;

    protected $fillable = [
        'business_id', 'capacity_hold_id', 'capacity_hold_line_id', 'service_segment_id',
        'staff_profile_id', 'kind', 'sequence', 'starts_at_utc', 'ends_at_utc', 'occupies_staff',
    ];

    protected function casts(): array
    {
        return [
            'starts_at_utc' => 'immutable_datetime', 'ends_at_utc' => 'immutable_datetime',
            'occupies_staff' => 'boolean',
        ];
    }
}
