<?php

namespace App\Domain\BusinessConfiguration\Models;

use App\Support\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

class ImportedConfigurationRecord extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['business_id', 'entity_type', 'row_key', 'fingerprint', 'data'];

    protected function casts(): array
    {
        return ['data' => 'array'];
    }
}
