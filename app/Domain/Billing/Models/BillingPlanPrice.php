<?php

namespace App\Domain\Billing\Models;

use App\Domain\Billing\Enums\BillingInterval;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingPlanPrice extends Model
{
    protected $fillable = ['billing_plan_id', 'billing_interval', 'currency', 'amount_minor', 'provider', 'provider_price_id', 'is_active', 'effective_from', 'effective_until'];

    protected function casts(): array
    {
        return [
            'billing_interval' => BillingInterval::class,
            'is_active' => 'boolean',
            'effective_from' => 'immutable_datetime',
            'effective_until' => 'immutable_datetime',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(BillingPlan::class, 'billing_plan_id');
    }
}
