<?php

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Models\BillingCapacityChange;
use App\Domain\Billing\Models\BillingRateCard;
use App\Domain\Billing\Models\BusinessSubscription;
use App\Domain\PlatformAccess\Models\Business;
use App\Models\User;
use App\Support\Audit\AuditWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CapacityBillingManager
{
    public function __construct(private readonly CapacityPricingCatalog $catalog, private readonly CapacityStripeGateway $stripe, private readonly EntitlementEvaluator $entitlements, private readonly AuditWriter $audit) {}

    public function review(Business $business, User $actor, string $market, string $interval, int $locations, int $staff): BillingCapacityChange
    {
        return DB::transaction(function () use ($business, $actor, $market, $interval, $locations, $staff): BillingCapacityChange {
            Business::whereKey($business->id)->lockForUpdate()->firstOrFail();
            $subscription = $business->subscription()->with('price')->lockForUpdate()->firstOrFail();
            $this->assertIdle($subscription);
            abort_unless(strtoupper($business->country_code) === strtoupper($market), 422, 'Choose the pricing country recorded in your business profile.');
            $retained = $subscription->billing_rate_card_id ? BillingRateCard::findOrFail($subscription->billing_rate_card_id) : null;
            abort_if($retained && $retained->market !== strtoupper($market), 422, 'Changing the billing country requires a separate migration.');
            if ($retained && $retained->billing_interval !== $interval) {
                $retained = null;
            }
            $quote = $this->catalog->quote($market, $interval, $locations, $staff, $retained);
            abort_unless($quote['ready'], 503, 'This country’s rates are awaiting approval and provider verification.');
            $this->assertFits($business, $quote);
            $hasProvider = filled($subscription->provider_subscription_id);
            $kind = $hasProvider ? 'immediate' : 'checkout';
            if ($hasProvider) {
                abort_unless($subscription->status->value === 'active' && $subscription->current_period_ends_at?->isFuture(), 409, 'Resolve billing or cancellation before changing capacity.');
                abort_unless($subscription->price && $subscription->price->currency === $quote['currency'], 422, 'Currency changes require a separate migration.');
                $current = $subscription->capacity_snapshot;
                if ($current && $current['locations'] === $locations && $current['staff'] === $staff && $subscription->billing_interval->value === $interval) {
                    throw ValidationException::withMessages(['staff' => 'You already have this capacity.']);
                }
                if ($subscription->billing_interval->value !== $interval || ($current && ($locations < $current['locations'] || $staff < $current['staff']))
                    || ($quote['total_minor'] < ($current['total_minor'] ?? $subscription->price->amount_minor))) {
                    $kind = 'renewal';
                }
                abort_if($subscription->invoices()->whereIn('status', ['open', 'uncollectible'])
                    ->whereRaw('COALESCE(amount_remaining_minor, amount_due_minor - amount_paid_minor) > 0')->exists(), 409, 'Pay outstanding invoices before changing the subscription.');
            }
            $at = now()->timestamp;
            $quote['proration_date'] = $at;
            $quote['due_today_minor'] = $kind === 'checkout' ? $quote['total_minor'] : 0;
            $quote['tax_minor'] = null;
            if ($hasProvider) {
                $quote = [...$quote, ...$this->stripe->preview($subscription, $quote, $kind, $at)];
            }

            return BillingCapacityChange::create([
                'business_id' => $business->id, 'business_subscription_id' => $subscription->id,
                'actor_user_id' => $actor->id, 'subscription_version' => $subscription->version,
                'kind' => $kind, 'quote' => $quote, 'status' => 'quoted',
                'expires_at' => now()->addMinutes(config('capacity-billing.quote_minutes')),
                'effective_at' => $kind === 'renewal' ? $subscription->current_period_ends_at : now(),
            ]);
        }, 3);
    }

    public function claim(Business $business, User $actor, BillingCapacityChange $change): array
    {
        return DB::transaction(function () use ($business, $actor, $change): array {
            Business::whereKey($business->id)->lockForUpdate()->firstOrFail();
            $subscription = $business->subscription()->lockForUpdate()->firstOrFail();
            $locked = BillingCapacityChange::where('business_id', $business->id)->lockForUpdate()->findOrFail($change->id);
            abort_unless($locked->actor_user_id === $actor->id, 403);
            if (in_array($locked->status, ['submitted', 'scheduled', 'applied'], true)) {
                return ['change' => $locked, 'dispatch' => false];
            }
            abort_unless($locked->status === 'quoted' && $locked->expires_at->isFuture(), 409, 'This review expired. Review the current total again.');
            abort_unless($locked->subscription_version === $subscription->version, 409, 'Your subscription changed. Review again.');
            $this->assertIdle($subscription);
            $card = BillingRateCard::findOrFail($locked->quote['rate_card_id']);
            abort_unless(config('capacity-billing.enabled') && config('capacity-billing.markets.'.$card->market.'.approved'), 503, 'New billing changes are temporarily unavailable.');
            abort_unless(strtoupper($business->fresh()->country_code) === $locked->quote['market'], 409, 'Your business country changed. Review billing again.');
            if (filled($subscription->provider_subscription_id)) {
                abort_unless($subscription->status->value === 'active' && $subscription->current_period_ends_at?->isFuture(), 409, 'Resolve billing before reviewing capacity again.');
                abort_if($subscription->invoices()->whereIn('status', ['open', 'uncollectible'])
                    ->whereRaw('COALESCE(amount_remaining_minor, amount_due_minor - amount_paid_minor) > 0')->exists(), 409, 'Pay outstanding invoices before changing the subscription.');
            }
            $this->assertFits($business, $locked->quote);
            $locked->update(['status' => 'submitted', 'submitted_at' => now()]);
            $this->audit->write('subscription.capacity.requested', $business, $actor, $locked,
                'Owner confirmed the displayed locations and bookable staff.',
                before: $subscription->capacity_snapshot ?? ['legacy_plan' => $subscription->billing_plan_id],
                after: $locked->quote);

            return ['change' => $locked->fresh(), 'dispatch' => true];
        }, 3);
    }

    public function assertFits(Business $business, array $quote): void
    {
        foreach (['locations' => 'locations.max', 'staff' => 'staff.max'] as $field => $key) {
            if ($this->entitlements->usage($business, $key) > $quote[$field]) {
                throw ValidationException::withMessages([$field => 'Deactivate surplus '.($field === 'staff' ? 'bookable staff' : 'locations').' before reducing purchased capacity. Existing records are retained.']);
            }
        }
    }

    private function assertIdle(BusinessSubscription $subscription): void
    {
        abort_if(BillingCapacityChange::where('business_subscription_id', $subscription->id)->whereIn('status', ['submitted', 'scheduled'])->exists()
            || $subscription->checkoutAttempts()->whereIn('status', ['pending', 'processing'])->where('expires_at', '>', now())->exists()
            || $subscription->scheduled_billing_plan_price_id, 409, 'A billing request is already pending. Refresh its status before making another change.');
    }
}
