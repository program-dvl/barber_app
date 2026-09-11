<?php

namespace App\Console\Commands\Stripe;

use Illuminate\Console\Command;

class CreateStripeProductsAndPrices extends Command
{
    protected $signature = 'stripe:create-products-and-prices
                            {--force : Permit remote catalog changes with a live-mode Stripe key}';

    protected $description = 'Provision the Business-owned ClipperDesk Stripe catalog from config/billing.php.';

    public function handle(): int
    {
        $this->components->info('Using the ClipperDesk Business billing catalog instead of the legacy LaraFast JSON/Cashier catalog.');

        return $this->call('billing:sync-stripe-catalog', [
            '--provision' => true,
            '--force' => (bool) $this->option('force'),
        ]);
    }
}
