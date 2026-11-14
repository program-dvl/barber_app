<?php

namespace App\Http\Controllers\Marketplace;

use App\Domain\Marketplace\LocalGrowth\Services\PublicOfferQuery;
use App\Http\Controllers\Controller;
use Inertia\Inertia;

class EditorialController extends Controller
{
    public function index()
    {
        return Inertia::render('Marketplace/Editorial', ['guides' => config('marketplace-editorial.guides'), 'categories' => config('marketplace-editorial.categories')]);
    }

    public function guide(string $slug)
    {
        $guide = collect(config('marketplace-editorial.guides'))->firstWhere('slug', $slug);
        abort_unless($guide, 404);

        return Inertia::render('Marketplace/Editorial', ['guide' => $guide, 'category' => collect(config('marketplace-editorial.categories'))->firstWhere('slug', $guide['category'])]);
    }

    public function category(string $slug)
    {
        $category = collect(config('marketplace-editorial.categories'))->firstWhere('slug', $slug);
        abort_unless($category, 404);

        return Inertia::render('Marketplace/Editorial', ['category' => $category, 'audiences' => collect(config('frontsite.solutions'))->only($category['audiences'])->map(fn ($v, $k) => ['name' => $v['label'], 'query' => $k, 'available' => config('marketplace-growth.enabled') && app(PublicOfferQuery::class)->term(app(PublicOfferQuery::class)->query(), 'audience', $k)->exists()])->values()]);
    }
}
