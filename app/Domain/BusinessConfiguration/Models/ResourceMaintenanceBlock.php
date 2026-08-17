<?php

namespace App\Domain\BusinessConfiguration\Models;

use App\Support\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

class ResourceMaintenanceBlock extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['business_id', 'physical_resource_id', 'starts_at_utc', 'ends_at_utc', 'time_zone', 'local_starts_at', 'local_ends_at', 'reason'];

    protected function casts(): array
    {
        return ['starts_at_utc' => 'immutable_datetime', 'ends_at_utc' => 'immutable_datetime'];
    }
}
