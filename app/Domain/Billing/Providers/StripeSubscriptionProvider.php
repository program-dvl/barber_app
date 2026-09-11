<?php

namespace App\Domain\Billing\Providers;

use App\Domain\Billing\Contracts\SubscriptionProvider;
use App\Domain\Billing\Models\BillingCheckoutAttempt;
use App\Domain\Billing\Models\BillingPlanPrice;
use App\Domain\Billing\Models\BusinessSubscription;
use App\Domain\PlatformAccess\Models\Business;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use LogicException;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class StripeSubscriptionProvider implements SubscriptionProvider
{
    private ?StripeClient $client = null;

    public function createCheckout(Business $business, BillingPlanPrice $price, BillingCheckoutAttempt $attempt, string $successUrl, string $cancelUrl, ?string $couponCode = null): array
    {
        abort_unless($price->provider === 'stripe' && str_starts_with((string) $price->provider_price_id, 'price_'), 422, 'The selected Stripe price is not configured.');

        return $this->guardProviderOperation($business->getKey(), 'checkout', function () use ($business, $price, $attempt, $successUrl, $cancelUrl, $couponCode): array {
            $subscription = $business->subscription()->firstOrFail();
            $customerId = $subscription->provider_customer_id;

            if (! $customerId) {
                $owner = $this->ownerFor($business);
                $customer = $this->stripe()->customers->create([
                    'name' => $business->name,
                    'email' => $owner->email,
                    'metadata' => [
                        'application' => 'clipperdesk',
                        'business_public_id' => $business->public_id,
                    ],
                ], ['idempotency_key' => 'clipperdesk-customer-'.$business->public_id]);
                $customerId = $customer->id;
                $subscription->update(['provider' => 'stripe', 'provider_customer_id' => $customerId]);
            }

            $metadata = [
                'application' => 'clipperdesk',
                'business_public_id' => $business->public_id,
                'billing_checkout_attempt_id' => $attempt->public_id,
                'plan_price_id' => (string) $price->getKey(),
            ];
            $parameters = [
                'mode' => 'subscription',
                'customer' => $customerId,
                'line_items' => [['price' => $price->provider_price_id, 'quantity' => 1]],
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'allow_promotion_codes' => $couponCode === null,
                'client_reference_id' => $business->public_id,
                'metadata' => $metadata,
                'subscription_data' => ['metadata' => $metadata],
            ];

            if ($couponCode) {
                $discountType = str_starts_with($couponCode, 'promo_') ? 'promotion_code' : 'coupon';
                $parameters['discounts'] = [[$discountType => $couponCode]];
            }

            $session = $this->stripe()->checkout->sessions->create(
                $parameters,
                ['idempotency_key' => 'clipperdesk-checkout-'.$attempt->public_id],
            );

            return ['url' => (string) $session->url, 'provider_session_id' => $session->id];
        });
    }

    public function expireCheckout(BillingCheckoutAttempt $attempt): void
    {
        if ($attempt->provider !== 'stripe' || ! str_starts_with((string) $attempt->provider_transaction_id, 'cs_')) {
            return;
        }

        $this->guardProviderOperation(
            $attempt->business_id,
            'expire_checkout',
            fn () => $this->stripe()->checkout->sessions->expire($attempt->provider_transaction_id, []),
        );
    }

    public function changePrice(BusinessSubscription $subscription, BillingPlanPrice $price, bool $atPeriodEnd): void
    {
        $this->guardProviderOperation($subscription->business_id, 'plan_change', function () use ($subscription, $price, $atPeriodEnd): void {
            $provider = $this->providerSubscription($subscription);
            $itemId = $provider->items->data[0]->id ?? throw new LogicException('Stripe subscription has no item.');

            if ($atPeriodEnd) {
                $item = $provider->items->data[0];
                $periodStart = $item->current_period_start ?? $provider->current_period_start;
                $periodEnd = $item->current_period_end ?? $provider->current_period_end;
                $schedule = is_string($provider->schedule ?? null)
                    ? $this->stripe()->subscriptionSchedules->retrieve($provider->schedule, [])
                    : $this->stripe()->subscriptionSchedules->create(['from_subscription' => $this->providerId($subscription)]);
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
                'proration_behavior' => 'always_invoice',
                'payment_behavior' => 'pending_if_incomplete',
            ]);
        });
    }

    public function cancelAtPeriodEnd(BusinessSubscription $subscription): void
    {
        $this->guardProviderOperation($subscription->business_id, 'cancel_at_period_end', fn () => $this->stripe()->subscriptions->update($this->providerId($subscription), ['cancel_at_period_end' => true]));
    }

    public function cancelImmediately(BusinessSubscription $subscription): void
    {
        $this->guardProviderOperation($subscription->business_id, 'cancel_immediately', fn () => $this->stripe()->subscriptions->cancel($this->providerId($subscription)));
    }

    public function reactivate(BusinessSubscription $subscription): void
    {
        $this->guardProviderOperation($subscription->business_id, 'reactivate', fn () => $this->stripe()->subscriptions->update($this->providerId($subscription), ['cancel_at_period_end' => false]));
    }

    public function billingPortalUrl(BusinessSubscription $subscription, string $returnUrl): string
    {
        return $this->guardProviderOperation($subscription->business_id, 'billing_portal', function () use ($subscription, $returnUrl): string {
            $session = $this->stripe()->billingPortal->sessions->create([
                'customer' => $subscription->provider_customer_id ?? throw new LogicException('Stripe customer is missing.'),
                'return_url' => $returnUrl,
            ]);

            return (string) $session->url;
        });
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

    private function guardProviderOperation(int $businessId, string $operation, callable $callback): mixed
    {
        try {
            return $callback();
        } catch (ApiErrorException $exception) {
            Log::warning('Stripe subscription operation failed.', [
                'business_id' => $businessId,
                'operation' => $operation,
                'provider_code' => $exception->getStripeCode(),
                'provider_http_status' => $exception->getHttpStatus(),
            ]);

            abort(503, 'The billing service is temporarily unavailable. No subscription change was made. Please try again shortly.');
        }
    }

    private function ownerFor(Business $business): User
    {
        $previousBusinessId = getPermissionsTeamId();
        setPermissionsTeamId($business->getKey());

        try {
            return $business->memberships()
                ->with('user')
                ->whereHas('roles', fn ($query) => $query->where('name', 'owner'))
                ->firstOrFail()
                ->user;
        } finally {
            setPermissionsTeamId($previousBusinessId);
        }
    }
}
