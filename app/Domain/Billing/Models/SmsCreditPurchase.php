<?php

namespace App\Domain\Billing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use LogicException;

class SmsCreditPurchase extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['quote' => 'array', 'expires_at' => 'immutable_datetime', 'paid_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $row) => $row->public_id ??= (string) Str::ulid());
        static::deleting(fn () => throw new LogicException('Credit purchases are retained as financial history.'));
    }
}
