<?php

namespace App\Domain\PlatformAccess\Models;

use App\Models\User;
use App\Support\Tenancy\BelongsToBusiness;
use Database\Factories\StaffInvitationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class StaffInvitation extends Model
{
    /** @use HasFactory<StaffInvitationFactory> */
    use BelongsToBusiness;

    use HasFactory;

    protected $fillable = [
        'business_id',
        'public_id',
        'staff_profile_id',
        'invited_by_membership_id',
        'accepted_by_user_id',
        'role_id',
        'email',
        'token_hash',
        'expires_at',
        'accepted_at',
        'revoked_at',
        'revoked_by_user_id',
    ];

    protected $hidden = ['token_hash'];

    protected static function booted(): void
    {
        static::creating(function (StaffInvitation $invitation): void {
            $invitation->public_id ??= (string) Str::ulid();
        });
    }

    protected function casts(): array
    {
        return [
            'expires_at' => 'immutable_datetime',
            'accepted_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }

    protected static function newFactory(): StaffInvitationFactory
    {
        return StaffInvitationFactory::new();
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function staffProfile(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(Membership::class, 'invited_by_membership_id');
    }

    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by_user_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(BusinessRole::class, 'role_id');
    }

    public function locations(): BelongsToMany
    {
        $relation = $this->belongsToMany(Location::class, 'location_staff_invitation')
            ->withPivot('business_id')
            ->withTimestamps();

        return $this->business_id ? $relation->wherePivot('business_id', $this->business_id) : $relation;
    }

    public function isPending(): bool
    {
        return $this->accepted_at === null
            && $this->revoked_at === null
            && $this->expires_at->isFuture();
    }
}
