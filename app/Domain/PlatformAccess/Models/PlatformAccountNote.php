<?php

namespace App\Domain\PlatformAccess\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PlatformAccountNote extends Model
{
    protected $fillable = ['public_id', 'business_id', 'author_user_id', 'body', 'visibility', 'retain_until'];

    protected static function booted(): void
    {
        static::creating(fn (self $note) => $note->public_id ??= (string) Str::ulid());
        static::updating(fn () => throw new \LogicException('Internal account notes are append-only.'));
        static::deleting(fn () => throw new \LogicException('Internal account notes are retention-controlled and cannot be deleted directly.'));
    }

    protected function casts(): array
    {
        return ['retain_until' => 'immutable_datetime'];
    }
}
