<?php

namespace App\Http\Controllers\Marketplace;

use App\Domain\Marketplace\LocalGrowth\Services\GrowthSitemap;
use App\Http\Controllers\Controller;

class GrowthSitemapController extends Controller
{
    public function index(GrowthSitemap $sitemap)
    {
        abort_unless(config('marketplace-growth.enabled'), 404);

        return response($sitemap->index())->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function shard(string $kind, string $shard, GrowthSitemap $sitemap)
    {
        abort_unless(config('marketplace-growth.enabled') && (int) $shard <= 100000, 404);

        return response($sitemap->shard($kind, (int) $shard))->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
