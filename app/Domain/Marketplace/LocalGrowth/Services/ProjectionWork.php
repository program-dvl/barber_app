<?php

namespace App\Domain\Marketplace\LocalGrowth\Services;

use App\Domain\Marketplace\LocalGrowth\Jobs\RefreshPublicSupply;
use App\Domain\PlatformAccess\Models\Business;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProjectionWork
{
    public function changed(Model $model): void
    {
        if (! config('marketplace-growth.enabled') || ! Schema::hasTable('marketplace_projection_work')) {
            return;
        }
        $businessId = $model instanceof Business ? $model->getKey() : $model->business_id;
        if ($businessId) {
            $this->mark((int) $businessId);
            if ($model instanceof Business && $model->wasChanged(['configuration_published_at', 'online_booking_enabled', 'marketplace_listed'])) {
                DB::afterCommit(function () use ($businessId) {
                    try {
                        (new RefreshPublicSupply((int) $businessId))->handle(app(PublicSupplyProjection::class));
                    } catch (\Throwable $error) {
                        report($error);
                    }
                });
            }
        }
    }

    public function mark(int $businessId): void
    {
        if (! config('marketplace-growth.enabled') || ! Schema::hasTable('marketplace_projection_work')) {
            return;
        }
        DB::table('marketplace_projection_work')->insertOrIgnore(['business_id' => $businessId, 'generation' => 0, 'projected_generation' => 0, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('marketplace_projection_work')->where('business_id', $businessId)->update(['generation' => DB::raw('generation + 1'), 'retry_at' => null, 'updated_at' => now()]);
        RefreshPublicSupply::dispatch($businessId)->afterCommit();
    }
}
