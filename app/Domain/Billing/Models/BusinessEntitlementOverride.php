<?php

namespace App\Domain\Billing\Models;

use App\Domain\PlatformAccess\Models\Business;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessEntitlementOverride extends Model
{
    protected $fillable = ['business_id', 'entitlement_definition_id', 'value', 'effective_from', 'effective_until', 'changed_by_user_id', 'change_reason'];

    protected function casts(): array
    {
        return ['value' => 'json', 'effective_from' => 'immutable_datetime', 'effective_until' => 'immutable_datetime'];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
