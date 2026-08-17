<?php

namespace App\Domain\PlatformAccess\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class SupportAccessGrant extends Model
{
    protected $fillable = ['public_id', 'business_id', 'operator_user_id', 'approved_by_user_id', 'ticket_reference', 'reason', 'scopes', 'expires_at', 'revoked_at', 'revoked_by_user_id', 'revocation_reason'];

    protected static function booted(): void
    {
        static::creating(fn (self $grant) => $grant->public_id ??= (string) Str::ulid());
    }

    protected function casts(): array
    {
        return ['scopes' => 'array', 'expires_at' => 'immutable_datetime', 'revoked_at' => 'immutable_datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_user_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(SupportAccessSession::class);
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null && $this->expires_at->isFuture();
    }

    public function permits(string $scope): bool
    {
        return $this->isActive() && in_array($scope, $this->scopes, true);
    }
}
