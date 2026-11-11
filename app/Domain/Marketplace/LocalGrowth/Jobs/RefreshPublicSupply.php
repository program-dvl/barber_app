<?php

namespace App\Domain\Marketplace\LocalGrowth\Jobs;

use App\Domain\Marketplace\LocalGrowth\Services\PublicSupplyProjection;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

class RefreshPublicSupply implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public array $backoff = [30, 120];

    // Only an explicit business ID crosses the queue; never a CRM record or stale source snapshot.
    public function __construct(public int $businessId)
    {
        $this->onQueue('marketplace');
    }

    public function handle(PublicSupplyProjection $projection): void
    {
        if (! config('marketplace-growth.enabled')) {
            return;
        }
        $work = DB::table('marketplace_projection_work')->where('business_id', $this->businessId)->first();
        if ($work && $work->generation === $work->projected_generation && CarbonImmutable::parse($work->updated_at)->gt(now()->subMinutes(config('marketplace-growth.projection_minutes')))) {
            return;
        }
        try {
            $projection->rebuild($this->businessId);
        } catch (Throwable $error) {
            DB::table('marketplace_projection_work')->where('business_id', $this->businessId)->update([
                'attempts' => DB::raw('attempts + 1'), 'retry_at' => now()->addMinutes(2), 'failure_reference' => 'MPG-PROJECTION-'.$this->businessId,
            ]);
            if (config('queue.default') === 'sync') {
                report($error);

                return;
            }
            throw $error;
        }
    }
}
