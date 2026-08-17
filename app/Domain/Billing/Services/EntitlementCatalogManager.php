<?php

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Models\BillingPlan;
use App\Domain\Billing\Models\BillingPlanEntitlement;
use App\Domain\Billing\Models\BusinessEntitlementOverride;
use App\Domain\Billing\Models\EntitlementDefinition;
use App\Domain\PlatformAccess\Models\Business;
use App\Models\User;
use App\Support\Audit\AuditWriter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class EntitlementCatalogManager
{
    public function __construct(private readonly AuditWriter $audit) {}

    public function changePlanEntitlement(BillingPlan $plan, string $key, mixed $value, Carbon $effectiveAt, User $actor, string $reason): BillingPlanEntitlement
    {
        return DB::transaction(function () use ($plan, $key, $value, $effectiveAt, $actor, $reason): BillingPlanEntitlement {
            $definition = EntitlementDefinition::query()->where('key', $key)->firstOrFail();
            $this->validateValue($definition->value_type, $value);
            $plan->entitlements()->where('entitlement_definition_id', $definition->getKey())
                ->whereNull('effective_until')->where('effective_from', '<', $effectiveAt)
                ->update(['effective_until' => $effectiveAt]);

            $entitlement = $plan->entitlements()->create([
                'entitlement_definition_id' => $definition->getKey(),
                'value' => $value,
                'effective_from' => $effectiveAt,
                'changed_by_user_id' => $actor->getKey(),
                'change_reason' => $reason,
            ]);
            $this->audit->write('billing.plan_entitlement.changed', actor: $actor, target: $entitlement, reason: $reason, after: ['plan' => $plan->code, 'key' => $key, 'value' => $value, 'effective_at' => $effectiveAt->toIso8601String()], source: 'platform');

            return $entitlement;
        });
    }

    public function changeBusinessOverride(Business $business, string $key, mixed $value, Carbon $effectiveAt, User $actor, string $reason): BusinessEntitlementOverride
    {
        return DB::transaction(function () use ($business, $key, $value, $effectiveAt, $actor, $reason): BusinessEntitlementOverride {
            $definition = EntitlementDefinition::query()->where('key', $key)->firstOrFail();
            $this->validateValue($definition->value_type, $value);
            BusinessEntitlementOverride::query()->where('business_id', $business->getKey())
                ->where('entitlement_definition_id', $definition->getKey())
                ->whereNull('effective_until')->where('effective_from', '<', $effectiveAt)
                ->update(['effective_until' => $effectiveAt]);

            $override = BusinessEntitlementOverride::query()->create([
                'business_id' => $business->getKey(), 'entitlement_definition_id' => $definition->getKey(),
                'value' => $value, 'effective_from' => $effectiveAt,
                'changed_by_user_id' => $actor->getKey(), 'change_reason' => $reason,
            ]);
            $this->audit->write('billing.business_entitlement.changed', $business, $actor, $override, $reason, after: ['key' => $key, 'value' => $value, 'effective_at' => $effectiveAt->toIso8601String()], source: 'platform');

            return $override;
        });
    }

    private function validateValue(string $type, mixed $value): void
    {
        abort_unless(($type === 'feature' && is_bool($value)) || ($type === 'numeric' && is_int($value) && $value >= 0), 422, 'Invalid entitlement value.');
    }
}
