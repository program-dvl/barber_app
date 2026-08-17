<?php

namespace App\Domain\BusinessConfiguration\Models;

use App\Domain\PlatformAccess\Models\Location;
use App\Support\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LocationScheduleException extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['business_id', 'location_id', 'kind', 'starts_on', 'ends_on', 'opens_at', 'closes_at', 'name', 'reason'];

    protected function casts(): array
    {
        return ['starts_on' => 'immutable_date', 'ends_on' => 'immutable_date'];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
