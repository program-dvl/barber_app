<?php

namespace App\Domain\Billing\Services;

use LogicException;
use Stripe\StripeClient;

class CapacityCatalogProvisioner
{
    /** Creates catalog objects only; never customers, subscriptions or charges. */
    public function provision(CapacityPricingCatalog $catalog, string $market): array
    {
        $stripe = new StripeClient(config('billing.stripe.secret'));
        $products = [];
        foreach ($stripe->products->all(['limit' => 100])->autoPagingIterator() as $product) {
            if (($product->metadata->application ?? null) === 'clipperdesk'
                && ($product->metadata->capacity_market ?? null) === $market) {
                $products[$product->metadata->capacity_component] = $product;
            }
        }
        $verified = [];
        foreach (['monthly', 'annual'] as $interval) {
            $terms = $catalog->terms($market, $interval);
            // Resolve in the current Stripe account/mode. Never reuse another environment's DB IDs.
            foreach (['base', 'location', 'staff'] as $role) {
                $terms[$role.'_price_id'] = config('capacity-billing.markets.'.$market.'.'.$interval.'.'.$role.'_price_id');
            }
            foreach (['base' => 'ClipperDesk subscription', 'location' => 'Additional location', 'staff' => 'Additional bookable staff'] as $role => $label) {
                if (filled($terms[$role.'_price_id'])) {
                    continue;
                }
                $metadata = ['application' => 'clipperdesk', 'capacity_market' => $market, 'capacity_component' => $role];
                $product = $products[$role] ??= $stripe->products->create([
                    'name' => $label, 'metadata' => $metadata,
                ], ['idempotency_key' => 'clipperdesk-capacity-product-'.$market.'-'.$role]);
                if (! $product->active) {
                    throw new LogicException('The capacity product is archived; review it before provisioning.');
                }
                $identity = hash('sha256', json_encode([$market, $terms['revision'], $interval, $role, $terms[$role.'_minor'], $terms['currency']]));
                $lookup = 'clipperdesk_capacity_'.$identity;
                $matches = $stripe->prices->all(['lookup_keys' => [$lookup], 'limit' => 2]);
                $price = $matches->data[0] ?? $stripe->prices->create([
                    'product' => $product->id, 'currency' => strtolower($terms['currency']),
                    'unit_amount' => $terms[$role.'_minor'], 'billing_scheme' => 'per_unit',
                    'tax_behavior' => 'exclusive', 'lookup_key' => $lookup,
                    'recurring' => ['interval' => $interval === 'annual' ? 'year' : 'month', 'interval_count' => 1, 'usage_type' => 'licensed'],
                    'metadata' => $metadata + ['revision' => $terms['revision']],
                ], ['idempotency_key' => 'clipperdesk-capacity-price-'.$identity]);
                if ($price->product !== $product->id) {
                    throw new LogicException('The lookup key belongs to another product.');
                }
                $terms[$role.'_price_id'] = $price->id;
            }
            $verified[$interval] = $terms;
        }

        return $verified;
    }
}
