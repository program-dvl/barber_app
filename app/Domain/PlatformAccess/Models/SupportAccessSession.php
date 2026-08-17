<?php

namespace App\Domain\PlatformAccess\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SupportAccessSession extends Model
{
    protected $fillable = ['public_id', 'support_access_grant_id', 'business_id', 'operator_user_id', 'session_fingerprint', 'started_at', 'last_used_at', 'ended_at', 'ended_reason'];

    protected static function booted(): void
    {
        static::creating(fn (self $session) => $session->public_id ??= (string) Str::ulid());
    }

    protected function casts(): array
    {
        return ['started_at' => 'immutable_datetime', 'last_used_at' => 'immutable_datetime', 'ended_at' => 'immutable_datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function grant(): BelongsTo
    {
        return $this->belongsTo(SupportAccessGrant::class, 'support_access_grant_id');
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_user_id');
    }

    public function isActive(): bool
    {
        return $this->ended_at === null && $this->grant->isActive();
    }
}
