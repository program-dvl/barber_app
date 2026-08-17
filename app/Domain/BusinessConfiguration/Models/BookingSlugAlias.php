<?php

namespace App\Domain\BusinessConfiguration\Models;

use App\Support\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

class BookingSlugAlias extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['business_id', 'slug', 'redirected_at'];

    protected function casts(): array
    {
        return ['redirected_at' => 'immutable_datetime'];
    }
}
