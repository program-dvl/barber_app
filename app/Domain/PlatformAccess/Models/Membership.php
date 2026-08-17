<?php

namespace App\Domain\PlatformAccess\Models;

use App\Domain\PlatformAccess\Enums\MembershipStatus;
use App\Models\User;
use App\Support\Tenancy\BelongsToBusiness;
use Database\Factories\MembershipFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;

class Membership extends Model
{
    /** @use HasFactory<MembershipFactory> */
    use BelongsToBusiness;

    use HasFactory;
    use HasRoles;

    protected string $guard_name = 'web';

    protected $fillable = [
        'business_id',
        'user_id',
        'public_id',
        'status',
        'joined_at',
        'suspended_at',
        'revoked_at',
        'revoked_by_user_id',
        'revocation_reason',
    ];

    protected static function booted(): void
    {
        static::creating(function (Membership $membership): void {
            $membership->public_id ??= (string) Str::ulid();
        });
    }

    protected function casts(): array
    {
        return [
            'status' => MembershipStatus::class,
            'joined_at' => 'immutable_datetime',
            'suspended_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }

    protected static function newFactory(): MembershipFactory
    {
        return MembershipFactory::new();
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function staffProfile(): HasOne
    {
        return $this->hasOne(StaffProfile::class);
    }

    public function locations(): BelongsToMany
    {
        $relation = $this->belongsToMany(Location::class, 'location_membership')
            ->withPivot('business_id')
            ->withTimestamps();

        return $this->business_id ? $relation->wherePivot('business_id', $this->business_id) : $relation;
    }

    public function isActive(): bool
    {
        return $this->status === MembershipStatus::Active && $this->business->isActive();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', MembershipStatus::Active->value)->whereNull('revoked_at');
    }
}
