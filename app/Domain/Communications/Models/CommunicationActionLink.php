<?php

namespace App\Domain\Communications\Models;

use App\Support\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CommunicationActionLink extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['business_id', 'client_id', 'public_id', 'purpose', 'target_type', 'target_id', 'expires_at', 'used_at', 'revoked_at'];

    protected static function booted(): void
    {
        static::creating(fn (self $link) => $link->public_id ??= (string) Str::ulid());
    }

    protected function casts(): array
    {
        return ['expires_at' => 'immutable_datetime', 'used_at' => 'immutable_datetime', 'revoked_at' => 'immutable_datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
