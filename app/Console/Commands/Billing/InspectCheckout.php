<?php

namespace App\Console\Commands\Billing;

use App\Domain\Billing\Models\BillingCapacityChange;
use App\Domain\Billing\Models\BillingCheckoutAttempt;
use App\Domain\Billing\Services\CapacityCheckoutRecovery;
use App\Domain\Billing\Services\CapacityPricingCatalog;
use App\Domain\PlatformAccess\Models\Business;
use Dotenv\Dotenv;
use Illuminate\Console\Command;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;
use Throwable;

class InspectCheckout extends Command
{
    protected $signature = 'billing:inspect-checkout {business : Business public ID or checkout Support reference}
                            {--provider : Also read customer and current monthly/annual Stripe prices; never changes billing}';

    protected $description = 'Inspect checkout mode and cached-configuration mismatch without revealing credentials or changing billing.';

    public function handle(CapacityCheckoutRecovery $recovery): int
    {
        $input = strtoupper(trim((string) $this->argument('business')));
        $change = BillingCapacityChange::where('public_id', $input)->where('kind', 'checkout')->first();
        $business = $change ? Business::find($change->business_id) : Business::where('public_id', $input)->first();
        if (! $business) {
            $this->components->error('No business or checkout Support reference matched in this application database.');
            $this->line('Use the Support reference shown in billing, or the business ID from /businesses/<ID>/billing.');
            $this->line('If the value is correct, run this command from the deployed site’s current release and database.');

            return self::FAILURE;
        }
        $matchedBy = $change ? 'support_reference' : 'business';
        // A supplied support reference identifies that exact request, including
        // closed history. Do not silently substitute a newer pending checkout.
        $change ??= BillingCapacityChange::where('business_id', $business->id)->where('kind', 'checkout')->where('status', 'submitted')->latest('id')->first();
        $attempt = $change ? $recovery->attempt($change) : null;
        $secret = trim((string) config('billing.stripe.secret'));
        $mode = $this->mode($secret);
        $storedMode = $attempt && preg_match('/^cs_(live|test)_/', $attempt->provider_transaction_id, $match)
            ? $match[1] : ($attempt?->capacity_quote['stripe_mode'] ?? 'unknown');
        $fileMode = 'unknown';
        $matchesFile = null;
        // Read only the application's private environment file. Never output its
        // values, fingerprints, provider IDs, customer data or parser diagnostics.
        try {
            if (is_readable($path = $this->laravel->environmentFilePath())) {
                $values = Dotenv::parse(file_get_contents($path));
                if (isset($values['STRIPE_SECRET'])) {
                    $fileMode = $this->mode(trim($values['STRIPE_SECRET']));
                    $matchesFile = hash_equals($secret, trim($values['STRIPE_SECRET']));
                }
            }
        } catch (Throwable) {
            // Managed environment variables may be the sole configuration source.
        }
        $providerChecks = $this->option('provider') ? $this->providerChecks($business, $attempt, $secret) : null;
        $priceChecks = collect($providerChecks['current_prices'] ?? [])->flatMap(fn ($prices) => array_values($prices));
        $priceProblem = $priceChecks->contains(fn ($price) => (($price['code'] ?? null) === 'resource_missing' && ($price['http_status'] ?? null) === 404)
            || ($price['matches'] ?? true) === false || ($price['configured'] ?? true) === false);
        $priceReadFailed = $priceChecks->contains(fn ($price) => ($price['readable'] ?? null) === false
            && ! (($price['code'] ?? null) === 'resource_missing' && ($price['http_status'] ?? null) === 404));
        $nextStep = ! $change ? 'No submitted checkout was found for this business. Review the current billing workspace.'
            : (! in_array($change->status, ['submitted', 'scheduled'], true) ? 'This referenced checkout is no longer pending. Review the current billing workspace before taking further action.'
                : ($matchesFile === false ? 'Verify the intended deployment secret source, rebuild configuration and restart persistent workers.'
                    : ($storedMode === 'live' && $mode !== 'live' ? 'Restore the original live payment account before checking or closing this checkout.'
                        : 'Check billing status in the workspace; unpaid stale test setup can recover without resetting paid records.')));
        if ($priceReadFailed) {
            $nextStep = 'Stripe price checks could not complete. Verify the current credentials, permissions and connectivity before provisioning prices. This diagnostic did not start a checkout.';
        } elseif ($priceProblem) {
            $nextStep = 'Subscription prices are unavailable or do not match the current Stripe account. '
                .($mode === 'test' ? 'Run php artisan billing:verify-capacity-rates '.app(CapacityPricingCatalog::class)->marketFor($business).' --provision in the intended test account. '
                    : 'Verify the approved subscription prices in the intended live account before accepting payment. ')
                .'Explicit price overrides must be correct or cleared first. After setup succeeds, follow the recovery steps in billing; do not reset paid subscriptions.';
        }
        $this->line(json_encode([
            'business' => $business->public_id, 'reference' => $change?->public_id, 'matched_by' => $matchedBy,
            'request_status' => $change?->status, 'attempt_status' => $attempt?->status,
            'session_saved' => $attempt ? str_starts_with($attempt->provider_transaction_id, 'cs_') : false,
            'checkout_failure' => str_starts_with((string) $attempt?->last_error, 'stripe_checkout:')
                ? json_decode(substr($attempt->last_error, strlen('stripe_checkout:')), true) : null,
            'can_restart_test_preparation' => $change ? $recovery->canRestartTestPreparation($change) : false,
            'original_secret_matches_current' => isset($attempt?->capacity_quote['stripe_credential_fingerprint'])
                ? hash_equals($attempt->capacity_quote['stripe_credential_fingerprint'], hash('sha256', $secret)) : null,
            'configured_mode' => $mode, 'checkout_mode' => $storedMode,
            'configuration_cached' => $this->laravel->configurationIsCached(),
            'environment_file_mode' => $fileMode, 'configured_secret_matches_environment_file' => $matchesFile,
            ...($providerChecks !== null ? ['provider_checks' => $providerChecks, 'requires_catalog_setup' => $priceProblem] : []),
            'next_step' => $nextStep,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }

    private function providerChecks(Business $business, ?BillingCheckoutAttempt $attempt, string $secret): array
    {
        if ($this->mode($secret) === 'unknown') {
            return ['available' => false, 'reason' => 'Server credentials are not configured.'];
        }
        $stripe = new StripeClient($secret);
        // Output classifications only. No provider IDs, keys, messages, addresses
        // or payment details are printed, including on a provider exception.
        $read = static function (callable $operation): array {
            try {
                return $operation();
            } catch (ApiErrorException $error) {
                return ['readable' => false, 'code' => $error->getStripeCode(), 'http_status' => $error->getHttpStatus()];
            } catch (Throwable) {
                return ['readable' => false, 'reason' => 'Verification could not complete.'];
            }
        };
        $customer = $attempt?->subscription?->provider_customer_id;
        $result = ['customer' => $customer ? $read(static function () use ($stripe, $customer, $business): array {
            $object = $stripe->customers->retrieve($customer, []);

            return ['readable' => true, 'deleted' => (bool) ($object->deleted ?? false),
                'business_metadata_present' => isset($object->metadata->business_public_id),
                'business_matches' => ($object->metadata->business_public_id ?? null) === $business->public_id];
        }) : ['saved' => false]];
        $catalog = app(CapacityPricingCatalog::class);
        foreach (['monthly', 'annual'] as $interval) {
            $terms = $catalog->terms($catalog->marketFor($business), $interval);
            foreach (['base', 'location', 'staff'] as $role) {
                $id = $terms[$role.'_price_id'];
                $result['current_prices'][$interval][$role] = ['source' => filled(config('capacity-billing.markets.'.$terms['market'].'.'.$interval.'.'.$role.'_price_id')) ? 'configuration' : 'verified_mapping'] + (filled($id) ? $read(static function () use ($stripe, $catalog, $terms, $role, $id, $interval): array {
                    $price = $stripe->prices->retrieve($id, ['expand' => ['tiers']]);

                    return ['readable' => true, 'matches' => $price->active && strtoupper($price->currency) === $terms['currency']
                        && $price->recurring?->interval === ($interval === 'annual' ? 'year' : 'month')
                        && $price->recurring?->interval_count === 1 && $price->recurring?->usage_type === 'licensed'
                        && $price->tax_behavior === 'exclusive'
                        && $catalog->matchesStripePrice($price->toArray(), $terms, $role, requireTiers: true)];
                }) : ['configured' => false]);
            }
        }

        return $result;
    }

    private function mode(string $secret): string
    {
        return preg_match('/^(?:sk|rk)_(live|test)_/', $secret, $match) ? $match[1] : 'unknown';
    }
}
