<?php

namespace App\Domain\PlatformAccess\Models;

use App\Domain\BusinessConfiguration\Models\StaffAvailabilityRule;
use App\Domain\BusinessConfiguration\Models\StaffServiceAssignment;
use App\Models\User;
use App\Support\Tenancy\BelongsToBusiness;
use Database\Factories\StaffProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class StaffProfile extends Model
{
    /** @use HasFactory<StaffProfileFactory> */
    use BelongsToBusiness;

    use HasFactory;

    protected $fillable = [
        'business_id',
        'membership_id',
        'user_id',
        'public_id',
        'display_name',
        'email',
        'mobile',
        'title',
        'biography',
        'photo_path',
        'status',
        'online_visible',
    ];

    protected static function booted(): void
    {
        static::creating(function (StaffProfile $staffProfile): void {
            $staffProfile->public_id ??= (string) Str::ulid();
        });
    }

    protected function casts(): array
    {
        return ['online_visible' => 'boolean'];
    }

    protected static function newFactory(): StaffProfileFactory
    {
        return StaffProfileFactory::new();
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function locations(): BelongsToMany
    {
        $relation = $this->belongsToMany(Location::class, 'location_staff_profile')
            ->withPivot('business_id')
            ->withTimestamps();

        return $this->business_id ? $relation->wherePivot('business_id', $this->business_id) : $relation;
    }

    public function serviceAssignments(): HasMany
    {
        return $this->hasMany(StaffServiceAssignment::class);
    }

    public function availabilityRules(): HasMany
    {
        return $this->hasMany(StaffAvailabilityRule::class);
    }
}
