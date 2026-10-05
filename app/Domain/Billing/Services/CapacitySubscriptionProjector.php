<?php

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Models\BillingCapacityChange;
use App\Domain\Billing\Models\BillingPlanPrice;
use App\Domain\Billing\Models\BillingRateCard;
use App\Domain\Billing\Models\BusinessSubscription;
use App\Domain\Billing\Models\EntitlementDefinition;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CapacitySubscriptionProjector
{
    public function resolve(array $items): ?array
    {
        if (! $items || count($items) > 3) {
            return null;
        }
        $ids = array_map(fn ($item) => data_get($item, 'price.id') ?? (is_string($item['price'] ?? null) ? $item['price'] : null), $items);
        if (count(array_unique($ids)) !== count($ids)) {
            return null;
        }
        $base = BillingPlanPrice::where('provider', 'stripe')->whereIn('provider_price_id', $ids)
            ->whereHas('plan', fn ($query) => $query->where('code', 'capacity'))->first();
        if (! $base) {
            return null;
        }
        $card = BillingRateCard::where('billing_plan_price_id', $base->id)->first();
        if (! $card) {
            return null;
        }
        foreach (['current_period_start', 'current_period_end'] as $period) {
            $values = array_filter(array_column($items, $period));
            if (count(array_unique($values)) > 1) {
                return null;
            }
        }
        $locations = 1;
        $staff = 1;
        foreach ($items as $item) {
            $id = data_get($item, 'price.id') ?? $item['price'];
            $quantity = $item['quantity'] ?? null;
            if (! is_int($quantity) || $quantity < 1) {
                return null;
            }
            if ($id === $card->terms['base_price_id'] && $quantity === 1) {
                $role = 'base';
            } elseif ($id === $card->terms['location_price_id']) {
                $role = 'location';
                $locations += $quantity;
            } elseif ($id === $card->terms['staff_price_id']) {
                $role = 'staff';
                $staff += $quantity;
            } else {
                return null;
            }
            if (is_array($item['price'])) {
                if (strtoupper($item['price']['currency'] ?? '') !== $card->currency
                    || ($item['price']['unit_amount'] ?? null) !== $card->terms[$role.'_minor']
                    || data_get($item, 'price.recurring.interval') !== ($card->billing_interval === 'annual' ? 'year' : 'month')
                    || data_get($item, 'price.recurring.interval_count', 1) !== 1
                    || data_get($item, 'price.recurring.usage_type', 'licensed') !== 'licensed'
                    || data_get($item, 'price.billing_scheme', 'per_unit') !== 'per_unit') {
                    return null;
                }
            }
        }
        $quote = app(CapacityPricingCatalog::class)->quote($card->market, $card->billing_interval, $locations, $staff, $card);

        return ['price' => $base, 'quote' => $quote];
    }

    public function apply(BusinessSubscription $subscription, array $quote, Carbon $occurredAt): void
    {
        if (! $subscription->provider_state_at?->equalTo($occurredAt)) {
            return;
        }
        if (! $subscription->capacity_snapshot) {
            [$monthStart, $monthEnd] = app(EntitlementUsageManager::class)->period($subscription->current_period_started_at, $subscription->current_period_ends_at, true);
            $definition = EntitlementDefinition::where('key', 'messaging.monthly_allowance')->value('id');
            foreach (DB::table('entitlement_usage')->where('business_id', $subscription->business_id)->where('entitlement_definition_id', $definition)
                ->where('period_started_at', '<=', now())->where('period_ends_at', '>', now())->lockForUpdate()->get() as $usage) {
                DB::table('entitlement_usage')->where('id', $usage->id)->update([
                    'period_ends_at' => Carbon::parse($usage->period_started_at)->equalTo($monthStart) ? $monthEnd : now(), 'updated_at' => now(),
                ]);
            }
        }
        $subscription->update(['billing_rate_card_id' => $quote['rate_card_id'], 'capacity_snapshot' => $quote]);
        foreach (BillingCapacityChange::where('business_subscription_id', $subscription->id)->whereIn('status', ['submitted', 'scheduled'])->get() as $change) {
            if ($change->quote['fingerprint'] === $quote['fingerprint'] && $change->quote['locations'] === $quote['locations'] && $change->quote['staff'] === $quote['staff']) {
                $change->update(['status' => 'applied', 'applied_at' => $change->applied_at ?? now()]);
            }
        }
    }

    public function schedule(BusinessSubscription $subscription, array $object, Carbon $occurredAt): void
    {
        $active = in_array($object['status'] ?? '', ['active', 'not_started'], true);
        $future = collect($object['phases'] ?? [])->filter(fn ($phase) => ($phase['start_date'] ?? 0) > now()->timestamp)->sortBy('start_date')->first();
        $resolved = $active && $future ? $this->resolve($future['items'] ?? []) : null;
        foreach (BillingCapacityChange::where('business_subscription_id', $subscription->id)->whereIn('status', ['submitted', 'scheduled'])->where('kind', 'renewal')->get() as $change) {
            if ($resolved && $change->quote['fingerprint'] === $resolved['quote']['fingerprint']
                && $change->quote['locations'] === $resolved['quote']['locations'] && $change->quote['staff'] === $resolved['quote']['staff']) {
                $change->update(['status' => 'scheduled', 'effective_at' => Carbon::createFromTimestampUTC($future['start_date']), 'last_checked_at' => now()]);
            } elseif (! $active && $change->status === 'scheduled') {
                $change->update(['status' => 'cancelled', 'last_checked_at' => now()]);
            }
        }
    }
}
