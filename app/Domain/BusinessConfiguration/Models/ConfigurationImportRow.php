<?php

namespace App\Domain\BusinessConfiguration\Models;

use App\Support\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConfigurationImportRow extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['business_id', 'configuration_import_id', 'row_number', 'row_key', 'fingerprint', 'normalized_data', 'errors', 'status', 'result_action'];

    protected function casts(): array
    {
        return ['normalized_data' => 'array', 'errors' => 'array'];
    }

    public function duplicates(): HasMany
    {
        return $this->hasMany(ImportDuplicateCandidate::class);
    }
}
