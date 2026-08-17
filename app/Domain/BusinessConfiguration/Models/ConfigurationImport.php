<?php

namespace App\Domain\BusinessConfiguration\Models;

use App\Support\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ConfigurationImport extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['business_id', 'public_id', 'entity_type', 'idempotency_key', 'source_name', 'source_path', 'source_hash', 'mapping', 'status', 'total_rows', 'created_rows', 'updated_rows', 'skipped_rows', 'failed_rows', 'duplicate_rows', 'error_export_path', 'started_at', 'completed_at'];

    protected static function booted(): void
    {
        static::creating(fn (ConfigurationImport $import) => $import->public_id ??= (string) Str::ulid());
    }

    protected function casts(): array
    {
        return ['mapping' => 'array', 'started_at' => 'immutable_datetime', 'completed_at' => 'immutable_datetime'];
    }

    public function rows(): HasMany
    {
        return $this->hasMany(ConfigurationImportRow::class);
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
