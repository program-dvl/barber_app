<?php

namespace App\Domain\SchedulingOperations\Models;

use App\Domain\BusinessConfiguration\Models\PhysicalResource;
use App\Support\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppointmentResourceClaim extends Model
{
    use BelongsToBusiness;

    protected $fillable = [
        'business_id', 'appointment_id', 'appointment_service_line_id', 'appointment_segment_id',
        'physical_resource_id', 'quantity', 'starts_at_utc', 'ends_at_utc',
    ];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'starts_at_utc' => 'immutable_datetime', 'ends_at_utc' => 'immutable_datetime'];
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(PhysicalResource::class, 'physical_resource_id');
    }
}
