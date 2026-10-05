<?php

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Models\BillingCapacityChange;
use App\Domain\Billing\Models\BusinessSubscription;
use App\Domain\Billing\Models\SmsCreditPurchase;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class CapacityStripeGateway
{
    private ?StripeClient $client = null;

    public function preview(BusinessSubscription $subscription, array $quote, string $kind, int $at): array
    {
        return $this->safe(function () use ($subscription, $quote, $kind, $at): array {
            $provider = $this->subscription($subscription);
            abort_if(filled($provider->schedule), 409, 'A billing change is already scheduled. Resolve it before making another change.');
            if ($kind === 'renewal') {
                return ['due_today_minor' => 0, 'tax_minor' => null, 'currency' => $quote['currency']];
            }
            $invoice = $this->stripe()->invoices->createPreview([
                'customer' => $subscription->provider_customer_id,
                'subscription' => $subscription->provider_subscription_id,
                'subscription_details' => [
                    'items' => $this->updateItems($provider->toArray(), $quote),
                    'proration_date' => $at, 'proration_behavior' => 'always_invoice',
                ],
            ]);
            abort_unless(strtoupper($invoice->currency) === $quote['currency'], 409, 'The invoice currency needs verification.');

            // Never describe a renewal invoice as today's expansion adjustment.
            abort_if(collect($invoice->lines->data ?? [])->contains(fn ($line) => ($line->amount ?? 0) !== 0
                && ! ($line->parent->subscription_item_details->proration ?? $line->proration ?? false)), 409, 'The invoice contains additional charges. Review billing before changing capacity.');

            return ['due_today_minor' => (int) $invoice->amount_due, 'tax_minor' => (int) collect($invoice->total_taxes ?? [])->sum('amount'), 'currency' => $quote['currency']];
        });
    }

    public function submit(BusinessSubscription $subscription, BillingCapacityChange $change): void
    {
        $this->safe(function () use ($subscription, $change): void {
            $provider = $this->subscription($subscription);
            abort_if(filled($provider->schedule), 409, 'A billing change is already scheduled. Refresh billing.');
            $key = 'clipperdesk-capacity-'.$change->public_id;
            if ($change->kind === 'renewal') {
                $items = collect($provider->items->data)->map(fn ($item) => ['price' => $item->price->id, 'quantity' => $item->quantity])->all();
                $first = $provider->items->data[0];
                $end = $first->current_period_end ?? $provider->current_period_end;
                $start = $first->current_period_start ?? $provider->current_period_start;
                abort_unless($end === $subscription->current_period_ends_at?->timestamp, 409, 'The renewal date changed. Refresh billing.');
                $schedule = $this->stripe()->subscriptionSchedules->create(['from_subscription' => $provider->id], ['idempotency_key' => $key.'-schedule']);
                $tax = ['automatic_tax' => ['enabled' => (bool) ($provider->automatic_tax->enabled ?? false)],
                    'default_tax_rates' => collect($provider->default_tax_rates ?? [])->map(fn ($rate) => is_string($rate) ? $rate : $rate->id)->all()];
                $this->stripe()->subscriptionSchedules->update($schedule->id, [
                    'end_behavior' => 'release', 'proration_behavior' => 'none',
                    'phases' => [
                        [...$tax, 'start_date' => $start, 'end_date' => $end, 'items' => $items, 'proration_behavior' => 'none'],
                        [...$tax, 'start_date' => $end, 'duration' => ['interval' => $change->quote['billing_interval'] === 'annual' ? 'year' : 'month', 'interval_count' => 1], 'items' => app(CapacityPricingCatalog::class)->lineItems($change->quote), 'proration_behavior' => 'none'],
                    ],
                ], ['idempotency_key' => $key.'-phases']);

                return;
            }
            $this->stripe()->subscriptions->update($provider->id, [
                'items' => $this->updateItems($provider->toArray(), $change->quote),
                'proration_behavior' => 'always_invoice', 'payment_behavior' => 'pending_if_incomplete',
                'proration_date' => $change->quote['proration_date'],
            ], ['idempotency_key' => $key]);
        });
    }

    public function topupCheckout(BusinessSubscription $subscription, SmsCreditPurchase $purchase, string $returnUrl): array
    {
        return $this->safe(function () use ($subscription, $purchase, $returnUrl): array {
            $metadata = ['business_public_id' => $subscription->business->public_id, 'sms_purchase_id' => $purchase->public_id, 'billing_purpose' => 'sms_topup'];
            $session = $this->stripe()->checkout->sessions->create([
                'mode' => 'payment', 'customer' => $subscription->provider_customer_id,
                'line_items' => [['price' => $purchase->quote['price_id'], 'quantity' => 1]],
                'automatic_tax' => ['enabled' => (bool) config('capacity-billing.automatic_tax')],
                'customer_update' => ['address' => 'auto'],
                'metadata' => $metadata, 'payment_intent_data' => ['metadata' => $metadata],
                'invoice_creation' => ['enabled' => true, 'invoice_data' => ['metadata' => $metadata]],
                'success_url' => $returnUrl.'?sms_purchase='.$purchase->public_id,
                'cancel_url' => $returnUrl,
            ], ['idempotency_key' => 'clipperdesk-sms-'.$purchase->public_id]);

            return ['id' => $session->id, 'url' => $session->url];
        });
    }

    public function reconcilePurchase(SmsCreditPurchase $purchase): void
    {
        $this->safe(function () use ($purchase): void {
            abort_unless(str_starts_with((string) $purchase->provider_session_id, 'cs_'), 409, 'Checkout preparation is still pending.');
            $session = $this->stripe()->checkout->sessions->retrieve($purchase->provider_session_id, []);
            app(SmsCreditWallet::class)->confirmPurchase($session->toArray());
        });
    }

    public function updateItems(array $provider, array $quote): array
    {
        $existing = collect(data_get($provider, 'items.data', []));
        $desired = app(CapacityPricingCatalog::class)->lineItems($quote);
        $result = [];
        $retained = [];
        foreach ($desired as $item) {
            $old = $existing->first(fn ($row) => data_get($row, 'price.id') === $item['price']);
            $result[] = $old ? ['id' => $old['id'], 'quantity' => $item['quantity']] : $item;
            if ($old) {
                $retained[] = $old['id'];
            }
        }
        foreach ($existing as $item) {
            if (! in_array($item['id'], $retained, true)) {
                $result[] = ['id' => $item['id'], 'deleted' => true];
            }
        }

        return $result;
    }

    private function subscription(BusinessSubscription $subscription): object
    {
        $provider = $this->stripe()->subscriptions->retrieve($subscription->provider_subscription_id, []);
        abort_unless($provider->customer === $subscription->provider_customer_id && $provider->status === 'active'
            && ! $provider->cancel_at_period_end && empty($provider->pending_update), 409, 'Billing needs verification before changing capacity.');
        abort_if(($provider->collection_method ?? 'charge_automatically') !== 'charge_automatically'
            || ! empty($provider->discounts), 409, 'Your tax or discount settings require a separate billing review.');
        $expected = $subscription->capacity_snapshot
            ? app(CapacityPricingCatalog::class)->lineItems($subscription->capacity_snapshot)
            : [['price' => $subscription->price?->provider_price_id, 'quantity' => 1]];
        $actual = collect($provider->items->data)->map(fn ($item) => ['price' => $item->price->id, 'quantity' => $item->quantity])->sortBy('price')->values()->all();
        abort_unless($actual === collect($expected)->sortBy('price')->values()->all(), 409, 'Your subscription changed. Refresh billing before continuing.');

        return $provider;
    }

    protected function stripe(): StripeClient
    {
        abort_unless(app(StripeBillingReadiness::class)->status()['checkout_ready'], 503, 'Secure billing is not configured.');

        return $this->client ??= new StripeClient((string) config('billing.stripe.secret'));
    }

    private function safe(callable $operation): mixed
    {
        try {
            return $operation();
        } catch (ApiErrorException $error) {
            Log::warning('Capacity billing provider request needs verification.', ['provider_code' => $error->getStripeCode(), 'status' => $error->getHttpStatus()]);
            abort(503, 'We could not confirm the billing request. Refresh billing before trying again.');
        }
    }
}
