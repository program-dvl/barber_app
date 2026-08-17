<?php

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Enums\RestrictionLevel;
use App\Domain\Billing\Enums\SubscriptionStatus;
use App\Domain\Billing\Models\BusinessEntitlementOverride;
use App\Domain\Billing\Models\BusinessSubscription;
use App\Domain\Billing\Models\EntitlementDefinition;
use App\Domain\PlatformAccess\Models\Business;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class EntitlementEvaluator
{
    public const ALWAYS_AVAILABLE = ['billing.manage', 'exports.enabled'];

    public function value(Business $business, string $key, ?Carbon $at = null): mixed
    {
        $at ??= now();
        $definition = EntitlementDefinition::query()->where('key', $key)->first();

        if (! $definition) {
            return null;
        }

        $override = BusinessEntitlementOverride::query()
            ->where('business_id', $business->getKey())
            ->where('entitlement_definition_id', $definition->getKey())
            ->where('effective_from', '<=', $at)
            ->where(fn ($query) => $query->whereNull('effective_until')->orWhere('effective_until', '>', $at))
            ->latest('effective_from')
            ->first();

        if ($override) {
            return $override->value;
        }

        $subscription = $business->subscription()->with('plan')->first();
        if (! $subscription) {
            return null;
        }

        return $subscription->plan->entitlements()
            ->where('entitlement_definition_id', $definition->getKey())
            ->where('effective_from', '<=', $at)
            ->where(fn ($query) => $query->whereNull('effective_until')->orWhere('effective_until', '>', $at))
            ->latest('effective_from')
            ->first()?->value;
    }

    public function decide(Business $business, string $key, string $operation = 'use', int $increaseBy = 0): EntitlementDecision
    {
        $subscription = $business->subscription()->first();

        if (! $subscription) {
            return new EntitlementDecision(false, 'subscription_missing');
        }

        if (! $this->subscriptionAllows($subscription, $key, $operation)) {
            return new EntitlementDecision(false, 'subscription_restricted');
        }

        $value = $this->value($business, $key);

        if (is_bool($value)) {
            return new EntitlementDecision($value, $value ? 'allowed' : 'feature_disabled', $value);
        }

        if (is_int($value) || is_float($value)) {
            $usage = $this->usage($business, $key);
            $allowed = $increaseBy <= 0 || $usage + $increaseBy <= (int) $value;

            return new EntitlementDecision($allowed, $allowed ? 'allowed' : 'limit_exceeded', (int) $value, $usage);
        }

        return new EntitlementDecision(false, 'entitlement_missing', $value);
    }

    public function authorize(Business $business, string $key, string $operation = 'use', int $increaseBy = 0): void
    {
        $decision = $this->decide($business, $key, $operation, $increaseBy);

        if (! $decision->allowed) {
            throw new AuthorizationException("Entitlement denied: {$decision->code}.");
        }
    }

    public function usage(Business $business, string $key): int
    {
        return match ($key) {
            'locations.max' => $business->locations()->where('is_active', true)->count(),
            'staff.max' => $business->staffProfiles()->where('status', 'active')->count(),
            'messaging.monthly_allowance' => (int) DB::table('entitlement_usage')
                ->join('entitlement_definitions', 'entitlement_definitions.id', '=', 'entitlement_usage.entitlement_definition_id')
                ->where('entitlement_usage.business_id', $business->getKey())
                ->where('entitlement_definitions.key', $key)
                ->where('entitlement_usage.period_started_at', '<=', now())
                ->where('entitlement_usage.period_ends_at', '>', now())
                ->sum('entitlement_usage.quantity'),
            default => 0,
        };
    }

    private function subscriptionAllows(BusinessSubscription $subscription, string $key, string $operation): bool
    {
        if (in_array($key, self::ALWAYS_AVAILABLE, true)) {
            return $key !== 'exports.enabled' || $subscription->exportIsAvailable();
        }

        if ($subscription->status === SubscriptionStatus::Terminated || $subscription->restriction_level === RestrictionLevel::Closed) {
            return false;
        }

        if ($subscription->restriction_level === RestrictionLevel::ReadOnly) {
            return in_array($operation, ['read', 'delete', 'deactivate'], true);
        }

        return $subscription->status->permitsNormalWrites();
    }
}
