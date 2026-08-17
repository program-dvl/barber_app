<?php

namespace App\Domain\BusinessConfiguration\Models;

use App\Support\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ServiceCategory extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['business_id', 'public_id', 'name', 'display_order', 'is_active'];

    protected static function booted(): void
    {
        static::creating(fn (ServiceCategory $category) => $category->public_id ??= (string) Str::ulid());
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }
}
