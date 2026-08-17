<?php

namespace App\Models;

use App\Domain\PlatformAccess\Enums\PlatformRole;
use App\Domain\PlatformAccess\Models\Membership;
use App\Domain\PlatformAccess\Models\PlatformRoleAssignment;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Jetstream\HasTeams;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements FilamentUser, MustVerifyEmail
{
    use HasApiTokens;
    use HasFactory;
    use HasProfilePhoto;
    use HasTeams;
    use Notifiable;
    use TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'trial_is_used',
        'is_admin',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'trial_is_used' => 'boolean',
        'is_admin' => 'boolean',
        'password' => 'hashed',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'profile_photo_url',
    ];

    // Relations
    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    public function platformRoleAssignments(): HasMany
    {
        return $this->hasMany(PlatformRoleAssignment::class);
    }

    public function activePlatformRoles(): HasMany
    {
        return $this->platformRoleAssignments()->active();
    }

    public function hasPlatformRole(PlatformRole $role): bool
    {
        return $this->activePlatformRoles()->where('role', $role->value)->exists();
    }

    // End Relations

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'admin'
            && $this->hasVerifiedEmail()
            && filled($this->two_factor_confirmed_at)
            && $this->hasPlatformRole(PlatformRole::Administrator);
    }

    public function hasAnyActivePlatformRole(): bool
    {
        return $this->activePlatformRoles()->exists();
    }

    public function trialIsUsed()
    {
        return $this->trial_is_used;
    }
}
