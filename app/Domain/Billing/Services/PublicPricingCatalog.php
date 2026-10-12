<?php

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Models\BillingPlanPrice;
use Illuminate\Support\Facades\Schema;

class PublicPricingCatalog
{
    public function __construct(private readonly CapacityPricingCatalog $capacity) {}

    public function present(): array
    {
        $markets = [];
        if (Schema::hasTable('billing_rate_cards')) {
            foreach ($this->capacity->present()['markets'] as $market) {
                $intervals = [];
                foreach ($market['intervals'] as $interval => $quote) {
                    $card = $this->capacity->verified($market['code'], $interval);
                    $price = $card ? BillingPlanPrice::find($card->billing_plan_price_id) : null;
                    if (! $card?->verified_at || ! $price?->is_active || $price->provider !== 'stripe'
                        || $price->provider_price_id !== $card->terms['base_price_id']
                        || (int) $price->amount_minor !== $quote['base_minor'] || $price->currency !== $quote['currency']
                        || $price->effective_from?->isFuture() || ($price->effective_until && $price->effective_until->lte(now()))) {
                        continue;
                    }
                    $intervals[$interval] = collect($quote)->only([
                        'currency', 'billing_interval', 'base_minor', 'location_minor', 'staff_minor',
                        'sms_monthly_allowance', 'ready',
                    ])->all();
                }
                if ($intervals) {
                    $markets[] = ['code' => $market['code'], 'currency' => $market['currency'], 'intervals' => $intervals];
                }
            }
        }

        return [
            'available' => count($markets) > 0,
            'trial_days' => (int) config('billing.trial_days'),
            'markets' => $markets,
            'max_locations' => (int) config('capacity-billing.max_locations'),
            'max_staff' => (int) config('capacity-billing.max_staff'),
        ];
    }

    /** Preferences only: never a quote, payment authorization or entitlement. */
    public function validSelection(mixed $plan, mixed $interval, mixed $locations = 1, mixed $staff = 1): ?array
    {
        if ($plan !== 'capacity' || ! in_array($interval, ['monthly', 'annual'], true)
            || filter_var($locations, FILTER_VALIDATE_INT) === false || filter_var($staff, FILTER_VALIDATE_INT) === false
            || $locations < 1 || $locations > config('capacity-billing.max_locations')
            || $staff < 1 || $staff > config('capacity-billing.max_staff')) {
            return null;
        }
        $market = collect($this->present()['markets'])->firstWhere('code', config('capacity-billing.default_market'));
        if (! isset($market['intervals'][$interval])) {
            return null;
        }

        return ['plan' => 'capacity', 'interval' => $interval, 'locations' => (int) $locations, 'staff' => (int) $staff];
    }
}
