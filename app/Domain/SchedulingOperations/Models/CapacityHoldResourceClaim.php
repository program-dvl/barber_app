<?php

namespace App\Domain\SchedulingOperations\Models;

use App\Support\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

class CapacityHoldResourceClaim extends Model
{
    use BelongsToBusiness;

    protected $fillable = [
        'business_id', 'capacity_hold_id', 'capacity_hold_line_id', 'capacity_hold_segment_id',
        'physical_resource_id', 'quantity', 'starts_at_utc', 'ends_at_utc',
    ];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'starts_at_utc' => 'immutable_datetime', 'ends_at_utc' => 'immutable_datetime'];
    }
}
