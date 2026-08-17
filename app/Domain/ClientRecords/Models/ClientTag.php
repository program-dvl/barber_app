<?php

namespace App\Domain\ClientRecords\Models;

use App\Support\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

class ClientTag extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['business_id', 'name', 'slug'];
}
