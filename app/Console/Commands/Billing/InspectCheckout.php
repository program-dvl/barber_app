<?php

namespace App\Console\Commands\Billing;

use App\Domain\Billing\Models\BillingCapacityChange;
use App\Domain\Billing\Services\CapacityCheckoutRecovery;
use App\Domain\PlatformAccess\Models\Business;
use Dotenv\Dotenv;
use Illuminate\Console\Command;
use Throwable;

class InspectCheckout extends Command
{
    protected $signature = 'billing:inspect-checkout {business : Business public ID}';

    protected $description = 'Inspect checkout mode and cached-configuration mismatch without revealing credentials or changing billing.';

    public function handle(CapacityCheckoutRecovery $recovery): int
    {
        $business = Business::where('public_id', $this->argument('business'))->first();
        if (! $business) {
            $this->components->error('Business not found.');

            return self::FAILURE;
        }
        $change = BillingCapacityChange::where('business_id', $business->id)->where('kind', 'checkout')->where('status', 'submitted')->latest('id')->first();
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
        $this->line(json_encode([
            'business' => $business->public_id, 'reference' => $change?->public_id,
            'request_status' => $change?->status, 'attempt_status' => $attempt?->status,
            'configured_mode' => $mode, 'checkout_mode' => $storedMode,
            'configuration_cached' => $this->laravel->configurationIsCached(),
            'environment_file_mode' => $fileMode, 'configured_secret_matches_environment_file' => $matchesFile,
            'next_step' => $matchesFile === false ? 'Verify the intended deployment secret source, rebuild configuration and restart persistent workers.'
                : ($storedMode === 'live' && $mode !== 'live' ? 'Restore the original live payment account before checking or closing this checkout.'
                    : 'Check billing status in the workspace; unpaid stale test setup can recover without resetting paid records.'),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }

    private function mode(string $secret): string
    {
        return preg_match('/^(?:sk|rk)_(live|test)_/', $secret, $match) ? $match[1] : 'unknown';
    }
}
