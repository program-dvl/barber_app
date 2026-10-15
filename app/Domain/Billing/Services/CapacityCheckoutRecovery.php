<?php

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Contracts\SubscriptionProvider;
use App\Domain\Billing\Models\BillingCapacityChange;
use App\Domain\Billing\Models\BillingCheckoutAttempt;
use App\Domain\Billing\Models\BillingRateCard;
use App\Domain\Billing\Models\BusinessSubscription;
use App\Domain\PlatformAccess\Models\Business;
use App\Models\User;
use App\Support\Audit\AuditWriter;
use Illuminate\Support\Facades\DB;
use Throwable;

class CapacityCheckoutRecovery
{
    public function __construct(private readonly SubscriptionProvider $provider, private readonly StripeCheckoutReconciler $reconciler, private readonly AuditWriter $audit) {}

    /** Persist the operation before any provider call, including recovery of a claim/attempt gap. */
    private function prepare(BillingCapacityChange $change): BillingCheckoutAttempt
    {
        return DB::transaction(function () use ($change) {
            Business::whereKey($change->business_id)->lockForUpdate()->firstOrFail();
            BusinessSubscription::whereKey($change->business_subscription_id)->lockForUpdate()->firstOrFail();
            $locked = BillingCapacityChange::lockForUpdate()->findOrFail($change->id);
            abort_unless($locked->kind === 'checkout' && $locked->status === 'submitted', 409, 'This checkout is no longer pending. Refresh billing to see your current subscription.');
            $attempt = $this->attempt($locked);
            if ($attempt) {
                return $attempt;
            }
            $card = BillingRateCard::findOrFail($locked->quote['rate_card_id']);

            return BillingCheckoutAttempt::create([
                'business_id' => $locked->business_id, 'business_subscription_id' => $locked->business_subscription_id,
                'created_by_user_id' => $locked->actor_user_id, 'billing_plan_price_id' => $card->billing_plan_price_id,
                'provider' => 'stripe', 'provider_transaction_id' => 'pending_capacity_'.$locked->public_id,
                'status' => 'pending', 'expires_at' => now()->addHours(2),
                'capacity_quote' => [...$locked->quote, 'capacity_change_id' => $locked->public_id,
                    'stripe_mode' => $this->mode(), 'stripe_credential_fingerprint' => $this->credentialFingerprint()],
            ]);
        }, 3);
    }

    public function synchronize(BillingCapacityChange $change, bool $restart = false, ?User $actor = null): BillingCheckoutAttempt
    {
        $attempt = $this->prepare($change);

        // Serialize confirmation, recovery and restart for this business. A timeout
        // retains the committed attempt and its original Stripe idempotency key.
        $result = DB::transaction(function () use ($change, $attempt, $restart, $actor) {
            $business = Business::whereKey($change->business_id)->lockForUpdate()->firstOrFail();
            $subscription = BusinessSubscription::whereKey($change->business_subscription_id)->lockForUpdate()->firstOrFail();
            $locked = BillingCapacityChange::lockForUpdate()->findOrFail($change->id);
            $attempt = BillingCheckoutAttempt::lockForUpdate()->findOrFail($attempt->id);
            if ($locked->status !== 'submitted') {
                return $attempt;
            }
            if ($this->retireTestCheckout($attempt, $locked, $business, $subscription, $restart, $actor)) {
                return $attempt->fresh();
            }
            if (in_array($attempt->status, ['failed', 'expired', 'superseded'], true)) {
                $this->close($locked, $business, $restart, $actor);

                return $attempt;
            }
            if (str_starts_with($attempt->provider_transaction_id, 'pending_capacity_')) {
                if ($restart || ! $attempt->expires_at?->isFuture()
                    || (($attempt->capacity_quote['stripe_credential_fingerprint'] ?? $this->credentialFingerprint()) !== $this->credentialFingerprint())) {
                    // Read the original account before abandoning an uncertain create.
                    // A missing object in another account/mode is never proof of failure.
                    $attempt = $this->reconciler->recoverPreparation($attempt);
                    if ($attempt->status === 'expired') {
                        $this->close($locked, $business, $restart, $actor);

                        return $attempt;
                    }
                }
                if (str_starts_with($attempt->provider_transaction_id, 'pending_capacity_')) {
                    abort_unless(($attempt->capacity_quote['stripe_mode'] ?? $this->mode()) === $this->mode(), 409,
                        'Your previous checkout belongs to a different payment environment. Restore its payment configuration and check again. No new payment was started.');
                    try {
                        $checkout = $this->provider->createCheckout($business, $attempt->price, $attempt,
                            route('business.billing.show', $business).'?checkout=success&attempt='.$attempt->public_id.'&session_id={CHECKOUT_SESSION_ID}',
                            route('business.billing.show', [$business, 'section' => 'plan', 'checkout' => 'canceled']));
                        $attempt->update(['provider_transaction_id' => $checkout['provider_session_id'], 'provider_checkout_url' => $checkout['url']]);
                    } catch (Throwable $error) {
                        // Commit a customer binding obtained before a failed Checkout
                        // response, so recovery can find the original operation.
                        return $error;
                    }
                    if (! $restart) {
                        return $attempt->fresh();
                    }
                }
            }
            $attempt = $this->reconciler->reconcile($attempt, expireOpen: $restart);
            if (in_array($attempt->status, ['failed', 'expired', 'superseded'], true)) {
                $this->close($locked->fresh(), $business, $restart, $actor);
            }

            return $attempt;
        }, 3);
        if ($result instanceof Throwable) {
            throw $result;
        }

        return $result;
    }

    /** A test-only checkout cannot charge money; it must not block a first live purchase. */
    private function retireTestCheckout(BillingCheckoutAttempt $attempt, BillingCapacityChange $change, Business $business, BusinessSubscription $subscription, bool $restart, ?User $actor): bool
    {
        if ($this->mode() !== 'live' || ! str_starts_with($attempt->provider_transaction_id, 'cs_test_')
            || ! in_array($attempt->status, ['pending', 'processing'], true)) {
            return false;
        }
        // Only an unconverted trial with no commercial history can detach this
        // test customer. Unknown/live attempts and existing paid records stay put.
        if ($subscription->status->value !== 'trialing' || $subscription->provider_subscription_id
            || $subscription->capacity_snapshot || $subscription->billing_rate_card_id
            || $subscription->scheduled_billing_plan_price_id || $subscription->payment_method_last_four
            || $subscription->customer_balance_minor || $subscription->invoices()->exists()
            || $attempt->provider_subscription_id || $attempt->confirmed_at
            || $subscription->checkoutAttempts()->whereKeyNot($attempt->id)->whereIn('status', ['pending', 'processing', 'confirmed'])->exists()) {
            return false;
        }
        $customer = $subscription->provider_customer_id;
        $attempt->update(['status' => 'superseded', 'provider_checkout_url' => null, 'expires_at' => now(),
            'last_checked_at' => now(), 'last_error' => 'test_checkout_retired',
            'capacity_quote' => [...$attempt->capacity_quote, 'retired_test_customer_id' => $customer]]);
        $subscription->update(['provider_customer_id' => null, 'account_checked_at' => null, 'billing_checked_at' => null,
            'version' => $subscription->version + 1]);
        $change->update(['status' => $restart ? 'cancelled' : 'expired', 'last_checked_at' => now()]);
        $this->audit->write('subscription.checkout.test_retired', $business, $actor, $change,
            'Retired a test-only checkout before the first live purchase; no live payment or paid record was changed.',
            after: ['status' => $change->status, 'test_attempt_id' => $attempt->public_id], source: 'application');

        return true;
    }

    public function attempt(BillingCapacityChange $change): ?BillingCheckoutAttempt
    {
        return BillingCheckoutAttempt::where('business_subscription_id', $change->business_subscription_id)
            ->where('provider', 'stripe')->where(function ($query) use ($change) {
                $query->where('capacity_quote->capacity_change_id', $change->public_id)
                    ->orWhere('provider_transaction_id', 'pending_capacity_'.$change->public_id)
                    ->orWhere(function ($legacy) use ($change) {
                        $legacy->whereNull('capacity_quote->capacity_change_id')
                            ->where('capacity_quote->fingerprint', $change->quote['fingerprint'])
                            ->where('capacity_quote->locations', $change->quote['locations'])->where('capacity_quote->staff', $change->quote['staff'])
                            ->where('created_at', '>=', $change->submitted_at ?? $change->created_at);
                    });
            })->latest('id')->first();
    }

    private function close(BillingCapacityChange $change, Business $business, bool $restart, ?User $actor): void
    {
        if ($change->status !== 'submitted' && ! ($restart && $change->status === 'expired')) {
            return;
        }
        $change->update(['status' => $restart ? 'cancelled' : 'expired', 'last_checked_at' => now()]);
        $this->audit->write('subscription.checkout.closed', $business, $actor, $change,
            $restart ? 'Owner restarted an abandoned checkout after provider verification.' : 'Verified checkout is no longer payable.',
            after: ['status' => $change->status], source: 'provider');
    }

    private function mode(): string
    {
        return str_contains((string) config('billing.stripe.secret'), '_live_') ? 'live' : 'test';
    }

    private function credentialFingerprint(): string
    {
        return hash('sha256', trim((string) config('billing.stripe.secret')));
    }
}
