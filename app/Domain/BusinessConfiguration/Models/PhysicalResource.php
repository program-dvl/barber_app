<?php

namespace App\Domain\BusinessConfiguration\Models;

use App\Domain\PlatformAccess\Models\Location;
use App\Support\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PhysicalResource extends Model
{
    use BelongsToBusiness;

    protected $attributes = ['quantity' => 1, 'is_active' => true];

    protected $fillable = ['business_id', 'location_id', 'public_id', 'type', 'name', 'quantity', 'is_active'];

    protected static function booted(): void
    {
        static::creating(fn (PhysicalResource $resource) => $resource->public_id ??= (string) Str::ulid());
    }

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'is_active' => 'boolean'];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function hours(): HasMany
    {
        return $this->hasMany(ResourceHour::class);
    }

    public function maintenanceBlocks(): HasMany
    {
        return $this->hasMany(ResourceMaintenanceBlock::class);
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
