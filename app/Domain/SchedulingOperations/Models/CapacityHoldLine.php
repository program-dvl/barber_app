<?php

namespace App\Domain\SchedulingOperations\Models;

use App\Support\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CapacityHoldLine extends Model
{
    use BelongsToBusiness;

    protected $fillable = [
        'business_id', 'capacity_hold_id', 'service_id', 'primary_staff_profile_id',
        'sequence', 'configuration_snapshot',
    ];

    protected function casts(): array
    {
        return ['configuration_snapshot' => 'array'];
    }

    public function hold(): BelongsTo
    {
        return $this->belongsTo(CapacityHold::class, 'capacity_hold_id');
    }

    public function segments(): HasMany
    {
        return $this->hasMany(CapacityHoldSegment::class)->orderBy('sequence');
    }
}
