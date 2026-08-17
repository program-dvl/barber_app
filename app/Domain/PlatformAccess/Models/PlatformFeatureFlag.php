<?php

namespace App\Domain\PlatformAccess\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PlatformFeatureFlag extends Model
{
    protected $fillable = ['public_id', 'key', 'scope_type', 'scope_id', 'enabled', 'description', 'reason', 'updated_by_user_id'];

    protected static function booted(): void
    {
        static::creating(fn (self $flag) => $flag->public_id ??= (string) Str::ulid());
    }

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }
}
