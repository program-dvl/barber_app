<?php

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Enums\BillingInterval;
use App\Domain\Billing\Models\BillingPlanPrice;
use Illuminate\Support\Collection;
use LogicException;

class PlanCatalog
{
    /** @return Collection<string, array<string, mixed>> */
    public function plans(): Collection
    {
        return collect(config('billing.plans', []));
    }

    /** @return list<string> */
    public function codes(): array
    {
        return $this->plans()->keys()->values()->all();
    }

    /** @return array<string, mixed> */
    public function plan(string $code): array
    {
        return $this->plans()->get($code)
            ?? throw new LogicException("Unknown billing plan [{$code}].");
    }

    public function rank(string $code): int
    {
        return (int) ($this->plan($code)['rank'] ?? 0);
    }

    public function isDowngrade(string $fromCode, string $toCode): bool
    {
        return $this->rank($toCode) < $this->rank($fromCode);
    }

    public function allows(BillingPlanPrice $price): bool
    {
        $price->loadMissing('plan:id,code');

        if ($price->provider !== 'stripe' || ! in_array($price->plan->code, $this->codes(), true)) {
            return false;
        }

        $configured = $this->price($price->plan->code, $price->billing_interval);

        $matchesConfiguredId = filled($configured['price_id'])
            && hash_equals($configured['price_id'], (string) $price->provider_price_id);
        if (! $matchesConfiguredId && ! $price->catalog_managed) {
            return false;
        }

        return $price->is_active
            && str_starts_with((string) $price->provider_price_id, 'price_')
            && strtoupper((string) $price->currency) === strtoupper((string) config('billing.stripe.currency', 'USD'))
            && $configured['amount_minor'] === (int) $price->amount_minor;
    }

    /** @return array{amount_minor: int, price_id: ?string} */
    public function price(string $code, BillingInterval|string $interval): array
    {
        $value = $interval instanceof BillingInterval ? $interval->value : $interval;
        $price = data_get($this->plan($code), "prices.{$value}");

        if (! is_array($price)) {
            throw new LogicException("Unknown billing interval [{$value}] for plan [{$code}].");
        }

        return [
            'amount_minor' => (int) ($price['amount_minor'] ?? 0),
            'price_id' => filled($price['price_id'] ?? null) ? (string) $price['price_id'] : null,
        ];
    }

    /** @return array<string, bool|int> */
    public function entitlements(string $code): array
    {
        return (array) ($this->plan($code)['entitlements'] ?? []);
    }
}
