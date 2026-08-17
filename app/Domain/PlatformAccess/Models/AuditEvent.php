<?php

namespace App\Domain\PlatformAccess\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;
use LogicException;

class AuditEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'public_id',
        'business_id',
        'actor_user_id',
        'actor_membership_id',
        'actor_platform_role',
        'action',
        'auditable_type',
        'auditable_id',
        'source',
        'correlation_id',
        'ip_address_hash',
        'user_agent',
        'reason',
        'before',
        'after',
        'metadata',
        'occurred_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (AuditEvent $event): void {
            $event->public_id ??= (string) Str::ulid();
            $event->occurred_at ??= now();
        });

        static::updating(fn () => throw new LogicException('Audit events are append-only.'));
        static::deleting(fn () => throw new LogicException('Audit events are append-only.'));
    }

    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
            'metadata' => 'array',
            'occurred_at' => 'immutable_datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function actorMembership(): BelongsTo
    {
        return $this->belongsTo(Membership::class, 'actor_membership_id');
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
