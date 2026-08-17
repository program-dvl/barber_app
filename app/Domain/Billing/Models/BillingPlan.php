<?php

namespace App\Domain\Billing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class BillingPlan extends Model
{
    protected $fillable = ['public_id', 'code', 'name', 'description', 'is_active', 'is_trial_default', 'available_from', 'available_until'];

    protected static function booted(): void
    {
        static::creating(fn (BillingPlan $plan) => $plan->public_id ??= (string) Str::ulid());
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_trial_default' => 'boolean',
            'available_from' => 'immutable_datetime',
            'available_until' => 'immutable_datetime',
        ];
    }

    public function prices(): HasMany
    {
        return $this->hasMany(BillingPlanPrice::class);
    }

    public function entitlements(): HasMany
    {
        return $this->hasMany(BillingPlanEntitlement::class);
    }
}
