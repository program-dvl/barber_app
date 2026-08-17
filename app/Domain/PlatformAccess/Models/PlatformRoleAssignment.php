<?php

namespace App\Domain\PlatformAccess\Models;

use App\Domain\PlatformAccess\Enums\PlatformRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformRoleAssignment extends Model
{
    protected $fillable = [
        'user_id',
        'role',
        'granted_by_user_id',
        'reason',
        'expires_at',
        'revoked_at',
        'revoked_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'role' => PlatformRole::class,
            'expires_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive($query)
    {
        return $query
            ->whereNull('revoked_at')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }
}
