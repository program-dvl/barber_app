<?php

namespace App\Domain\Billing\Models;

use App\Domain\PlatformAccess\Models\Business;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

class SubscriptionChange extends Model
{
    protected $fillable = ['public_id', 'business_id', 'business_subscription_id', 'kind', 'from_billing_plan_id', 'to_billing_plan_id', 'requested_at', 'effective_at', 'applied_at', 'superseded_at', 'actor_user_id', 'reason', 'usage_snapshot', 'limit_snapshot'];

    protected static function booted(): void
    {
        static::creating(fn (SubscriptionChange $change) => $change->public_id ??= (string) Str::ulid());
        static::deleting(fn () => throw new LogicException('Subscription changes are append-only.'));
    }

    protected function casts(): array
    {
        return [
            'requested_at' => 'immutable_datetime', 'effective_at' => 'immutable_datetime',
            'applied_at' => 'immutable_datetime', 'superseded_at' => 'immutable_datetime',
            'usage_snapshot' => 'array', 'limit_snapshot' => 'array',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(BusinessSubscription::class, 'business_subscription_id');
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function fromPlan(): BelongsTo
    {
        return $this->belongsTo(BillingPlan::class, 'from_billing_plan_id');
    }

    public function toPlan(): BelongsTo
    {
        return $this->belongsTo(BillingPlan::class, 'to_billing_plan_id');
    }
}
