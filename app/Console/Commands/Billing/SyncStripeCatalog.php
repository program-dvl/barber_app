<?php

namespace App\Console\Commands\Billing;

use App\Domain\Billing\Enums\BillingInterval;
use App\Domain\Billing\Models\BillingPlan;
use App\Domain\Billing\Models\BillingPlanEntitlement;
use App\Domain\Billing\Models\BillingPlanPrice;
use App\Domain\Billing\Models\EntitlementDefinition;
use App\Domain\Billing\Services\PlanCatalog;
use App\Domain\Billing\Services\StripeCatalogProvisioner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use LogicException;
use Stripe\Exception\ApiConnectionException;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\AuthenticationException;
use Stripe\Exception\InvalidRequestException;
use Stripe\Exception\PermissionException;
use Stripe\Exception\RateLimitException;
use Stripe\Price;
use Stripe\StripeClient;

class SyncStripeCatalog extends Command
{
    protected $signature = 'billing:sync-stripe-catalog
                            {--apply : Verify Stripe and write effective-dated local mappings}
                            {--provision : Create or safely update the managed Stripe Products and Prices, then synchronize locally}
                            {--dry-run : Show the configured catalog without contacting Stripe or writing data}
                            {--force : Permit remote catalog changes when STRIPE_SECRET is a live-mode key}';

    protected $description = 'Verify and synchronize the approved ClipperDesk Stripe subscription catalog.';

    public function handle(PlanCatalog $catalog, StripeCatalogProvisioner $provisioner): int
    {
        $selectedModes = collect(['apply', 'provision', 'dry-run'])->filter(fn (string $option): bool => (bool) $this->option($option));
        if ($selectedModes->count() > 1) {
            $this->components->error('Choose only one of --apply, --provision, or --dry-run.');

            return self::INVALID;
        }

        if ($this->option('provision')) {
            return $this->provision($catalog, $provisioner);
        }

        $apply = (bool) $this->option('apply');
        if (! $apply) {
            $this->components->warn('Dry run only. Stripe is not contacted and no local billing data is changed.');
        }

        $stripe = $apply ? $this->stripe() : null;
        if ($apply && ! $stripe) {
            return self::FAILURE;
        }

        foreach ($catalog->plans() as $code => $definition) {
            $this->line('<fg=cyan>'.$definition['name'].'</>');

            foreach ([BillingInterval::Monthly, BillingInterval::Annual] as $interval) {
                $configured = $catalog->price((string) $code, $interval);
                $priceId = $configured['price_id'];
                $environmentName = $this->priceEnvironmentName((string) $code, $interval);
                $managedPrice = ! $apply && ! $priceId
                    ? $this->managedLocalPrice((string) $code, $interval, $configured['amount_minor'])
                    : null;
                $displayPrice = $priceId
                    ?? ($managedPrice ? $managedPrice->provider_price_id.' (managed local mapping)' : "missing {$environmentName}");
                $this->line(sprintf(
                    '- %s: %s at %s',
                    $interval->value,
                    $displayPrice,
                    $this->money($configured['amount_minor']),
                ));

                if (! $apply) {
                    continue;
                }

                if (! $priceId || ! str_starts_with($priceId, 'price_')) {
                    $this->components->error("A valid Stripe Price ID is required in {$environmentName}.");

                    return self::FAILURE;
                }

                try {
                    $providerPrice = $stripe->prices->retrieve($priceId, []);
                } catch (ApiErrorException) {
                    $this->components->error("Stripe could not retrieve the configured {$code} {$interval->value} price.");

                    return self::FAILURE;
                }

                if (! $this->validProviderPrice($providerPrice, $interval, $configured['amount_minor'])) {
                    $this->components->error("The configured {$code} {$interval->value} Stripe price does not match the approved active recurring amount, currency, or interval.");

                    return self::FAILURE;
                }
            }
        }

        if ($apply) {
            $prices = [];
            foreach ($catalog->plans() as $code => $definition) {
                foreach ([BillingInterval::Monthly, BillingInterval::Annual] as $interval) {
                    $prices[(string) $code][$interval->value] = $catalog->price((string) $code, $interval)['price_id'];
                }
            }
            $this->synchronizeLocal($catalog, $prices);
        }

        $this->components->info($apply
            ? 'Stripe catalog verified and local effective-dated mappings synchronized.'
            : 'Dry run completed. Use --provision to create/synchronize the managed catalog, or configure every Price ID before using --apply.');

        return self::SUCCESS;
    }

    private function provision(PlanCatalog $catalog, StripeCatalogProvisioner $provisioner): int
    {
        if (! Schema::hasColumn('billing_plan_prices', 'catalog_managed')) {
            $this->components->error('Run the pending billing migration before provisioning the Stripe catalog.');

            return self::FAILURE;
        }

        $secret = trim((string) config('billing.stripe.secret'));
        if ($secret === '') {
            $this->components->error('STRIPE_SECRET must be configured before provisioning the catalog.');

            return self::FAILURE;
        }
        if (! $this->validServerSecret($secret)) {
            return self::FAILURE;
        }
        if ((str_starts_with($secret, 'sk_live_') || str_starts_with($secret, 'rk_live_')) && ! $this->option('force')) {
            $this->components->error('Refusing to change a live Stripe catalog without --force. Run a dry run and confirm the catalog first.');

            return self::FAILURE;
        }

        $this->components->warn('Provisioning will create or update ClipperDesk-managed Stripe Products and Prices. Replaced prices are archived, never mutated or deleted.');
        try {
            $result = $provisioner->provision($catalog);
        } catch (ApiErrorException|LogicException $exception) {
            report($exception);
            $this->components->error($this->stripeFailureMessage($exception));
            $this->line('No local catalog changes were written; rerun safely after correcting the issue.');

            return self::FAILURE;
        }

        foreach ($result['messages'] as $message) {
            $this->line('- '.$message);
        }
        $this->synchronizeLocal($catalog, $result['prices']);
        try {
            foreach ($provisioner->archiveReplaced($result['replaced_price_ids']) as $message) {
                $this->line('- '.$message);
            }
        } catch (ApiErrorException|LogicException $exception) {
            report($exception);
            $this->components->warn('The new catalog is active locally, but one or more replaced Stripe prices could not be archived. Rerun this command safely to finish cleanup.');

            return self::FAILURE;
        }
        $this->components->info('Stripe Products and immutable Prices were provisioned and the local effective-dated catalog was synchronized.');

        return self::SUCCESS;
    }

    /** @param array<string, array<string, string>> $prices */
    private function synchronizeLocal(PlanCatalog $catalog, array $prices): void
    {
        DB::transaction(function () use ($catalog, $prices): void {
            foreach ($catalog->plans() as $code => $definition) {
                $code = (string) $code;
                $plan = $this->syncPlan($code, $definition);
                foreach ([BillingInterval::Monthly, BillingInterval::Annual] as $interval) {
                    $configured = $catalog->price($code, $interval);
                    $this->syncPrice($plan, $interval, $configured['amount_minor'], $prices[$code][$interval->value]);
                }
                $this->syncEntitlements($plan, $catalog->entitlements($code));
            }
        }, 3);
    }

    /** @param array<string, mixed> $definition */
    private function syncPlan(string $code, array $definition): BillingPlan
    {
        $plan = BillingPlan::query()->firstOrCreate(
            ['code' => $code],
            [
                'public_id' => (string) Str::ulid(),
                'name' => $definition['name'],
                'description' => $definition['description'],
                'is_active' => true,
                'is_trial_default' => false,
                'available_from' => now(),
                'available_until' => null,
            ],
        );
        $plan->update([
            'name' => $definition['name'],
            'description' => $definition['description'],
            'is_active' => true,
            'available_until' => null,
        ]);

        return $plan;
    }

    private function syncPrice(BillingPlan $plan, BillingInterval $interval, int $amountMinor, string $priceId): void
    {
        DB::transaction(function () use ($plan, $interval, $amountMinor, $priceId): void {
            $current = BillingPlanPrice::query()
                ->where('billing_plan_id', $plan->getKey())
                ->where('provider', 'stripe')
                ->where('billing_interval', $interval->value)
                ->where('is_active', true)
                ->whereNull('effective_until')
                ->latest('effective_from')
                ->first();

            if ($current?->provider_price_id === $priceId && $current->amount_minor === $amountMinor) {
                return;
            }

            $effectiveFrom = now();
            if ($current?->effective_from?->gte($effectiveFrom)) {
                $effectiveFrom = $current->effective_from->addSecond();
            }
            while (BillingPlanPrice::query()
                ->where('billing_plan_id', $plan->getKey())
                ->where('billing_interval', $interval->value)
                ->where('currency', strtoupper((string) config('billing.stripe.currency', 'USD')))
                ->where('effective_from', $effectiveFrom)
                ->exists()) {
                $effectiveFrom = $effectiveFrom->addSecond();
            }
            if ($current) {
                $current->update(['is_active' => false, 'effective_until' => $effectiveFrom]);
            }

            BillingPlanPrice::query()->updateOrCreate(
                ['provider_price_id' => $priceId],
                [
                    'billing_plan_id' => $plan->getKey(),
                    'billing_interval' => $interval,
                    'currency' => strtoupper((string) config('billing.stripe.currency', 'USD')),
                    'amount_minor' => $amountMinor,
                    'provider' => 'stripe',
                    'catalog_managed' => true,
                    'is_active' => true,
                    'effective_from' => $effectiveFrom,
                    'effective_until' => null,
                ],
            );
        });
    }

    /** @param array<string, bool|int> $values */
    private function syncEntitlements(BillingPlan $plan, array $values): void
    {
        foreach ($values as $key => $value) {
            $definition = EntitlementDefinition::query()->where('key', $key)->firstOrFail();
            $current = BillingPlanEntitlement::query()
                ->where('billing_plan_id', $plan->getKey())
                ->where('entitlement_definition_id', $definition->getKey())
                ->whereNull('effective_until')
                ->latest('effective_from')
                ->first();
            if ($current?->value === $value) {
                continue;
            }

            $effectiveAt = now();
            $current?->update(['effective_until' => $effectiveAt]);
            BillingPlanEntitlement::query()->create([
                'billing_plan_id' => $plan->getKey(),
                'entitlement_definition_id' => $definition->getKey(),
                'value' => $value,
                'effective_from' => $effectiveAt,
                'change_reason' => 'Verified Stripe catalog synchronization.',
            ]);
        }
    }

    private function validProviderPrice(Price $price, BillingInterval $interval, int $amountMinor): bool
    {
        $expectedInterval = $interval === BillingInterval::Annual ? 'year' : 'month';

        return $price->active
            && $price->type === 'recurring'
            && $price->recurring?->interval === $expectedInterval
            && (int) ($price->recurring?->interval_count ?? 0) === 1
            && strtoupper((string) $price->currency) === strtoupper((string) config('billing.stripe.currency', 'USD'))
            && (int) $price->unit_amount === $amountMinor;
    }

    private function managedLocalPrice(string $planCode, BillingInterval $interval, int $amountMinor): ?BillingPlanPrice
    {
        if (! Schema::hasColumn('billing_plan_prices', 'catalog_managed')) {
            return null;
        }

        return BillingPlanPrice::query()
            ->whereHas('plan', fn ($query) => $query->where('code', $planCode))
            ->where('provider', 'stripe')
            ->where('billing_interval', $interval->value)
            ->where('currency', strtoupper((string) config('billing.stripe.currency', 'USD')))
            ->where('amount_minor', $amountMinor)
            ->where('catalog_managed', true)
            ->where('is_active', true)
            ->whereNull('effective_until')
            ->latest('effective_from')
            ->first();
    }

    private function stripe(): ?StripeClient
    {
        $secret = trim((string) config('billing.stripe.secret'));
        if ($secret === '') {
            $this->components->error('STRIPE_SECRET must be configured before applying the catalog.');

            return null;
        }
        if (! $this->validServerSecret($secret)) {
            return null;
        }

        return new StripeClient($secret);
    }

    private function validServerSecret(string $secret): bool
    {
        if (str_starts_with($secret, 'pk_')) {
            $this->components->error('STRIPE_SECRET contains a publishable key (pk_…). Put the publishable key in STRIPE_KEY and a server-side secret key (sk_… or rk_…) in STRIPE_SECRET.');

            return false;
        }

        if (! Str::startsWith($secret, ['sk_', 'rk_'])) {
            $this->components->error('STRIPE_SECRET is not a recognized Stripe server-side key. Expected an sk_… secret key or a properly permissioned rk_… restricted key.');

            return false;
        }

        return true;
    }

    private function stripeFailureMessage(ApiErrorException|LogicException $exception): string
    {
        if ($exception instanceof LogicException) {
            return $exception->getMessage();
        }

        if ($exception instanceof PermissionException) {
            if ($exception->getStripeCode() === 'secret_key_required') {
                return 'Stripe rejected STRIPE_SECRET because it is a publishable key. Use an sk_… secret key, or an rk_… key with the required catalog permissions.';
            }

            return 'Stripe denied catalog access. If STRIPE_SECRET is a restricted rk_… key, grant read/write access to Products and Prices.';
        }

        if ($exception instanceof AuthenticationException) {
            return 'Stripe could not authenticate STRIPE_SECRET. Confirm that the server-side key is valid, active, and belongs to the intended Stripe account and mode.';
        }

        if ($exception instanceof ApiConnectionException) {
            return 'Stripe could not be reached. Check network connectivity and retry; the command is safe to rerun.';
        }

        if ($exception instanceof RateLimitException) {
            return 'Stripe rate-limited the catalog request. Wait briefly and rerun the command safely.';
        }

        if ($exception instanceof InvalidRequestException) {
            $code = $exception->getStripeCode();

            return 'Stripe rejected a catalog parameter'.($code ? " ({$code})" : '').'. Review the logged Stripe request ID and catalog configuration.';
        }

        return 'Stripe catalog provisioning failed. Review the logged Stripe request ID and API status, then rerun safely.';
    }

    private function money(int $minor): string
    {
        return strtoupper((string) config('billing.stripe.currency', 'USD')).' '.number_format($minor / 100, 2);
    }

    private function priceEnvironmentName(string $code, BillingInterval $interval): string
    {
        return 'STRIPE_'.strtoupper($code).'_'.strtoupper($interval->value).'_PRICE_ID';
    }
}
