<?php

namespace App\Domain\Marketplace\LocalGrowth\Models;

use App\Support\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

class ServiceConcept extends Model
{
    use BelongsToBusiness;

    protected $table = 'marketplace_service_concepts';

    protected $guarded = ['id'];
}
