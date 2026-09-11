<?php

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Models\EntitlementDefinition;
use App\Domain\PlatformAccess\Models\Business;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class EntitlementUsageManager
{
    public function __construct(private readonly EntitlementEvaluator $entitlements) {}

    public function reserve(Business $business, string $key, int $quantity = 1): bool
    {
        if ($quantity < 1) {
            return false;
        }

        return DB::transaction(function () use ($business, $key, $quantity): bool {
            $definition = EntitlementDefinition::query()->where('key', $key)->first();
            $subscription = $business->subscription()->lockForUpdate()->first();
            if (! $definition || ! $subscription) {
                return false;
            }

            [$periodStart, $periodEnd] = $this->period($subscription->current_period_started_at, $subscription->current_period_ends_at);
            DB::table('entitlement_usage')->insertOrIgnore([
                'business_id' => $business->getKey(),
                'entitlement_definition_id' => $definition->getKey(),
                'period_started_at' => $periodStart,
                'period_ends_at' => $periodEnd,
                'quantity' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $usage = DB::table('entitlement_usage')
                ->where('business_id', $business->getKey())
                ->where('entitlement_definition_id', $definition->getKey())
                ->where('period_started_at', $periodStart)
                ->lockForUpdate()
                ->first();

            if (! $usage || ! $this->entitlements->decide($business, $key, 'create', $quantity)->allowed) {
                return false;
            }

            DB::table('entitlement_usage')->where('id', $usage->id)->update([
                'quantity' => (int) $usage->quantity + $quantity,
                'updated_at' => now(),
            ]);

            return true;
        }, 3);
    }

    public function release(Business $business, string $key, CarbonInterface|string $chargedAt, int $quantity = 1): void
    {
        if ($quantity < 1) {
            return;
        }

        DB::transaction(function () use ($business, $key, $chargedAt, $quantity): void {
            $definitionId = EntitlementDefinition::query()->where('key', $key)->value('id');
            if (! $definitionId) {
                return;
            }

            $at = $chargedAt instanceof CarbonInterface ? $chargedAt : Carbon::parse($chargedAt);
            $usage = DB::table('entitlement_usage')
                ->where('business_id', $business->getKey())
                ->where('entitlement_definition_id', $definitionId)
                ->where('period_started_at', '<=', $at)
                ->where('period_ends_at', '>', $at)
                ->lockForUpdate()
                ->first();

            if ($usage) {
                DB::table('entitlement_usage')->where('id', $usage->id)->update([
                    'quantity' => max(0, (int) $usage->quantity - $quantity),
                    'updated_at' => now(),
                ]);
            }
        }, 3);
    }

    /** @return array{0: CarbonInterface, 1: CarbonInterface} */
    private function period(?CarbonInterface $subscriptionStart, ?CarbonInterface $subscriptionEnd): array
    {
        $now = now();
        if ($subscriptionStart && $subscriptionEnd && $subscriptionStart->lte($now) && $subscriptionEnd->gt($now)) {
            return [$subscriptionStart, $subscriptionEnd];
        }

        return [$now->copy()->startOfMonth(), $now->copy()->addMonth()->startOfMonth()];
    }
}
