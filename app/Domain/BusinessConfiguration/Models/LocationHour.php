<?php

namespace App\Domain\BusinessConfiguration\Models;

use App\Domain\PlatformAccess\Models\Location;
use App\Support\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LocationHour extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['business_id', 'location_id', 'day_of_week', 'opens_at', 'closes_at', 'sequence', 'effective_from', 'effective_until'];

    protected function casts(): array
    {
        return ['effective_from' => 'immutable_date', 'effective_until' => 'immutable_date'];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
