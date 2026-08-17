<?php

namespace App\Domain\SchedulingOperations\Models;

use App\Support\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class WalkInHistory extends Model
{
    use BelongsToBusiness;

    protected $table = 'walk_in_history';

    protected $fillable = [
        'business_id', 'walk_in_entry_id', 'action', 'previous_status', 'status',
        'source', 'actor_type', 'actor_id', 'reason', 'before', 'after', 'occurred_at',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Walk-in history is append-only.'));
        static::deleting(fn () => throw new LogicException('Walk-in history is append-only.'));
    }

    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array', 'occurred_at' => 'immutable_datetime'];
    }
}
