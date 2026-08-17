<?php

namespace App\Domain\Billing\Models;

use App\Domain\Billing\Enums\BillingInterval;
use App\Domain\Billing\Enums\RestrictionLevel;
use App\Domain\Billing\Enums\SubscriptionStatus;
use App\Domain\PlatformAccess\Models\Business;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class BusinessSubscription extends Model
{
    protected $fillable = [
        'public_id', 'business_id', 'billing_plan_id', 'billing_plan_price_id', 'provider',
        'provider_customer_id', 'provider_subscription_id', 'status', 'restriction_level',
        'billing_interval', 'trial_started_at', 'trial_ends_at', 'current_period_started_at',
        'current_period_ends_at', 'grace_ends_at', 'cancel_at', 'canceled_at', 'ended_at',
        'export_available_until', 'payment_method_type', 'payment_method_last_four',
        'provider_state_at', 'version',
    ];

    protected static function booted(): void
    {
        static::creating(fn (BusinessSubscription $subscription) => $subscription->public_id ??= (string) Str::ulid());
    }

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'restriction_level' => RestrictionLevel::class,
            'billing_interval' => BillingInterval::class,
            'trial_started_at' => 'immutable_datetime',
            'trial_ends_at' => 'immutable_datetime',
            'current_period_started_at' => 'immutable_datetime',
            'current_period_ends_at' => 'immutable_datetime',
            'grace_ends_at' => 'immutable_datetime',
            'cancel_at' => 'immutable_datetime',
            'canceled_at' => 'immutable_datetime',
            'ended_at' => 'immutable_datetime',
            'export_available_until' => 'immutable_datetime',
            'provider_state_at' => 'immutable_datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(BillingPlan::class, 'billing_plan_id');
    }

    public function price(): BelongsTo
    {
        return $this->belongsTo(BillingPlanPrice::class, 'billing_plan_price_id');
    }

    public function changes(): HasMany
    {
        return $this->hasMany(SubscriptionChange::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(BillingInvoice::class);
    }

    public function checkoutAttempts(): HasMany
    {
        return $this->hasMany(BillingCheckoutAttempt::class);
    }

    public function exportIsAvailable(): bool
    {
        return $this->status !== SubscriptionStatus::Terminated
            || $this->export_available_until?->isFuture();
    }
}
