<?php

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Models\BillingRateCard;
use Illuminate\Validation\ValidationException;

class CapacityPricingCatalog
{
    public function terms(string $market, string $interval): array
    {
        $country = config('capacity-billing.markets.'.strtoupper($market));
        if (! is_array($country) || ! in_array($interval, ['monthly', 'annual'], true) || ! is_array($country[$interval] ?? null)) {
            throw ValidationException::withMessages(['market' => 'Pricing is not yet available for this country and billing interval.']);
        }
        $rates = $country[$interval];
        foreach (['base_minor', 'location_minor', 'staff_minor'] as $key) {
            if (! is_int($rates[$key] ?? null) || $rates[$key] < 1) {
                throw ValidationException::withMessages(['market' => 'This rate card needs review.']);
            }
        }
        abort_unless(in_array($country['currency'] ?? '', ['USD', 'CAD', 'GBP', 'EUR', 'INR', 'AUD'], true), 422, 'Unsupported billing currency.');

        return [
            'market' => strtoupper($market), 'currency' => $country['currency'],
            'revision' => $country['revision'], 'billing_interval' => $interval,
            ...$rates, 'sms_per_staff' => (int) $country['sms_per_staff'],
            'sms_routes' => (array) $country['sms_routes'], 'sms_packs' => (array) ($country['sms_packs'] ?? []),
            'features' => config('capacity-billing.features'),
        ];
    }

    public function fingerprint(array $terms): string
    {
        return hash('sha256', json_encode($terms, JSON_THROW_ON_ERROR));
    }

    public function verified(string $market, string $interval): ?BillingRateCard
    {
        $terms = $this->terms($market, $interval);
        if (! config('capacity-billing.enabled') || ! config('capacity-billing.markets.'.$terms['market'].'.approved')) {
            return null;
        }

        return BillingRateCard::query()->where('fingerprint', $this->fingerprint($terms))->first();
    }

    public function quote(string $market, string $interval, int $locations, int $staff, ?BillingRateCard $retainedCard = null): array
    {
        if ($locations < 1 || $locations > config('capacity-billing.max_locations') || $staff < 1 || $staff > config('capacity-billing.max_staff')) {
            throw ValidationException::withMessages(['locations' => 'Choose at least one location and one bookable staff member within the supported limits.']);
        }
        $terms = $retainedCard?->terms ?? $this->terms($market, $interval);
        abort_unless($terms['market'] === strtoupper($market) && $terms['billing_interval'] === $interval, 422, 'The retained billing terms do not match.');
        $card = $retainedCard ?? $this->verified($market, $interval);
        $total = $terms['base_minor'] + ($locations - 1) * $terms['location_minor'] + ($staff - 1) * $terms['staff_minor'];

        return [
            'rate_card_id' => $card?->id, 'fingerprint' => $this->fingerprint($terms),
            'market' => $terms['market'], 'currency' => $terms['currency'], 'revision' => $terms['revision'],
            'billing_interval' => $interval, 'locations' => $locations, 'staff' => $staff,
            'total_minor' => $total, 'base_minor' => $terms['base_minor'],
            'location_minor' => $terms['location_minor'], 'staff_minor' => $terms['staff_minor'],
            'sms_monthly_allowance' => $terms['sms_per_staff'] * $staff,
            'sms_routes' => $terms['sms_routes'], 'features' => $terms['features'],
            'ready' => (bool) $card && (bool) config('capacity-billing.enabled'),
        ];
    }

    public function lineItems(array $quote): array
    {
        $card = BillingRateCard::findOrFail($quote['rate_card_id']);
        abort_unless(hash_equals($card->fingerprint, $quote['fingerprint']), 409, 'Billing terms changed. Review again.');

        return $this->items($card->terms, $quote['locations'], $quote['staff']);
    }

    public function items(array $terms, int $locations, int $staff): array
    {
        $items = [['price' => $terms['base_price_id'], 'quantity' => 1]];
        if ($locations > 1) {
            $items[] = ['price' => $terms['location_price_id'], 'quantity' => $locations - 1];
        }
        if ($staff > 1) {
            $items[] = ['price' => $terms['staff_price_id'], 'quantity' => $staff - 1];
        }

        return $items;
    }

    public function present(bool $includeDrafts = false): array
    {
        $markets = [];
        foreach (config('capacity-billing.markets', []) as $market => $country) {
            $intervals = [];
            foreach (['monthly', 'annual'] as $interval) {
                if (! isset($country[$interval])) {
                    continue;
                }
                $quote = $this->quote($market, $interval, 1, 1);
                if (! $includeDrafts && ! $quote['ready']) {
                    continue;
                }
                $intervals[$interval] = $quote;
            }
            if ($intervals) {
                $markets[] = ['code' => $market, 'name' => $country['name'], 'currency' => $country['currency'], 'intervals' => $intervals];
            }
        }

        return ['markets' => $markets, 'trial_days' => (int) config('billing.trial_days'), 'available' => count($markets) > 0];
    }
}
