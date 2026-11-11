<?php

namespace App\Http\Controllers\Marketplace;

use App\Domain\Marketplace\LocalGrowth\Services\MarketplacePublicPage;
use App\Domain\Marketplace\LocalGrowth\Services\SeoPageRegistry;
use App\Domain\Marketplace\LocalGrowth\Services\SupplyDiscovery;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LocalPageController extends Controller
{
    public function __invoke(Request $request, SeoPageRegistry $registry, SupplyDiscovery $discovery, MarketplacePublicPage $pages)
    {
        abort_unless(config('marketplace-growth.enabled'), 404);
        $page = DB::table('marketplace_seo_pages')->where('path', '/'.ltrim($request->path(), '/'))->where('status', 'approved')->where('kind', '!=', 'profile')->first();
        abort_unless($page, 404);
        $data = $request->validate(['page' => ['nullable', 'integer', 'min:1', 'max:17']]);
        $result = $discovery->search($data, geography: $page->geography_id, term: in_array($page->kind, ['audience', 'service'], true) ? [$page->kind === 'service' ? 'concept' : 'audience', $page->term] : null, country: $page->kind === 'country' ? explode('/', $page->path)[2] : null);
        if ((int) ($data['page'] ?? 1) > 1 && $result['results']->isEmpty()) {
            abort(404);
        }
        $geo = $page->geography_id ? DB::table('marketplace_geographies')->find($page->geography_id) : null;
        $breadcrumbs = $geo ? [['name' => strtoupper($geo->country), 'url' => '/marketplace/'.$geo->country], ['name' => $geo->name, 'url' => '/marketplace/'.$geo->country.'/'.$geo->region.'/'.$geo->city]] : [];
        // Breadcrumb links exist only when a real registry page is public.
        $breadcrumbs = array_values(array_filter($breadcrumbs, fn ($b) => DB::table('marketplace_seo_pages')->where('path', $b['url'])->where('status', 'approved')->exists()));
        $content = ['kind' => $page->kind, 'title' => $page->title, 'description' => $page->context, 'cards' => $result['results']->items(), 'offset' => ($result['results']->currentPage() - 1) * 12, 'links' => $registry->publicLinks($page->geography_id), 'breadcrumbs' => $breadcrumbs, 'locale' => 'en', 'pagination' => ['previous' => $result['results']->previousPageUrl(), 'next' => $result['results']->nextPageUrl(), 'page' => $result['results']->currentPage()]];

        return $pages->render('Marketplace/Local', [...$result, 'localPage' => $content], $content, $page);
    }
}
