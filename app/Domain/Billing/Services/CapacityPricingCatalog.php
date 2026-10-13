<?php

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Models\BillingRateCard;
use App\Domain\PlatformAccess\Models\Business;
use Illuminate\Validation\ValidationException;

class CapacityPricingCatalog
{
    public function marketFor(Business $business): string
    {
        return config('capacity-billing.pricing_mode') === 'usd'
            ? (string) config('capacity-billing.default_market', 'US')
            : strtoupper($business->country_code);
    }

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
        if (array_key_exists('staff_tiers', $rates)) {
            $previous = 0;
            $tiers = $rates['staff_tiers'];
            if (! is_array($tiers) || ! array_is_list($tiers) || empty($tiers)) {
                throw ValidationException::withMessages(['market' => 'Progressive staff rates need review.']);
            }
            foreach ($tiers as $index => $tier) {
                $bound = $tier['up_to'] ?? null;
                if (! is_int($tier['unit_minor'] ?? null) || $tier['unit_minor'] < 1
                    || ($index === array_key_last($tiers) ? $bound !== null : (! is_int($bound) || $bound <= $previous))) {
                    throw ValidationException::withMessages(['market' => 'Progressive staff rates need review.']);
                }
                $previous = $bound ?? $previous;
            }
        }
        abort_unless(in_array($country['currency'] ?? '', ['USD', 'CAD', 'GBP', 'EUR', 'INR', 'AUD'], true), 422, 'Unsupported billing currency.');

        $terms = [
            'market' => strtoupper($market), 'currency' => $country['currency'],
            'revision' => $country['revision'], 'billing_interval' => $interval,
            ...$rates, 'included_locations' => (int) config('capacity-billing.included_locations'),
            'included_staff' => (int) config('capacity-billing.included_staff'), 'sms_per_staff' => (int) $country['sms_per_staff'],
            'sms_routes' => (array) $country['sms_routes'], 'sms_packs' => (array) ($country['sms_packs'] ?? []),
            'features' => config('capacity-billing.features'),
        ];
        // Command-managed mappings survive deployment without copying IDs to .env.
        if (collect(['base', 'location', 'staff'])->contains(fn ($role) => blank($terms[$role.'_price_id']))) {
            $cards = BillingRateCard::where('market', $terms['market'])->where('revision', $terms['revision'])
                ->where('billing_interval', $interval)->latest('id')->get();
            foreach ($cards as $card) {
                $candidate = $terms;
                foreach (['base', 'location', 'staff'] as $role) {
                    $candidate[$role.'_price_id'] = $candidate[$role.'_price_id'] ?: $card->terms[$role.'_price_id'];
                }
                if ($this->fingerprint($candidate) === $card->fingerprint) {
                    return $candidate;
                }
            }
        }

        return $terms;
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
        $staffLines = $this->staffLines($terms, $staff);
        $total = $terms['base_minor'] + max(0, $locations - ($terms['included_locations'] ?? 1)) * $terms['location_minor'] + array_sum(array_column($staffLines, 'total_minor'));

        return [
            'rate_card_id' => $card?->id, 'fingerprint' => $this->fingerprint($terms),
            'market' => $terms['market'], 'currency' => $terms['currency'], 'revision' => $terms['revision'],
            'billing_interval' => $interval, 'locations' => $locations, 'staff' => $staff,
            'total_minor' => $total, 'base_minor' => $terms['base_minor'],
            'location_minor' => $terms['location_minor'], 'staff_minor' => $terms['staff_minor'],
            'staff_tiers' => $terms['staff_tiers'] ?? null, 'staff_lines' => $staffLines,
            'included_locations' => $terms['included_locations'] ?? 1, 'included_staff' => $terms['included_staff'] ?? 1,
            'sms_monthly_allowance' => $terms['sms_per_staff'] * $staff,
            'sms_routes' => $terms['sms_routes'], 'features' => $terms['features'],
            'ready' => (bool) $card && (bool) config('capacity-billing.enabled'),
        ];
    }

    /** Cumulative bounds count additional staff; the first person is included. */
    public function staffLines(array $terms, int $staff): array
    {
        $tiers = $terms['staff_tiers'] ?? [['up_to' => null, 'unit_minor' => $terms['staff_minor']]];
        $included = $terms['included_staff'] ?? 1;
        $lines = [];
        $previous = 0;
        foreach ($tiers as $tier) {
            $quantity = max(0, min($staff - $included, $tier['up_to'] ?? ($staff - $included)) - $previous);
            if ($quantity > 0) {
                $lines[] = ['from' => $previous + $included + 1, 'to' => $previous + $quantity + $included,
                    'quantity' => $quantity, 'unit_minor' => $tier['unit_minor'], 'total_minor' => $quantity * $tier['unit_minor']];
            }
            $previous = $tier['up_to'] ?? ($staff - $included);
        }

        return $lines;
    }

    /** Stripe owns recurring totals, invoices and proration using these immutable terms. */
    public function stripePriceParameters(array $terms, string $role): array
    {
        if ($role === 'staff' && ! empty($terms['staff_tiers'])) {
            return ['billing_scheme' => 'tiered', 'tiers_mode' => 'graduated',
                'tiers' => array_map(fn ($tier) => ['up_to' => $tier['up_to'] ?? 'inf', 'unit_amount' => $tier['unit_minor']], $terms['staff_tiers'])];
        }

        return ['billing_scheme' => 'per_unit', 'unit_amount' => $terms[$role.'_minor']];
    }

    public function matchesStripePrice(array $price, array $terms, string $role, bool $requireTiers = false): bool
    {
        $graduated = $role === 'staff' && ! empty($terms['staff_tiers']);
        if (($price['billing_scheme'] ?? 'per_unit') !== ($graduated ? 'tiered' : 'per_unit')
            || ! empty($price['transform_quantity']) || ! empty($price['custom_unit_amount'])) {
            return false;
        }
        if (! $graduated) {
            return ($price['unit_amount'] ?? null) === $terms[$role.'_minor'];
        }
        if (($price['tiers_mode'] ?? null) !== 'graduated' || ($price['unit_amount'] ?? null) !== null) {
            return false;
        }
        if (! isset($price['tiers'])) {
            // Subscription webhook price objects omit expanded tiers. Their IDs must
            // already match a remotely verified immutable rate card.
            return ! $requireTiers;
        }
        $tiers = array_map(fn ($tier) => ['up_to' => $tier['up_to'] ?? null,
            'unit_minor' => $tier['unit_amount'] ?? null], $price['tiers']);
        foreach ($price['tiers'] as $tier) {
            if (($tier['flat_amount'] ?? 0) != 0 || ($tier['flat_amount_decimal'] ?? '0') != 0 || (isset($tier['unit_amount_decimal']) && $tier['unit_amount_decimal'] != $tier['unit_amount'])) {
                return false;
            }
        }

        return $tiers === $terms['staff_tiers'];
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
        if ($locations > ($terms['included_locations'] ?? 1)) {
            $items[] = ['price' => $terms['location_price_id'], 'quantity' => $locations - ($terms['included_locations'] ?? 1)];
        }
        if ($staff > ($terms['included_staff'] ?? 1)) {
            $items[] = ['price' => $terms['staff_price_id'], 'quantity' => $staff - ($terms['included_staff'] ?? 1)];
        }

        return $items;
    }

    public function present(bool $includeDrafts = false): array
    {
        $markets = [];
        foreach (config('capacity-billing.markets', []) as $market => $country) {
            if (config('capacity-billing.pricing_mode') === 'usd' && $market !== config('capacity-billing.default_market')) {
                continue;
            }
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
