<?php

namespace App\Domain\PlatformAccess\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PlatformNotice extends Model
{
    protected $fillable = ['public_id', 'title', 'message', 'severity', 'audience', 'business_id', 'starts_at', 'ends_at', 'published_at', 'published_by_user_id'];

    protected static function booted(): void
    {
        static::creating(fn (self $notice) => $notice->public_id ??= (string) Str::ulid());
    }

    protected function casts(): array
    {
        return ['starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime', 'published_at' => 'immutable_datetime'];
    }
}
