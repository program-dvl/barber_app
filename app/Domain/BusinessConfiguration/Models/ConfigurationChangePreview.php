<?php

namespace App\Domain\BusinessConfiguration\Models;

use App\Support\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ConfigurationChangePreview extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['business_id', 'public_id', 'change_type', 'subject_type', 'subject_id', 'proposed_change', 'affected_appointment_ids', 'affected_count', 'status', 'resolution_note', 'expires_at'];

    protected static function booted(): void
    {
        static::creating(fn (ConfigurationChangePreview $preview) => $preview->public_id ??= (string) Str::ulid());
    }

    protected function casts(): array
    {
        return ['proposed_change' => 'array', 'affected_appointment_ids' => 'array', 'affected_count' => 'integer', 'expires_at' => 'immutable_datetime'];
    }
}
