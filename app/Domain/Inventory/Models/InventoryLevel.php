<?php

namespace App\Domain\Inventory\Models;

use App\Support\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

class InventoryLevel extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['business_id', 'location_id', 'inventory_product_id', 'current_stock'];

    protected function casts(): array
    {
        return ['current_stock' => 'integer'];
    }
}
