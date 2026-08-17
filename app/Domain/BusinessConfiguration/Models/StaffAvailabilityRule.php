<?php

namespace App\Domain\BusinessConfiguration\Models;

use App\Support\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

class StaffAvailabilityRule extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['business_id', 'staff_profile_id', 'location_id', 'kind', 'day_of_week', 'starts_on', 'ends_on', 'starts_at', 'ends_at', 'sequence', 'reason'];

    protected function casts(): array
    {
        return ['starts_on' => 'immutable_date', 'ends_on' => 'immutable_date'];
    }
}
