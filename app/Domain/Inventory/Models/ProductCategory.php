<?php

namespace App\Domain\Inventory\Models;

use App\Support\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ProductCategory extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['business_id', 'public_id', 'name', 'status'];

    protected static function booted(): void
    {
        static::creating(fn (self $category) => $category->public_id ??= (string) Str::ulid());
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
