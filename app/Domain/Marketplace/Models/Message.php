<?php

namespace App\Domain\Marketplace\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Message extends Model
{
    protected $table = 'marketplace_messages';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['body' => 'encrypted', 'read_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $message) => $message->public_id ??= (string) Str::ulid());
    }
}
