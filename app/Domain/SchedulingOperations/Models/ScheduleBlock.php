<?php

namespace App\Domain\SchedulingOperations\Models;

use App\Support\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ScheduleBlock extends Model
{
    use BelongsToBusiness;

    protected $fillable = [
        'business_id', 'location_id', 'staff_profile_id', 'public_id', 'kind', 'label',
        'private_reason', 'starts_at_utc', 'ends_at_utc', 'time_zone', 'local_starts_at',
        'local_ends_at', 'version', 'actor_type', 'actor_id',
    ];

    protected static function booted(): void
    {
        static::creating(fn (ScheduleBlock $block) => $block->public_id ??= (string) Str::ulid());
    }

    protected function casts(): array
    {
        return [
            'starts_at_utc' => 'immutable_datetime', 'ends_at_utc' => 'immutable_datetime',
            'version' => 'integer',
        ];
    }
}
