<?php

namespace App\Domain\Reporting\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class InstrumentationEvent extends Model
{
    protected $fillable = ['business_id', 'event_name', 'metric_version', 'idempotency_key', 'subject_hash', 'dimensions', 'occurred_at'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Instrumentation events are append-only.'));
        static::deleting(fn () => throw new LogicException('Instrumentation events are append-only.'));
    }

    protected function casts(): array
    {
        return ['dimensions' => 'array', 'occurred_at' => 'immutable_datetime'];
    }
}
