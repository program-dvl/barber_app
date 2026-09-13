<?php

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Enums\RestrictionLevel;
use App\Domain\Billing\Enums\SubscriptionStatus;
use App\Domain\Billing\Models\BillingPlan;
use App\Domain\Billing\Models\BillingPlanPrice;
use App\Domain\Billing\Models\BusinessSubscription;
use App\Domain\Billing\Models\EntitlementDefinition;
use App\Domain\Billing\Models\SubscriptionChange;
use App\Models\User;
use App\Support\Audit\AuditWriter;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use LogicException;

class SubscriptionLifecycleManager
{
    public function __construct(private readonly EntitlementEvaluator $entitlements, private readonly AuditWriter $audit) {}

    public function activate(BusinessSubscription $subscription, BillingPlanPrice $price, CarbonInterface $periodStart, CarbonInterface $periodEnd, CarbonInterface $providerStateAt, ?string $providerCustomerId = null, ?string $providerSubscriptionId = null, ?string $paymentMethodType = null, ?string $lastFour = null, ?CarbonInterface $cancelAt = null): BusinessSubscription
    {
        $updated = $this->transition($subscription, $providerStateAt, function (BusinessSubscription $locked) use ($price, $periodStart, $periodEnd, $providerCustomerId, $providerSubscriptionId, $paymentMethodType, $lastFour, $cancelAt): array {
            return [
                'billing_plan_id' => $price->billing_plan_id,
                'billing_plan_price_id' => $price->getKey(),
                'billing_interval' => $price->billing_interval,
                'provider' => 'stripe',
                'provider_customer_id' => $providerCustomerId ?? $locked->provider_customer_id,
                'provider_subscription_id' => $providerSubscriptionId ?? $locked->provider_subscription_id,
                'status' => $cancelAt ? SubscriptionStatus::CancelScheduled : SubscriptionStatus::Active,
                'restriction_level' => RestrictionLevel::None,
                'current_period_started_at' => $periodStart,
                'current_period_ends_at' => $periodEnd,
                'grace_ends_at' => null,
                'cancel_at' => $cancelAt,
                'canceled_at' => null,
                'ended_at' => null,
                'payment_method_type' => $paymentMethodType ?? $locked->payment_method_type,
                'payment_method_last_four' => $lastFour ?? $locked->payment_method_last_four,
            ];
        }, 'subscription.activated');

        $updated->changes()
            ->whereNull('applied_at')
            ->whereNull('superseded_at')
            ->where('to_billing_plan_id', $price->billing_plan_id)
            ->where('effective_at', '<=', now())
            ->update(['applied_at' => now()]);

        return $updated;
    }

    public function requestPlanChange(BusinessSubscription $subscription, BillingPlanPrice $price, User $actor, string $reason, bool $atPeriodEnd, bool $awaitProvider = false): SubscriptionChange
    {
        return DB::transaction(function () use ($subscription, $price, $actor, $reason, $atPeriodEnd, $awaitProvider): SubscriptionChange {
            $locked = BusinessSubscription::query()->lockForUpdate()->findOrFail($subscription->getKey());
            $usage = [];
            $limits = [];
            foreach (EntitlementDefinition::query()->where('value_type', 'numeric')->pluck('key') as $key) {
                $usage[$key] = $this->entitlements->usage($locked->business, $key);
                $limits[$key] = $this->planValue($price->plan, $key);
            }
            $overLimit = collect($limits)->contains(fn ($limit, $key) => is_numeric($limit) && $usage[$key] > $limit);
            $effectiveAt = ($atPeriodEnd || $overLimit)
                ? ($locked->current_period_ends_at ?? now())
                : now();

            $locked->changes()->whereNull('applied_at')->whereNull('superseded_at')->update(['superseded_at' => now()]);
            $change = $locked->changes()->create([
                'business_id' => $locked->business_id,
                'kind' => $overLimit ? 'over_limit_downgrade' : ($effectiveAt->isFuture() ? 'scheduled_plan_change' : ($awaitProvider ? 'pending_provider_plan_change' : 'immediate_plan_change')),
                'from_billing_plan_id' => $locked->billing_plan_id,
                'to_billing_plan_id' => $price->billing_plan_id,
                'requested_at' => now(),
                'effective_at' => $effectiveAt,
                'actor_user_id' => $actor->getKey(),
                'reason' => $reason,
                'usage_snapshot' => $usage,
                'limit_snapshot' => $limits,
            ]);

            if (! $effectiveAt->isFuture() && ! $awaitProvider) {
                $this->applyPlanChange($locked, $change, $price);
            }

            $this->audit->write('subscription.plan_change.requested', $locked->business, $actor, $change, $reason, before: ['plan_id' => $locked->billing_plan_id], after: ['plan_id' => $price->billing_plan_id, 'effective_at' => $effectiveAt->toIso8601String(), 'over_limit' => $overLimit, 'usage' => $usage, 'limits' => $limits]);

            return $change->fresh();
        });
    }

    public function requiresPeriodEnd(BusinessSubscription $subscription, BillingPlanPrice $price, bool $requestedAtPeriodEnd): bool
    {
        if ($requestedAtPeriodEnd) {
            return true;
        }

        foreach (EntitlementDefinition::query()->where('value_type', 'numeric')->pluck('key') as $key) {
            $limit = $this->planValue($price->plan, $key);
            if (is_numeric($limit) && $this->entitlements->usage($subscription->business, $key) > $limit) {
                return true;
            }
        }

        return false;
    }

    public function applyDuePlanChanges(CarbonInterface $at): int
    {
        $count = 0;
        SubscriptionChange::query()->whereNull('applied_at')->whereNull('superseded_at')
            ->where('kind', '!=', 'pending_provider_plan_change')
            ->where('effective_at', '<=', $at)
            ->eachById(function (SubscriptionChange $change) use (&$count): void {
                DB::transaction(function () use ($change, &$count): void {
                    $locked = BusinessSubscription::query()->lockForUpdate()->findOrFail($change->business_subscription_id);
                    $price = BillingPlanPrice::query()->where('billing_plan_id', $change->to_billing_plan_id)
                        ->where('billing_interval', $locked->billing_interval?->value)->where('is_active', true)->latest('effective_from')->firstOrFail();
                    $this->applyPlanChange($locked, $change->fresh(), $price);
                    $count++;
                });
            });

        return $count;
    }

    public function scheduleCancellation(BusinessSubscription $subscription, User $actor, string $reason): BusinessSubscription
    {
        if (! $subscription->current_period_ends_at) {
            throw new LogicException('A paid billing period is required to schedule cancellation.');
        }

        return $this->localTransition($subscription, [
            'status' => SubscriptionStatus::CancelScheduled,
            'cancel_at' => $subscription->current_period_ends_at,
        ], 'subscription.cancellation.scheduled', $actor, $reason);
    }

    public function reactivate(BusinessSubscription $subscription, User $actor, string $reason): BusinessSubscription
    {
        abort_unless($subscription->status === SubscriptionStatus::CancelScheduled, 409, 'Only a scheduled cancellation can be reactivated.');

        return $this->localTransition($subscription, ['status' => SubscriptionStatus::Active, 'cancel_at' => null, 'canceled_at' => null], 'subscription.reactivated', $actor, $reason);
    }

    public function supportCancel(BusinessSubscription $subscription, User $actor, string $reason): BusinessSubscription
    {
        return $this->localTransition($subscription, [
            'status' => SubscriptionStatus::Canceled,
            'restriction_level' => RestrictionLevel::ReadOnly,
            'canceled_at' => now(),
            'ended_at' => now(),
            'export_available_until' => now()->addDays(config('billing.export_days_after_termination')),
        ], 'subscription.support_canceled', $actor, $reason, 'platform');
    }

    public function renewalFailed(BusinessSubscription $subscription, int $attempt, CarbonInterface $occurredAt): BusinessSubscription
    {
        $status = $attempt > 1 ? SubscriptionStatus::Grace : SubscriptionStatus::PastDue;
        $updated = $this->transition($subscription, $occurredAt, fn (BusinessSubscription $locked): array => [
            'status' => $status,
            'restriction_level' => RestrictionLevel::Warning,
            'grace_ends_at' => $locked->grace_ends_at ?? $occurredAt->copy()->addDays(config('billing.grace_days')),
        ], 'subscription.renewal_failed');

        DB::table('billing_notices')->insertOrIgnore([
            'business_id' => $updated->business_id,
            'business_subscription_id' => $updated->getKey(),
            'type' => $attempt > 1 ? 'renewal_retry_failed' : 'renewal_failed',
            'scheduled_for' => now(),
            'deduplication_key' => "renewal-failed:{$updated->getKey()}:{$occurredAt->timestamp}:{$attempt}",
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $updated;
    }

    public function advanceDunning(CarbonInterface $at): int
    {
        $count = 0;
        BusinessSubscription::query()->whereIn('status', [SubscriptionStatus::PastDue->value, SubscriptionStatus::Grace->value])
            ->whereNotNull('grace_ends_at')->where('grace_ends_at', '<=', $at)
            ->eachById(function (BusinessSubscription $subscription) use (&$count): void {
                DB::transaction(function () use ($subscription, &$count): void {
                    $locked = BusinessSubscription::query()->lockForUpdate()->findOrFail($subscription->getKey());
                    if (! in_array($locked->status, [SubscriptionStatus::PastDue, SubscriptionStatus::Grace], true)) {
                        return;
                    }
                    $before = ['status' => $locked->status->value, 'restriction_level' => $locked->restriction_level->value];
                    $locked->update([
                        'status' => SubscriptionStatus::Restricted,
                        'restriction_level' => RestrictionLevel::ReadOnly,
                        'version' => $locked->version + 1,
                    ]);
                    $this->queueNotice($locked, 'subscription_restricted', 'subscription-restricted:'.$locked->getKey());
                    $this->audit->write('subscription.restricted', $locked->business, target: $locked, before: $before, after: ['status' => 'restricted', 'restriction_level' => 'read_only'], source: 'system');
                    $count++;
                });
            });

        return $count;
    }

    /** @return array{notices: int, expired: int} */
    public function advanceTrials(CarbonInterface $at): array
    {
        $notices = 0;
        $expired = 0;
        BusinessSubscription::query()
            ->where('status', SubscriptionStatus::Trialing->value)
            ->whereNotNull('trial_ends_at')
            ->eachById(function (BusinessSubscription $subscription) use ($at, &$notices, &$expired): void {
                if ($subscription->trial_ends_at->lessThanOrEqualTo($at)) {
                    DB::transaction(function () use ($subscription, &$expired): void {
                        $locked = BusinessSubscription::query()->lockForUpdate()->findOrFail($subscription->getKey());
                        if ($locked->status !== SubscriptionStatus::Trialing || $locked->trial_ends_at?->isFuture()) {
                            return;
                        }
                        $locked->update([
                            'status' => SubscriptionStatus::Restricted,
                            'restriction_level' => RestrictionLevel::ReadOnly,
                            'version' => $locked->version + 1,
                        ]);
                        $this->queueNotice($locked, 'trial_expired', 'trial-expired:'.$locked->getKey());
                        $this->audit->write('subscription.trial.expired', $locked->business, target: $locked, after: ['status' => 'restricted', 'trial_ends_at' => $locked->trial_ends_at?->toIso8601String()], source: 'system');
                        $expired++;
                    });

                    return;
                }

                foreach ((array) config('billing.trial_notice_days', [7, 3, 1]) as $days) {
                    $days = (int) $days;
                    if ($subscription->trial_ends_at->greaterThan($at->copy()->addDays($days))) {
                        continue;
                    }
                    $notices += $this->queueNotice(
                        $subscription,
                        'trial_ending',
                        "trial-ending:{$subscription->getKey()}:{$days}",
                    ) ? 1 : 0;
                }
            });

        return ['notices' => $notices, 'expired' => $expired];
    }

    public function recover(BusinessSubscription $subscription, CarbonInterface $periodEnd, CarbonInterface $occurredAt): BusinessSubscription
    {
        return $this->transition($subscription, $occurredAt, fn (): array => [
            'status' => SubscriptionStatus::Active,
            'restriction_level' => RestrictionLevel::None,
            'grace_ends_at' => null,
            'current_period_ends_at' => $periodEnd,
        ], 'subscription.recovered');
    }

    public function providerRestricted(BusinessSubscription $subscription, CarbonInterface $occurredAt, string $providerStatus): BusinessSubscription
    {
        return $this->transition($subscription, $occurredAt, fn (): array => [
            'status' => SubscriptionStatus::Restricted,
            'restriction_level' => RestrictionLevel::ReadOnly,
            'grace_ends_at' => null,
        ], 'subscription.provider_restricted.'.$providerStatus);
    }

    public function terminate(BusinessSubscription $subscription, CarbonInterface $occurredAt): BusinessSubscription
    {
        return $this->transition($subscription, $occurredAt, fn (): array => [
            'status' => SubscriptionStatus::Terminated,
            'restriction_level' => RestrictionLevel::Closed,
            'ended_at' => $occurredAt,
            'export_available_until' => $occurredAt->copy()->addDays(config('billing.export_days_after_termination')),
        ], 'subscription.terminated');
    }

    public function supersedePlanChange(SubscriptionChange $change, User $actor, string $reason): void
    {
        DB::transaction(function () use ($change, $actor, $reason): void {
            $locked = SubscriptionChange::query()->lockForUpdate()->findOrFail($change->getKey());
            if ($locked->applied_at || $locked->superseded_at) {
                return;
            }
            $locked->update(['superseded_at' => now()]);
            $this->audit->write('subscription.plan_change.failed', $locked->business, $actor, $locked, $reason, source: 'provider');
        });
    }

    public function supersedePendingProviderPlanChanges(BusinessSubscription $subscription, User $actor, string $reason): int
    {
        return DB::transaction(function () use ($subscription, $actor, $reason): int {
            $locked = BusinessSubscription::query()->lockForUpdate()->findOrFail($subscription->getKey());
            $changes = $locked->changes()
                ->where('kind', 'pending_provider_plan_change')
                ->whereNull('applied_at')
                ->whereNull('superseded_at')
                ->lockForUpdate()
                ->get();

            foreach ($changes as $change) {
                $change->update(['superseded_at' => now()]);
                $this->audit->write('subscription.plan_change.superseded', $locked->business, $actor, $change, $reason);
            }

            return $changes->count();
        });
    }

    public function expirePendingProviderPlanChanges(BusinessSubscription $subscription, CarbonInterface $occurredAt): int
    {
        return DB::transaction(function () use ($subscription, $occurredAt): int {
            $locked = BusinessSubscription::query()->lockForUpdate()->findOrFail($subscription->getKey());
            $changes = $locked->changes()
                ->where('kind', 'pending_provider_plan_change')
                ->whereNull('applied_at')
                ->whereNull('superseded_at')
                ->lockForUpdate()
                ->get();

            foreach ($changes as $change) {
                $change->update(['superseded_at' => $occurredAt]);
                $this->audit->write('subscription.plan_change.expired', $locked->business, target: $change, after: [
                    'superseded_at' => $occurredAt->toIso8601String(),
                ], source: 'provider');
            }

            return $changes->count();
        });
    }

    private function transition(BusinessSubscription $subscription, CarbonInterface $providerStateAt, callable $attributes, string $action): BusinessSubscription
    {
        return DB::transaction(function () use ($subscription, $providerStateAt, $attributes, $action): BusinessSubscription {
            $locked = BusinessSubscription::query()->lockForUpdate()->findOrFail($subscription->getKey());
            if ($locked->provider_state_at && $providerStateAt->lessThanOrEqualTo($locked->provider_state_at)) {
                return $locked;
            }
            $before = ['status' => $locked->status->value, 'restriction_level' => $locked->restriction_level->value, 'plan_id' => $locked->billing_plan_id, 'price_id' => $locked->billing_plan_price_id, 'billing_interval' => $locked->billing_interval?->value];
            $locked->fill($attributes($locked));
            $locked->provider_state_at = $providerStateAt;
            $locked->version++;
            $locked->save();
            $this->audit->write($action, $locked->business, target: $locked, before: $before, after: ['status' => $locked->status->value, 'restriction_level' => $locked->restriction_level->value, 'plan_id' => $locked->billing_plan_id, 'price_id' => $locked->billing_plan_price_id, 'billing_interval' => $locked->billing_interval?->value], source: 'provider');

            return $locked->fresh();
        });
    }

    private function localTransition(BusinessSubscription $subscription, array $attributes, string $action, User $actor, string $reason, string $source = 'application'): BusinessSubscription
    {
        return DB::transaction(function () use ($subscription, $attributes, $action, $actor, $reason, $source): BusinessSubscription {
            $locked = BusinessSubscription::query()->lockForUpdate()->findOrFail($subscription->getKey());
            $before = ['status' => $locked->status->value, 'restriction_level' => $locked->restriction_level->value];
            $locked->fill($attributes);
            $locked->version++;
            $locked->save();
            $this->audit->write($action, $locked->business, $actor, $locked, $reason, $before, ['status' => $locked->status->value, 'restriction_level' => $locked->restriction_level->value], source: $source);

            return $locked->fresh();
        });
    }

    private function applyPlanChange(BusinessSubscription $subscription, SubscriptionChange $change, BillingPlanPrice $price): void
    {
        $subscription->update(['billing_plan_id' => $price->billing_plan_id, 'billing_plan_price_id' => $price->getKey(), 'billing_interval' => $price->billing_interval, 'version' => $subscription->version + 1]);
        $change->update(['applied_at' => now()]);
    }

    private function planValue(BillingPlan $plan, string $key): mixed
    {
        return $plan->entitlements()->whereHas('definition', fn ($query) => $query->where('key', $key))
            ->where('effective_from', '<=', now())->where(fn ($query) => $query->whereNull('effective_until')->orWhere('effective_until', '>', now()))
            ->latest('effective_from')->first()?->value;
    }

    private function queueNotice(BusinessSubscription $subscription, string $type, string $deduplicationKey): bool
    {
        return DB::table('billing_notices')->insertOrIgnore([
            'business_id' => $subscription->business_id,
            'business_subscription_id' => $subscription->getKey(),
            'type' => $type,
            'scheduled_for' => now(),
            'deduplication_key' => $deduplicationKey,
            'created_at' => now(),
            'updated_at' => now(),
        ]) === 1;
    }
}
