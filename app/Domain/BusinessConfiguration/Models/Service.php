<?php

namespace App\Domain\BusinessConfiguration\Models;

use App\Domain\PlatformAccess\Models\Location;
use App\Support\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Service extends Model
{
    use BelongsToBusiness;

    protected $fillable = [
        'business_id', 'service_category_id', 'public_id', 'kind', 'name', 'description', 'image_path',
        'is_active', 'online_visible', 'consultation_required', 'client_eligibility', 'price_type',
        'price_minor', 'currency_code', 'tax_category', 'tax_inclusive', 'duration_minutes',
        'processing_minutes', 'cleanup_minutes', 'minimum_notice_minutes', 'maximum_advance_days',
        'deposit_type', 'deposit_value', 'effective_from', 'effective_until',
    ];

    protected static function booted(): void
    {
        static::creating(fn (Service $service) => $service->public_id ??= (string) Str::ulid());
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean', 'online_visible' => 'boolean', 'consultation_required' => 'boolean',
            'tax_inclusive' => 'boolean', 'price_minor' => 'integer', 'duration_minutes' => 'integer',
            'processing_minutes' => 'integer', 'cleanup_minutes' => 'integer', 'deposit_value' => 'integer',
            'effective_from' => 'immutable_datetime', 'effective_until' => 'immutable_datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'service_category_id');
    }

    public function segments(): HasMany
    {
        return $this->hasMany(ServiceSegment::class)->orderBy('sequence');
    }

    public function resourceRequirements(): HasMany
    {
        return $this->hasMany(ServiceResourceRequirement::class);
    }

    public function staffAssignments(): HasMany
    {
        return $this->hasMany(StaffServiceAssignment::class);
    }

    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class, 'location_service')->withPivot(['business_id', 'is_eligible', 'price_minor'])->withTimestamps();
    }

    public function addons(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'service_addons', 'service_id', 'addon_service_id')->withPivot('business_id')->withTimestamps();
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
