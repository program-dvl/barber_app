<?php

namespace App\Domain\BusinessConfiguration\Models;

use App\Support\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceResourceRequirement extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['business_id', 'service_id', 'service_segment_id', 'physical_resource_id', 'quantity'];

    public function resource(): BelongsTo
    {
        return $this->belongsTo(PhysicalResource::class, 'physical_resource_id');
    }

    protected function casts(): array
    {
        return ['quantity' => 'integer'];
    }
}
