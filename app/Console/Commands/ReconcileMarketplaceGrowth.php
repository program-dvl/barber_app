<?php

namespace App\Console\Commands;

use App\Domain\Marketplace\LocalGrowth\Services\PublicSupplyProjection;
use App\Domain\PlatformAccess\Models\Business;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReconcileMarketplaceGrowth extends Command
{
    protected $signature = 'marketplace:growth-reconcile {--business= : Business public ID} {--all : Rebuild every business, including unchanged supply}';

    protected $description = 'Refresh published business supply and automatic public profiles';

    public function handle(PublicSupplyProjection $projection): int
    {
        if (! config('marketplace-growth.enabled')) {
            $this->info('Marketplace local growth is disabled. No supply was changed.');

            return self::SUCCESS;
        }
        $query = Business::query()->when($this->option('business'), fn ($q) => $q->where('public_id', $this->option('business')));
        if (! $this->option('all') && ! $this->option('business')) {
            $query->where(fn ($q) => $q->whereNotIn('id', DB::table('marketplace_projection_work')->select('business_id'))
                ->orWhereIn('id', DB::table('marketplace_projection_work')->where(fn ($q) => $q->whereColumn('generation', '!=', 'projected_generation')->orWhere('updated_at', '<=', now()->subMinutes(config('marketplace-growth.projection_minutes'))))
                    ->where(fn ($q) => $q->whereNull('retry_at')->orWhere('retry_at', '<=', now()))->select('business_id')));
        }
        $count = 0;
        $failures = 0;
        foreach ($query->lazyById(100) as $business) {
            try {
                $projection->rebuild($business->id);
                $count++;
            } catch (\Throwable $error) {
                $failures++;
                report($error);
                DB::table('marketplace_projection_work')->where('business_id', $business->id)->update(['attempts' => DB::raw('attempts + 1'), 'retry_at' => now()->addMinutes(2), 'failure_reference' => 'MPG-PROJECTION-'.$business->id]);
            }
        }
        $this->info("Rebuilt {$count}; failed {$failures}. Search indexing settings are unchanged.");

        return $failures ? self::FAILURE : self::SUCCESS;
    }
}
