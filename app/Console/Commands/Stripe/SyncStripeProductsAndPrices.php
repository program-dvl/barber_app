<?php

namespace App\Console\Commands\Stripe;

use Illuminate\Console\Command;

class SyncStripeProductsAndPrices extends Command
{
    protected $signature = 'stripe:sync-products-and-prices
                            {--force : Permit remote catalog changes with a live-mode Stripe key}';

    protected $description = 'Converge Stripe and the local Business billing catalog on config/billing.php.';

    public function handle(): int
    {
        $this->components->info('Synchronizing through the ClipperDesk Business-owned catalog.');

        return $this->call('billing:sync-stripe-catalog', [
            '--provision' => true,
            '--force' => (bool) $this->option('force'),
        ]);
    }
}
