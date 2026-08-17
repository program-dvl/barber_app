<?php

namespace App\Domain\Billing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingPlanEntitlement extends Model
{
    protected $fillable = ['billing_plan_id', 'entitlement_definition_id', 'value', 'effective_from', 'effective_until', 'changed_by_user_id', 'change_reason'];

    protected function casts(): array
    {
        return ['value' => 'json', 'effective_from' => 'immutable_datetime', 'effective_until' => 'immutable_datetime'];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(BillingPlan::class, 'billing_plan_id');
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(EntitlementDefinition::class, 'entitlement_definition_id');
    }
}
