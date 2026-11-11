<?php

namespace App\Console\Commands;

use App\Domain\Marketplace\LocalGrowth\Services\BookingAttribution;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PruneMarketplaceAttribution extends Command
{
    protected $signature = 'marketplace:growth-prune';

    protected $description = 'Remove expired optional acquisition evidence; preserve booking and financial history';

    public function handle(BookingAttribution $attribution): int
    {
        if (! Schema::hasTable('marketplace_booking_attributions')) {
            return self::SUCCESS;
        }
        $count = DB::table('marketplace_booking_attributions')->whereNotNull('expires_at')->where('expires_at', '<=', now())->update($attribution->unknown());
        $this->info("Anonymized {$count} expired attribution records. Operational entry and booking history retained.");

        return self::SUCCESS;
    }
}
