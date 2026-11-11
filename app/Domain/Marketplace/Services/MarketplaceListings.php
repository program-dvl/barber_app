<?php

namespace App\Domain\Marketplace\Services;

use App\Domain\PlatformAccess\Models\Business;
use Illuminate\Database\Eloquent\Builder;

class MarketplaceListings
{
    public function query(): Builder
    {
        $query = Business::query()->where(fn ($q) => $q->where('marketplace_listed', true)
            ->orWhereNull('marketplace_consented_at'))
            ->where('status', 'active')->whereNotNull('configuration_published_at')->where('online_booking_enabled', true)
            ->whereNotNull('booking_slug')
            ->whereHas('locations', fn ($q) => $q->where('is_active', true)->where('status', 'active'));
        if (\Illuminate\Support\Facades\Schema::hasTable('marketplace_seo_pages')) {
            $query->whereNotExists(fn ($p) => $p->selectRaw('1')->from('marketplace_seo_pages')->whereColumn('marketplace_seo_pages.business_id', 'businesses.id')->where('kind', 'profile')->whereIn('status', ['paused', 'withdrawn']));
        }
        return $query;
    }

    public function resolve(string $slug): Business
    {
        return $this->query()->where('booking_slug', $slug)->firstOrFail();
    }
}
