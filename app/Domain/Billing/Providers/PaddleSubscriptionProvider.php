<?php

namespace App\Domain\Billing\Providers;

use App\Domain\Billing\Contracts\SubscriptionProvider;
use App\Domain\Billing\Models\BillingPlanPrice;
use App\Domain\Billing\Models\BusinessSubscription;
use App\Domain\PlatformAccess\Models\Business;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use LogicException;

/** Paddle Billing edge adapter for Good Hours SaaS subscriptions only. */
class PaddleSubscriptionProvider implements SubscriptionProvider
{
    public function createCheckout(Business $business, BillingPlanPrice $price, string $successUrl, string $cancelUrl, ?string $couponCode = null): array
    {
        try {
            // Verify a catalog mapping before creating a provider customer so an
            // environment mismatch cannot leave behind an orphan customer.
            $this->request()->get('/prices/'.$price->provider_price_id)->throw();
            $owner = $this->ownerFor($business);
            $subscription = $business->subscription()->firstOrFail();
            $customerId = $subscription->provider_customer_id;
            if (! $customerId) {
                $customer = $this->request()->post('/customers', [
                    'email' => $owner->email,
                    'name' => $business->name,
                    'custom_data' => [
                        'application' => 'good_hours',
                        'business_public_id' => $business->public_id,
                    ],
                ])->throw()->json('data');
                $customerId = (string) ($customer['id'] ?? '');
                abort_if($customerId === '', 503, 'Paddle did not create a billing customer.');
                $subscription->update(['provider_customer_id' => $customerId]);
            }
            $payload = [
                'items' => [['price_id' => $price->provider_price_id, 'quantity' => 1]],
                'collection_mode' => 'automatic',
                'customer_id' => $customerId,
                'custom_data' => [
                    'application' => 'good_hours',
                    'business_public_id' => $business->public_id,
                    'plan_price_id' => (string) $price->getKey(),
                ],
            ];
            // Paddle applies configured discounts during checkout. A code is intentionally
            // never trusted as a price change from the browser.
            if ($couponCode) {
                $payload['discount_id'] = $couponCode;
            }
            $transaction = $this->request()->post('/transactions', $payload)->throw()->json('data');
            $transactionId = (string) ($transaction['id'] ?? '');
            abort_unless($transactionId !== '', 503, 'Paddle did not create a secure checkout transaction.');

            return [
                'url' => (string) data_get($transaction, 'checkout.url', ''),
                'provider_session_id' => $transactionId,
            ];
        } catch (ConnectionException|RequestException $exception) {
            Log::warning('Paddle secure checkout could not be created.', [
                'business_id' => $business->getKey(),
                'price_id' => $price->getKey(),
                'provider_status' => $exception instanceof RequestException ? $exception->response?->status() : null,
            ]);

            abort(503, 'Secure checkout is temporarily unavailable. Please try again shortly.');
        }
    }

    public function changePrice(BusinessSubscription $subscription, BillingPlanPrice $price, bool $atPeriodEnd): void
    {
        $this->guardProviderOperation($subscription, 'plan_change', function () use ($subscription, $price, $atPeriodEnd): void {
            $provider = $this->subscription($subscription);
            $items = collect($provider['items'] ?? [])->map(fn (array $item) => [
                'price_id' => $item['price']['id'], 'quantity' => (int) ($item['quantity'] ?? 1),
            ])->all();
            abort_unless($items !== [], 409, 'Paddle subscription has no billable item.');
            $items[0] = ['price_id' => $price->provider_price_id, 'quantity' => 1];

            $this->request()->patch('/subscriptions/'.$this->providerId($subscription), [
                'items' => $items,
                'proration_billing_mode' => $atPeriodEnd ? 'prorated_next_billing_period' : 'prorated_immediately',
            ])->throw();
        });
    }

    /** @return array<string, mixed> */
    public function transaction(BusinessSubscription $subscription, string $transactionId): array
    {
        abort_unless((bool) preg_match('/^txn_[a-z0-9]+$/', $transactionId), 422, 'Invalid checkout transaction.');

        return $this->guardProviderOperation($subscription, 'checkout_confirmation', fn () => $this->request()
            ->get('/transactions/'.$transactionId)
            ->throw()
            ->json('data'));
    }

    /** @return array<int, array<string, mixed>> */
    public function recentCompletedTransactions(BusinessSubscription $subscription): array
    {
        $customerId = $subscription->provider_customer_id;
        abort_unless(filled($customerId), 409, 'A Paddle customer is required to recover checkout.');

        return $this->guardProviderOperation($subscription, 'checkout_recovery', fn () => $this->request()
            ->get('/transactions', [
                'customer_id' => $customerId,
                'status' => 'completed',
                'per_page' => '20',
            ])
            ->throw()
            ->json('data') ?? []);
    }

    /** @return array<string, mixed> */
    public function subscriptionByProviderId(BusinessSubscription $subscription, string $providerSubscriptionId): array
    {
        abort_unless((bool) preg_match('/^sub_[a-z0-9]+$/', $providerSubscriptionId), 422, 'Invalid provider subscription.');

        return $this->guardProviderOperation($subscription, 'checkout_subscription_confirmation', fn () => $this->request()
            ->get('/subscriptions/'.$providerSubscriptionId)
            ->throw()
            ->json('data'));
    }

    public function cancelAtPeriodEnd(BusinessSubscription $subscription): void
    {
        $this->guardProviderOperation($subscription, 'cancel_at_period_end', fn () => $this->request()
            ->post('/subscriptions/'.$this->providerId($subscription).'/cancel', ['effective_from' => 'next_billing_period'])
            ->throw());
    }

    public function cancelImmediately(BusinessSubscription $subscription): void
    {
        $this->guardProviderOperation($subscription, 'cancel_immediately', fn () => $this->request()
            ->post('/subscriptions/'.$this->providerId($subscription).'/cancel', ['effective_from' => 'immediately'])
            ->throw());
    }

    public function reactivate(BusinessSubscription $subscription): void
    {
        $this->guardProviderOperation($subscription, 'reactivate', fn () => $this->request()
            ->patch('/subscriptions/'.$this->providerId($subscription), ['scheduled_change' => null])
            ->throw());
    }

    public function billingPortalUrl(BusinessSubscription $subscription, string $returnUrl): string
    {
        $customerId = $subscription->provider_customer_id ?? throw new LogicException('Paddle customer is missing.');
        $session = $this->request()->post('/customers/'.$customerId.'/portal-sessions', [
            'subscription_ids' => [$this->providerId($subscription)],
            'return_url' => $returnUrl,
        ])->throw()->json('data');
        $url = data_get($session, 'urls.general.overview') ?? data_get($session, 'url');
        if (! is_string($url) || $url === '') {
            throw new LogicException('Paddle did not return a customer portal URL.');
        }

        return $url;
    }

    /** @return array<string, mixed> */
    private function subscription(BusinessSubscription $subscription): array
    {
        return $this->request()->get('/subscriptions/'.$this->providerId($subscription))->throw()->json('data');
    }

    private function providerId(BusinessSubscription $subscription): string
    {
        return $subscription->provider_subscription_id ?? throw new LogicException('Paddle subscription is missing.');
    }

    private function guardProviderOperation(BusinessSubscription $subscription, string $operation, callable $callback): mixed
    {
        try {
            return $callback();
        } catch (ConnectionException|RequestException $exception) {
            Log::warning('Paddle subscription operation failed.', [
                'business_id' => $subscription->business_id,
                'subscription_id' => $subscription->getKey(),
                'operation' => $operation,
                'provider_status' => $exception instanceof RequestException ? $exception->response?->status() : null,
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

    private function request(): PendingRequest
    {
        $apiKey = trim((string) config('billing.paddle.api_key'));
        abort_unless($apiKey !== '' && ! str_starts_with($apiKey, 'your-paddle-'), 503, 'Secure checkout is not configured yet. Please contact support.');

        return Http::baseUrl((string) config('billing.paddle.api_url'))
            ->acceptJson()
            ->asJson()
            ->withToken($apiKey)
            ->withHeaders(['Paddle-Version' => '1']);
    }
}
