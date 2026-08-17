<?php

namespace App\Domain\PlatformAccess\Models;

use App\Domain\BusinessConfiguration\Models\LocationHour;
use App\Domain\BusinessConfiguration\Models\LocationScheduleException;
use App\Domain\BusinessConfiguration\Models\PhysicalResource;
use App\Support\Tenancy\BelongsToBusiness;
use Database\Factories\LocationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Location extends Model
{
    /** @use HasFactory<LocationFactory> */
    use BelongsToBusiness;

    use HasFactory;

    protected $fillable = [
        'business_id',
        'public_id',
        'name',
        'time_zone',
        'status',
        'address',
        'phone',
        'email',
        'latitude',
        'longitude',
        'is_active',
    ];

    protected static function booted(): void
    {
        static::creating(function (Location $location): void {
            $location->public_id ??= (string) Str::ulid();
        });
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function newFactory(): LocationFactory
    {
        return LocationFactory::new();
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function hours(): HasMany
    {
        return $this->hasMany(LocationHour::class);
    }

    public function scheduleExceptions(): HasMany
    {
        return $this->hasMany(LocationScheduleException::class);
    }

    public function physicalResources(): HasMany
    {
        return $this->hasMany(PhysicalResource::class);
    }
}
