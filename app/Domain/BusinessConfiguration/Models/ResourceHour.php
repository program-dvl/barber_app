<?php

namespace App\Domain\BusinessConfiguration\Models;

use App\Support\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

class ResourceHour extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['business_id', 'physical_resource_id', 'day_of_week', 'opens_at', 'closes_at', 'sequence'];
}
