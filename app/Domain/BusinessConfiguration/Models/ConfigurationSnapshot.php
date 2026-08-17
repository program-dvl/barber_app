<?php

namespace App\Domain\BusinessConfiguration\Models;

use App\Support\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

class ConfigurationSnapshot extends Model
{
    use BelongsToBusiness;

    public $timestamps = false;

    protected $fillable = ['business_id', 'snapshot_type', 'subject_type', 'subject_id', 'values', 'effective_at', 'captured_at'];

    protected function casts(): array
    {
        return ['values' => 'array', 'effective_at' => 'immutable_datetime', 'captured_at' => 'immutable_datetime'];
    }
}
