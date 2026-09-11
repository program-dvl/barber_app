<?php

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Enums\BillingInterval;
use LogicException;
use Stripe\Exception\InvalidRequestException;
use Stripe\Price;
use Stripe\Product;
use Stripe\StripeClient;

class StripeCatalogProvisioner
{
    /**
     * @return array{prices: array<string, array<string, string>>, replaced_price_ids: list<string>, messages: list<string>}
     */
    public function provision(PlanCatalog $catalog): array
    {
        $secret = trim((string) config('billing.stripe.secret'));
        if ($secret === '') {
            throw new LogicException('STRIPE_SECRET must be configured before provisioning the Stripe catalog.');
        }

        $stripe = new StripeClient($secret);
        $resolved = [];
        $replacedPriceIds = [];
        $messages = [];

        foreach ($catalog->plans() as $code => $definition) {
            $code = (string) $code;
            $product = $this->managedProduct($stripe, $code);
            if ($product) {
                $product = $stripe->products->update($product->id, [
                    'active' => true,
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'metadata' => $this->metadata($code),
                ]);
                $messages[] = "Updated Stripe Product {$product->id} for {$definition['name']}.";
            } else {
                $product = $stripe->products->create([
                    'active' => true,
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'metadata' => $this->metadata($code),
                ], ['idempotency_key' => "clipperdesk-catalog-product-{$code}"]);
                $messages[] = "Created Stripe Product {$product->id} for {$definition['name']}.";
            }

            foreach ([BillingInterval::Monthly, BillingInterval::Annual] as $interval) {
                $configured = $catalog->price($code, $interval);
                $price = $this->matchingPrice($stripe, $product, $code, $interval, $configured);
                $lookupKey = $this->lookupKey($code, $interval);

                if ($price) {
                    $price = $stripe->prices->update($price->id, [
                        'active' => true,
                        'lookup_key' => $lookupKey,
                        'transfer_lookup_key' => true,
                        'nickname' => $definition['name'].' '.ucfirst($interval->value),
                        'metadata' => $this->metadata($code, $interval),
                    ]);
                    $messages[] = "Reused Stripe Price {$price->id} for {$code} {$interval->value}.";
                } else {
                    $price = $stripe->prices->create([
                        'active' => true,
                        'product' => $product->id,
                        'currency' => strtolower((string) config('billing.stripe.currency', 'USD')),
                        'unit_amount' => $configured['amount_minor'],
                        'billing_scheme' => 'per_unit',
                        'recurring' => [
                            'interval' => $interval === BillingInterval::Annual ? 'year' : 'month',
                            'interval_count' => 1,
                            'usage_type' => 'licensed',
                        ],
                        'lookup_key' => $lookupKey,
                        'transfer_lookup_key' => true,
                        'nickname' => $definition['name'].' '.ucfirst($interval->value),
                        'metadata' => $this->metadata($code, $interval),
                    ], ['idempotency_key' => $this->priceIdempotencyKey($code, $interval, $configured['amount_minor'])]);
                    $messages[] = "Created Stripe Price {$price->id} for {$code} {$interval->value}.";
                }

                $replacedPriceIds = [
                    ...$replacedPriceIds,
                    ...$this->replacedPriceIds($stripe, $product, $price, $code, $interval),
                ];
                $resolved[$code][$interval->value] = $price->id;
            }

            $stripe->products->update($product->id, ['default_price' => $resolved[$code][BillingInterval::Monthly->value]]);
        }

        return [
            'prices' => $resolved,
            'replaced_price_ids' => array_values(array_unique($replacedPriceIds)),
            'messages' => $messages,
        ];
    }

    /** @param list<string> $priceIds
     * @return list<string>
     */
    public function archiveReplaced(array $priceIds): array
    {
        $secret = trim((string) config('billing.stripe.secret'));
        if ($secret === '') {
            throw new LogicException('STRIPE_SECRET must be configured before archiving replaced prices.');
        }

        $stripe = new StripeClient($secret);
        $messages = [];
        foreach (array_values(array_unique($priceIds)) as $priceId) {
            $stripe->prices->update($priceId, ['active' => false]);
            $messages[] = "Archived replaced Stripe Price {$priceId}; existing subscriptions remain attached to it.";
        }

        return $messages;
    }

    private function managedProduct(StripeClient $stripe, string $code): ?Product
    {
        foreach ($stripe->products->all(['limit' => 100])->autoPagingIterator() as $product) {
            if ($this->metadataValue($product, 'application') === 'clipperdesk'
                && $this->metadataValue($product, 'catalog_plan') === $code) {
                return $product;
            }
        }

        return null;
    }

    /** @param array{amount_minor: int, price_id: ?string} $configured */
    private function matchingPrice(StripeClient $stripe, Product $product, string $code, BillingInterval $interval, array $configured): ?Price
    {
        if ($configured['price_id']) {
            try {
                $configuredPrice = $stripe->prices->retrieve($configured['price_id'], []);
                if ($this->matches($configuredPrice, $product, $interval, $configured['amount_minor'])) {
                    return $configuredPrice;
                }
            } catch (InvalidRequestException $exception) {
                if ($exception->getStripeCode() !== 'resource_missing') {
                    throw $exception;
                }
            }
        }

        foreach ([true, false] as $active) {
            foreach ($stripe->prices->all(['product' => $product->id, 'active' => $active, 'limit' => 100])->autoPagingIterator() as $price) {
                if ($this->metadataValue($price, 'application') === 'clipperdesk'
                    && $this->metadataValue($price, 'catalog_plan') === $code
                    && $this->metadataValue($price, 'catalog_interval') === $interval->value
                    && $this->matches($price, $product, $interval, $configured['amount_minor'])) {
                    return $price;
                }
            }
        }

        return null;
    }

    /** @return list<string> */
    private function replacedPriceIds(StripeClient $stripe, Product $product, Price $current, string $code, BillingInterval $interval): array
    {
        $priceIds = [];
        foreach ($stripe->prices->all(['product' => $product->id, 'active' => true, 'limit' => 100])->autoPagingIterator() as $price) {
            if ($price->id === $current->id
                || $this->metadataValue($price, 'application') !== 'clipperdesk'
                || $this->metadataValue($price, 'catalog_plan') !== $code
                || $this->metadataValue($price, 'catalog_interval') !== $interval->value) {
                continue;
            }

            $priceIds[] = $price->id;
        }

        return $priceIds;
    }

    private function matches(Price $price, Product $product, BillingInterval $interval, int $amountMinor): bool
    {
        $expectedInterval = $interval === BillingInterval::Annual ? 'year' : 'month';

        return (string) $price->product === $product->id
            && $price->type === 'recurring'
            && $price->billing_scheme === 'per_unit'
            && $price->recurring?->interval === $expectedInterval
            && (int) ($price->recurring?->interval_count ?? 0) === 1
            && $price->recurring?->usage_type === 'licensed'
            && strtoupper((string) $price->currency) === strtoupper((string) config('billing.stripe.currency', 'USD'))
            && (int) $price->unit_amount === $amountMinor;
    }

    /** @return array<string, string> */
    private function metadata(string $code, ?BillingInterval $interval = null): array
    {
        return array_filter([
            'application' => 'clipperdesk',
            'catalog_plan' => $code,
            'catalog_interval' => $interval?->value,
            'managed_by' => 'billing:sync-stripe-catalog',
        ], fn (?string $value): bool => $value !== null);
    }

    private function metadataValue(Product|Price $object, string $key): string
    {
        return (string) ($object->metadata[$key] ?? '');
    }

    private function lookupKey(string $code, BillingInterval $interval): string
    {
        return "clipperdesk_{$code}_{$interval->value}";
    }

    private function priceIdempotencyKey(string $code, BillingInterval $interval, int $amountMinor): string
    {
        $currency = strtolower((string) config('billing.stripe.currency', 'USD'));

        return 'clipperdesk-catalog-price-'.hash('sha256', "{$code}|{$interval->value}|{$currency}|{$amountMinor}");
    }
}
