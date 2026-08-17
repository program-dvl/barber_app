<?php

namespace App\Domain\Inventory\Models;

use App\Support\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class InventoryProduct extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['business_id', 'product_category_id', 'public_id', 'name', 'sku', 'barcode', 'sale_price_minor', 'cost_minor', 'tax_rate_bps', 'currency_code', 'status', 'current_stock', 'low_stock_threshold'];

    protected static function booted(): void
    {
        static::creating(fn (self $product) => $product->public_id ??= (string) Str::ulid());
    }

    protected function casts(): array
    {
        return ['sale_price_minor' => 'integer', 'cost_minor' => 'integer', 'tax_rate_bps' => 'integer', 'current_stock' => 'integer', 'low_stock_threshold' => 'integer'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class)->orderByDesc('occurred_at');
    }

    public function scopeLowStock(Builder $query): Builder
    {
        return $query->whereColumn('current_stock', '<=', 'low_stock_threshold');
    }
}
