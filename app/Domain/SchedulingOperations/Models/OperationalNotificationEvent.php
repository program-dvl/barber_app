<?php

namespace App\Domain\SchedulingOperations\Models;

use App\Domain\Communications\Jobs\ProcessOperationalCommunicationEvent;
use App\Support\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

class OperationalNotificationEvent extends Model
{
    use BelongsToBusiness;

    protected $fillable = [
        'business_id', 'event_type', 'subject_type', 'subject_id', 'payload',
        'status', 'idempotency_key', 'occurred_at',
    ];

    protected function casts(): array
    {
        return ['payload' => 'array', 'occurred_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::created(function (self $event): void {
            if (! config('communications.dispatch_operational_events_immediately', true)) {
                return;
            }

            ProcessOperationalCommunicationEvent::dispatch($event->id, $event->business_id)->afterCommit();
        });
    }
}
