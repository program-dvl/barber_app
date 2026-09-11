<?php

namespace App\Domain\BusinessConfiguration\Models;

use App\Domain\PlatformAccess\Models\StaffProfile;
use App\Support\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffServiceAssignment extends Model
{
    use BelongsToBusiness;

    protected $fillable = [
        'business_id', 'staff_profile_id', 'service_id', 'is_qualified', 'is_active', 'online_visible',
        'price_minor', 'duration_minutes', 'processing_minutes', 'cleanup_minutes', 'commission_rate',
        'effective_from', 'effective_until',
    ];

    protected function casts(): array
    {
        return [
            'is_qualified' => 'boolean', 'is_active' => 'boolean', 'online_visible' => 'boolean',
            'price_minor' => 'integer', 'duration_minutes' => 'integer', 'processing_minutes' => 'integer',
            'cleanup_minutes' => 'integer', 'commission_rate' => 'decimal:4',
            'effective_from' => 'immutable_datetime', 'effective_until' => 'immutable_datetime',
        ];
    }

    public function staffProfile(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
