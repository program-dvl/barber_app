<?php

namespace App\Domain\Billing\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class BillingRateCard extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['terms' => 'array', 'verified_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Verified rate cards are immutable. Publish a new revision.'));
        static::deleting(fn () => throw new LogicException('Verified rate cards are retained as billing evidence.'));
    }
}
