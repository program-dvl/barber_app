<?php

namespace App\Console\Commands\Billing;

use App\Domain\Billing\Models\BillingPlan;
use App\Domain\Billing\Models\BillingPlanPrice;
use App\Domain\Billing\Models\BillingRateCard;
use App\Domain\Billing\Services\CapacityPricingCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Stripe\StripeClient;

class VerifyCapacityRates extends Command
{
    protected $signature = 'billing:verify-capacity-rates {market} {--apply : Read Stripe prices and install verified immutable local mappings}';

    protected $description = 'Preview or verify approved local capacity prices; never creates or changes Stripe objects.';

    public function handle(CapacityPricingCatalog $catalog): int
    {
        $market = strtoupper($this->argument('market'));
        foreach (['monthly', 'annual'] as $interval) {
            $terms = $catalog->terms($market, $interval);
            $this->line($market.' '.$interval.': '.$terms['currency'].' base '.$terms['base_minor'].'; location '.$terms['location_minor'].'; staff '.$terms['staff_minor'].' (minor units)');
        }
        if (! $this->option('apply')) {
            $this->info('Preview only. Commercial approval and explicit Stripe Price IDs are required before verification.');

            return self::SUCCESS;
        }
        if (! config('capacity-billing.markets.'.$market.'.approved') || ! filled(config('billing.stripe.secret'))) {
            $this->error('Approve the country rates and configure Stripe before installing mappings.');

            return self::FAILURE;
        }
        $stripe = new StripeClient(config('billing.stripe.secret'));
        $verified = [];
        try {
            foreach (['monthly', 'annual'] as $interval) {
                $terms = $catalog->terms($market, $interval);
                $ids = [];
                foreach (['base', 'location', 'staff'] as $role) {
                    $id = $terms[$role.'_price_id'];
                    if (! is_string($id) || ! str_starts_with($id, 'price_') || in_array($id, $ids, true)) {
                        throw new \LogicException('Use distinct, configured Stripe Price IDs for each component.');
                    }
                    $ids[] = $id;
                    $price = $stripe->prices->retrieve($id, []);
                    if (! $price->active || $price->type !== 'recurring' || $price->billing_scheme !== 'per_unit' || $price->tax_behavior !== 'exclusive'
                        || strtoupper($price->currency) !== $terms['currency'] || $price->unit_amount !== $terms[$role.'_minor']
                        || $price->recurring->interval !== ($interval === 'annual' ? 'year' : 'month')
                        || $price->recurring->interval_count !== 1 || $price->recurring->usage_type !== 'licensed') {
                        throw new \LogicException('Stripe price does not match the approved country, amount, interval and quantity model.');
                    }
                }
                foreach ($terms['sms_packs'] as $pack) {
                    $price = $stripe->prices->retrieve($pack['price_id'], []);
                    if (! $price->active || $price->type !== 'one_time' || $price->billing_scheme !== 'per_unit' || $price->tax_behavior !== 'exclusive'
                        || strtoupper($price->currency) !== $terms['currency'] || $price->unit_amount !== $pack['amount_minor'] || $pack['credits'] < 1) {
                        throw new \LogicException('SMS pack mapping does not match its approved terms.');
                    }
                }
                $verified[] = $terms;
            }
            DB::transaction(function () use ($verified, $catalog): void {
                $plan = BillingPlan::where('code', 'capacity')->firstOrFail();
                foreach ($verified as $terms) {
                    $existing = BillingPlanPrice::where('provider', 'stripe')->where('provider_price_id', $terms['base_price_id'])->first();
                    if ($existing && ($existing->billing_plan_id !== $plan->id || $existing->currency !== $terms['currency'] || $existing->amount_minor !== $terms['base_minor'])) {
                        throw new \LogicException('This base price is already mapped to different subscription terms.');
                    }
                    $oldCard = $existing ? BillingRateCard::where('billing_plan_price_id', $existing->id)->first() : null;
                    if ($oldCard && $oldCard->fingerprint !== $catalog->fingerprint($terms)) {
                        throw new \LogicException('Publish a new immutable Stripe base price for revised commercial terms.');
                    }
                    $price = $existing ?? BillingPlanPrice::create([
                        'billing_plan_id' => $plan->id, 'provider' => 'stripe', 'provider_price_id' => $terms['base_price_id'],
                        'currency' => $terms['currency'], 'billing_interval' => $terms['billing_interval'], 'amount_minor' => $terms['base_minor'],
                        'catalog_managed' => true, 'is_active' => true, 'effective_from' => now(),
                    ]);
                    BillingRateCard::firstOrCreate(['fingerprint' => $catalog->fingerprint($terms)], [
                        'market' => $terms['market'], 'currency' => $terms['currency'], 'revision' => $terms['revision'],
                        'billing_interval' => $terms['billing_interval'], 'billing_plan_price_id' => $price->id,
                        'terms' => $terms, 'verified_at' => now(),
                    ]);
                }
            });
        } catch (\Throwable $error) {
            $this->error('Verification failed. No rate cards were installed. Check the configured price mappings and provider access.');

            return self::FAILURE;
        }
        $this->info('Verified local mappings installed. Existing subscriptions and remote Stripe objects were preserved.');

        return self::SUCCESS;
    }
}
