<?php

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Models\BillingPlan;
use Illuminate\Support\Facades\Schema;

class PublicPricingCatalog
{
    private const PLAN_CODES = ['starter', 'pro'];

    private const INTERVALS = ['monthly', 'annual'];

    /** @return array{available: bool, currency: ?string, trial_days: int, plans: array<int, array<string, mixed>>, reason: ?string} */
    public function present(): array
    {
        if (! Schema::hasTable('billing_plans')) {
            return $this->unavailable('The billing catalog has not been installed in this environment.');
        }

        $now = now();
        $provider = (string) config('billing.provider');
        $plans = BillingPlan::query()
            ->whereIn('code', self::PLAN_CODES)
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('available_from')->orWhere('available_from', '<=', $now))
            ->where(fn ($query) => $query->whereNull('available_until')->orWhere('available_until', '>', $now))
            ->with([
                'prices' => fn ($query) => $query
                    ->where('provider', $provider)
                    ->where('is_active', true)
                    ->where('effective_from', '<=', $now)
                    ->where(fn ($effective) => $effective->whereNull('effective_until')->orWhere('effective_until', '>', $now)),
                'entitlements' => fn ($query) => $query
                    ->where('effective_from', '<=', $now)
                    ->where(fn ($effective) => $effective->whereNull('effective_until')->orWhere('effective_until', '>', $now))
                    ->with('definition'),
            ])
            ->get()
            ->keyBy('code');

        if ($provider !== 'paddle' || $plans->keys()->sort()->values()->all() !== collect(self::PLAN_CODES)->sort()->values()->all()) {
            return $this->unavailable('The approved public Paddle catalog is not fully available.');
        }

        $currencies = collect();
        $presented = collect(self::PLAN_CODES)->map(function (string $code) use ($plans, $currencies): ?array {
            $plan = $plans[$code];
            $prices = $plan->prices->keyBy(fn ($price) => $price->billing_interval->value);

            foreach (self::INTERVALS as $interval) {
                $price = $prices->get($interval);
                if (! $price || ! str_starts_with((string) $price->provider_price_id, 'pri_')) {
                    return null;
                }
                $currencies->push(strtoupper($price->currency));
            }

            $monthly = $prices['monthly'];
            $annual = $prices['annual'];
            $entitlements = $plan->entitlements
                ->filter(fn ($entitlement) => $entitlement->definition)
                ->mapWithKeys(fn ($entitlement) => [$entitlement->definition->key => $entitlement->value]);

            return [
                'code' => $plan->code,
                'name' => $plan->name,
                'description' => $plan->description,
                'prices' => [
                    'monthly' => ['amount_minor' => $monthly->amount_minor, 'currency' => strtoupper($monthly->currency)],
                    'annual' => ['amount_minor' => $annual->amount_minor, 'currency' => strtoupper($annual->currency)],
                ],
                'annual_savings_minor' => max(0, ($monthly->amount_minor * 12) - $annual->amount_minor),
                'entitlements' => $entitlements->all(),
            ];
        });

        if ($presented->contains(null) || $currencies->unique()->count() !== 1 || $currencies->first() !== 'USD') {
            return $this->unavailable('The approved public prices are incomplete or use an unsupported currency.');
        }

        return [
            'available' => true,
            'currency' => 'USD',
            'trial_days' => (int) config('billing.trial_days'),
            'plans' => $presented->values()->all(),
            'reason' => null,
        ];
    }

    public function validSelection(?string $plan, ?string $interval): ?array
    {
        if (! in_array($plan, self::PLAN_CODES, true) || ! in_array($interval, self::INTERVALS, true)) {
            return null;
        }

        $catalog = $this->present();
        if (! $catalog['available'] || ! collect($catalog['plans'])->contains('code', $plan)) {
            return null;
        }

        return ['plan' => $plan, 'interval' => $interval];
    }

    /** @return array{available: false, currency: null, trial_days: int, plans: array<int, never>, reason: string} */
    private function unavailable(string $reason): array
    {
        return ['available' => false, 'currency' => null, 'trial_days' => (int) config('billing.trial_days'), 'plans' => [], 'reason' => $reason];
    }
}
