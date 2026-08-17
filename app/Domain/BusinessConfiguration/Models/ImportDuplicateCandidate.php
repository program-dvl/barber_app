<?php

namespace App\Domain\BusinessConfiguration\Models;

use App\Support\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

class ImportDuplicateCandidate extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['business_id', 'configuration_import_row_id', 'candidate_type', 'candidate_key', 'matched_fields', 'resolution'];

    protected function casts(): array
    {
        return ['matched_fields' => 'array'];
    }
}
