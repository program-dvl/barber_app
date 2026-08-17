<?php

namespace App\Domain\Billing\Models;

use App\Domain\PlatformAccess\Models\Business;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class BillingCheckoutAttempt extends Model
{
    protected $fillable = [
        'public_id', 'business_id', 'business_subscription_id', 'billing_plan_price_id',
        'created_by_user_id', 'provider', 'provider_transaction_id', 'provider_subscription_id',
        'status', 'expires_at', 'last_checked_at', 'confirmed_at', 'last_error',
    ];

    protected static function booted(): void
    {
        static::creating(fn (BillingCheckoutAttempt $attempt) => $attempt->public_id ??= (string) Str::ulid());
    }

    protected function casts(): array
    {
        return [
            'expires_at' => 'immutable_datetime',
            'last_checked_at' => 'immutable_datetime',
            'confirmed_at' => 'immutable_datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(BusinessSubscription::class, 'business_subscription_id');
    }

    public function price(): BelongsTo
    {
        return $this->belongsTo(BillingPlanPrice::class, 'billing_plan_price_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
