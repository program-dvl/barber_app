<?php

namespace App\Domain\Reporting\Models;

use App\Support\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ReportExport extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['business_id', 'requested_by_membership_id', 'public_id', 'report_key', 'format', 'filters', 'scope_snapshot', 'status', 'storage_path', 'content_hash', 'row_count', 'totals', 'error', 'completed_at'];

    protected static function booted(): void
    {
        static::creating(fn (self $export) => $export->public_id ??= (string) Str::ulid());
    }

    protected function casts(): array
    {
        return ['filters' => 'array', 'scope_snapshot' => 'array', 'totals' => 'array', 'row_count' => 'integer', 'completed_at' => 'immutable_datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
