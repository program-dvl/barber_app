<?php

namespace App\Domain\Billing\Providers;

use App\Domain\Billing\Contracts\SubscriptionProvider;
use App\Domain\Billing\Models\BillingPlanPrice;
use App\Domain\Billing\Models\BusinessSubscription;
use App\Domain\PlatformAccess\Models\Business;
use LogicException;
use Stripe\StripeClient;

class StripeSubscriptionProvider implements SubscriptionProvider
{
    private ?StripeClient $client = null;

    public function createCheckout(Business $business, BillingPlanPrice $price, string $successUrl, string $cancelUrl, ?string $couponCode = null): array
    {
        $subscription = $business->subscription()->firstOrFail();
        $customerId = $subscription->provider_customer_id;

        if (! $customerId) {
            $owner = $business->memberships()->with('user')->whereHas('roles', fn ($query) => $query->where('name', 'owner'))->firstOrFail()->user;
            $customer = $this->stripe()->customers->create([
                'name' => $business->name,
                'email' => $owner->email,
                'metadata' => ['business_public_id' => $business->public_id],
            ]);
            $customerId = $customer->id;
            $subscription->update(['provider_customer_id' => $customerId]);
        }

        $parameters = [
            'mode' => 'subscription',
            'customer' => $customerId,
            'line_items' => [['price' => $price->provider_price_id, 'quantity' => 1]],
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'allow_promotion_codes' => $couponCode === null,
            'client_reference_id' => $business->public_id,
            'metadata' => ['business_public_id' => $business->public_id, 'plan_price_id' => (string) $price->getKey()],
            'subscription_data' => ['metadata' => ['business_public_id' => $business->public_id, 'plan_price_id' => (string) $price->getKey()]],
        ];

        if ($couponCode) {
            $parameters['discounts'] = [['promotion_code' => $couponCode]];
        }

        $session = $this->stripe()->checkout->sessions->create($parameters);

        return ['url' => (string) $session->url, 'provider_session_id' => $session->id];
    }

    public function changePrice(BusinessSubscription $subscription, BillingPlanPrice $price, bool $atPeriodEnd): void
    {
        $provider = $this->providerSubscription($subscription);
        $itemId = $provider->items->data[0]->id ?? throw new LogicException('Stripe subscription has no item.');

        if ($atPeriodEnd) {
            $item = $provider->items->data[0];
            $periodStart = $item->current_period_start ?? $provider->current_period_start;
            $periodEnd = $item->current_period_end ?? $provider->current_period_end;
            $schedule = $this->stripe()->subscriptionSchedules->create(['from_subscription' => $this->providerId($subscription)]);
            $this->stripe()->subscriptionSchedules->update($schedule->id, [
                'end_behavior' => 'release',
                'phases' => [
                    [
                        'start_date' => $periodStart,
                        'end_date' => $periodEnd,
                        'items' => [['price' => $item->price->id, 'quantity' => $item->quantity ?? 1]],
                        'proration_behavior' => 'none',
                    ],
                    [
                        'start_date' => $periodEnd,
                        'items' => [['price' => $price->provider_price_id, 'quantity' => 1]],
                        'proration_behavior' => 'none',
                    ],
                ],
            ]);

            return;
        }

        $this->stripe()->subscriptions->update($this->providerId($subscription), [
            'items' => [['id' => $itemId, 'price' => $price->provider_price_id]],
            'proration_behavior' => 'create_prorations',
        ]);
    }

    public function cancelAtPeriodEnd(BusinessSubscription $subscription): void
    {
        $this->stripe()->subscriptions->update($this->providerId($subscription), ['cancel_at_period_end' => true]);
    }

    public function cancelImmediately(BusinessSubscription $subscription): void
    {
        $this->stripe()->subscriptions->cancel($this->providerId($subscription));
    }

    public function reactivate(BusinessSubscription $subscription): void
    {
        $this->stripe()->subscriptions->update($this->providerId($subscription), ['cancel_at_period_end' => false]);
    }

    public function billingPortalUrl(BusinessSubscription $subscription, string $returnUrl): string
    {
        $session = $this->stripe()->billingPortal->sessions->create([
            'customer' => $subscription->provider_customer_id ?? throw new LogicException('Stripe customer is missing.'),
            'return_url' => $returnUrl,
        ]);

        return (string) $session->url;
    }

    private function providerSubscription(BusinessSubscription $subscription): object
    {
        return $this->stripe()->subscriptions->retrieve($this->providerId($subscription), []);
    }

    private function providerId(BusinessSubscription $subscription): string
    {
        return $subscription->provider_subscription_id ?? throw new LogicException('Stripe subscription is missing.');
    }

    private function stripe(): StripeClient
    {
        abort_unless(filled(config('billing.stripe.secret')), 503, 'Stripe billing is not configured.');

        return $this->client ??= new StripeClient((string) config('billing.stripe.secret'));
    }
}
