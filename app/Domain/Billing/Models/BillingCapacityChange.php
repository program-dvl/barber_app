<?php

namespace App\Domain\Billing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use LogicException;

class BillingCapacityChange extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['quote' => 'array', 'subscription_version' => 'integer', 'expires_at' => 'immutable_datetime',
            'submitted_at' => 'immutable_datetime', 'effective_at' => 'immutable_datetime',
            'applied_at' => 'immutable_datetime', 'last_checked_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $row) => $row->public_id ??= (string) Str::ulid());
        static::deleting(fn () => throw new LogicException('Capacity changes are retained as billing evidence.'));
    }
}
