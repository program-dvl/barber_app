<?php

namespace App\Domain\PlatformAccess\Services;

use App\Domain\Billing\Models\BillingPlanPrice;
use App\Domain\Billing\Models\BusinessSubscription;
use App\Domain\Billing\Services\SubscriptionLifecycleManager;
use App\Domain\PlatformAccess\Enums\BusinessStatus;
use App\Domain\PlatformAccess\Models\Business;
use App\Models\User;
use App\Support\Audit\AuditWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlatformBusinessLifecycleService
{
    public function __construct(private readonly AuditWriter $audit, private readonly SubscriptionLifecycleManager $subscriptions) {}

    public function changeStatus(Business $business, BusinessStatus $status, User $actor, string $reason): Business
    {
        return DB::transaction(function () use ($business, $status, $actor, $reason): Business {
            $locked = Business::query()->lockForUpdate()->findOrFail($business->id);
            $before = $locked->status->value;
            if ($before === $status->value) {
                return $locked;
            }
            $locked->update([
                'status' => $status,
                'suspended_at' => $status === BusinessStatus::Suspended ? now() : null,
                'closed_at' => $status === BusinessStatus::Closed ? now() : null,
            ]);
            $this->audit->write('platform.business.status_changed', $locked, $actor, $locked, $reason, ['status' => $before], ['status' => $status->value], source: 'platform');

            return $locked->fresh();
        });
    }

    public function extendTrial(BusinessSubscription $subscription, int $days, User $actor, string $reason): BusinessSubscription
    {
        if ($subscription->status->value !== 'trialing') {
            throw ValidationException::withMessages(['subscription' => 'Only an active trial can be extended.']);
        }
        $days = max(1, min($days, 30));
        $before = $subscription->trial_ends_at;
        $subscription->update(['trial_ends_at' => ($before && $before->isFuture() ? $before : now())->addDays($days), 'version' => $subscription->version + 1]);
        $this->audit->write('platform.subscription.trial_extended', $subscription->business, $actor, $subscription, $reason, ['trial_ends_at' => $before?->toIso8601String()], ['trial_ends_at' => $subscription->trial_ends_at->toIso8601String(), 'days' => $days], source: 'platform');

        return $subscription->fresh();
    }

    public function changePlan(BusinessSubscription $subscription, BillingPlanPrice $price, User $actor, string $reason, bool $atPeriodEnd): object
    {
        abort_unless($price->is_active, 422, 'The selected price is not active.');

        return $this->subscriptions->requestPlanChange($subscription, $price, $actor, $reason, $atPeriodEnd);
    }
}
