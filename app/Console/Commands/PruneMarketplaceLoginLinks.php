<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneMarketplaceLoginLinks extends Command
{
    protected $signature = 'marketplace:prune-login-links';

    protected $description = 'Remove expired Marketplace sign-in links after one day';

    public function handle(): int
    {
        $count = DB::table('marketplace_login_links')->where('expires_at', '<', now()->subDay())->delete();
        $this->info('Removed '.$count.' expired sign-in links.');

        return self::SUCCESS;
    }
}
