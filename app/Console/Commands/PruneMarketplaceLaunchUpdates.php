<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneMarketplaceLaunchUpdates extends Command
{
    protected $signature = 'marketplace:prune-launch-updates';

    protected $description = 'Remove expired unconfirmed requests and completed or year-old area update subscriptions';

    public function handle(): int
    {
        $count = DB::table('marketplace_launch_subscriptions')->where(function ($q) {
            $q->where(fn ($p) => $p->whereNull('subscribed_at')->where('consented_at', '<', now()->subDays(7)))
                ->orWhere('unsubscribed_at', '<', now()->subDays(30))
                ->orWhere('notified_at', '<', now()->subDays(30))
                ->orWhere('consented_at', '<', now()->subYear());
        })->delete();
        $this->info("Removed {$count} expired requests.");

        return self::SUCCESS;
    }
}
