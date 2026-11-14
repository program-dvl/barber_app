<?php

namespace App\Http\Controllers\Marketplace;

use App\Domain\Marketplace\LocalGrowth\Services\MarketplacePublicPage;
use App\Domain\Marketplace\LocalGrowth\Services\PublicOfferQuery;
use App\Domain\Marketplace\LocalGrowth\Services\SeoPageRegistry;
use App\Domain\Marketplace\LocalGrowth\Services\SupplyDiscovery;
use App\Domain\Marketplace\Services\DiscoveryCatalogue;
use App\Domain\Marketplace\Services\DiscoveryFilters;
use App\Domain\Marketplace\Services\MarketplaceListings;
use App\Domain\PlatformAccess\Models\Business;
use App\Http\Controllers\Controller;
use App\Support\BookingPageData;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DiscoveryController extends Controller
{
    public function nearby(Request $request)
    {
        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'], 'longitude' => ['required', 'numeric', 'between:-180,180'],
            'q' => ['nullable', 'string', 'max:100'], 'category' => ['nullable', Rule::in(['hair', 'barber', 'nails', ...array_keys(config('marketplace-taxonomy.audiences'))])],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'rating' => ['nullable', 'numeric', 'between:0,5'], 'sort' => ['nullable', 'in:name,rating,recommended'],
        ]);
        // Short-lived search centre only; never store a customer location/profile or URL coordinates.
        $request->session()->put('marketplace_nearby', [(float) $data['latitude'], (float) $data['longitude']]);
        $request->session()->put('marketplace_nearby_expires_at', now()->addMinutes(30)->timestamp);

        return redirect('/marketplace?'.http_build_query(['nearby' => 1, ...collect($data)->only(['q', 'category', 'date', 'rating', 'sort'])->filter()->all()]));
    }

    public function services()
    {
        return response()->json(['services' => config('marketplace-growth.enabled') ? app(SupplyDiscovery::class)->suggestions() : app(DiscoveryCatalogue::class)->suggestions()]);
    }

    public function clearNearby(Request $request)
    {
        $request->session()->forget(['marketplace_nearby', 'marketplace_nearby_expires_at']);

        return redirect('/marketplace');
    }

    public function __invoke(Request $request): Response
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'], 'location' => ['nullable', 'string', 'max:150'],
            'category' => ['nullable', Rule::in(['hair', 'barber', 'nails', ...array_keys(config('marketplace-taxonomy.audiences'))])],
            'page' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'rating' => ['nullable', 'numeric', 'between:0,5'], 'sort' => ['nullable', 'in:name,rating,recommended'],
            'nearby' => ['nullable', 'in:1'],
            'max_price' => ['nullable', 'numeric', 'between:0,1000000'],
            'currency' => ['required_with:max_price', 'nullable', 'in:USD,CAD,GBP,INR,AUD,EUR'],
            'date' => ['nullable', 'string', 'max:10', function ($attribute, $value, $fail) {
                if ($value !== 'today' && (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) || ! \DateTime::createFromFormat('!Y-m-d', $value) || \DateTime::createFromFormat('!Y-m-d', $value)->format('Y-m-d') !== $value)) {
                    $fail('Choose a valid appointment date.');
                }
            }],
        ]);
        $term = trim($data['q'] ?? '');
        $location = trim($data['location'] ?? '');
        if ($request->session()->get('marketplace_nearby_expires_at', 0) <= now()->timestamp) {
            $request->session()->forget(['marketplace_nearby', 'marketplace_nearby_expires_at']);
        }
        $point = $request->boolean('nearby') ? $request->session()->get('marketplace_nearby') : null;
        if (config('marketplace-growth.enabled')) {
            $result = app(SupplyDiscovery::class)->search($data, $point);
            $alternatives = $point && $result['results']->total() === 0 ? app(SupplyDiscovery::class)->search($data, $point, radius: 50)['results']->getCollection()->filter(fn ($c) => $c['distance'] > 25)->take(3)->values()->all() : [];
            $launchMode = ! app(PublicOfferQuery::class)->query()->exists();
            $registry = app(SeoPageRegistry::class);
            $entry = DB::table('marketplace_seo_pages')->where('path', '/marketplace')->where('status', 'approved')->first();

            return app(MarketplacePublicPage::class)->render('Marketplace/Discover', [
                ...$result, 'alternatives' => $alternatives,
                'launchMode' => $launchMode,
                'editorial' => config('marketplace-editorial'),
                'audiences' => collect(config('frontsite.solutions'))->map(fn ($v, $k) => ['name' => $v['label'], 'query' => $k, 'image' => $v['image'] ?? null])->values()->all(),
                'filters' => ['q' => $term, 'location' => $location, 'rating' => $data['rating'] ?? '', 'sort' => $data['sort'] ?? 'recommended', 'nearby' => (bool) $point, 'date' => $data['date'] ?? '', 'max_price' => $data['max_price'] ?? '', 'currency' => $data['currency'] ?? 'USD', 'category' => $data['category'] ?? ''],
            ], ['launch' => $launchMode, 'editorial' => $launchMode ? config('marketplace-editorial') : null, 'kind' => 'entry', 'title' => 'Find local appointments', 'description' => 'Explore real services, locations and starting prices from participating appointment businesses. Choose a business to see its current booking choices.', 'cards' => $result['results']->items(), 'alternatives' => $alternatives, 'links' => $registry->publicLinks(), 'pagination' => ['page' => $result['results']->currentPage(), 'previous' => $result['results']->previousPageUrl(), 'next' => $result['results']->nextPageUrl()]], $entry);
        }
        $literal = fn (string $value) => '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value).'%';
        $rating = DB::table('client_reviews')->selectRaw('AVG(rating)')->whereColumn('business_id', 'businesses.id')->where('public_consent', true);
        $query = app(MarketplaceListings::class)->query()
            ->when(! empty($data['category']), function ($q) use ($data) {
                app(DiscoveryCatalogue::class)->category($q, $data['category']);
            })
            ->when($term !== '', fn ($q) => $q->where(function ($q) use ($term, $literal) {
                $q->whereRaw("name LIKE ? ESCAPE '!'", [$literal($term)])->orWhereHas('services', fn ($q) => $q
                    ->where('is_active', true)->where('online_visible', true)
                    ->where(fn ($q) => $q->whereNull('effective_from')->orWhere('effective_from', '<=', now()))
                    ->where(fn ($q) => $q->whereNull('effective_until')->orWhere('effective_until', '>', now()))
                    ->whereRaw("name LIKE ? ESCAPE '!'", [$literal($term)]));
            }))
            ->when($location !== '', fn ($q) => $q->whereHas('locations', fn ($q) => $q->where('is_active', true)->where('status', 'active')->whereRaw("address LIKE ? ESCAPE '!'", [$literal($location)])))
            ->when(! empty($data['rating']), fn ($q) => $q->where($rating, '>=', $data['rating']))
            ->with(['locations' => fn ($q) => $q->where('is_active', true)->where('status', 'active')]);
        if ($point) {
            $longitudeScale = max(0.01, 111.32 * cos(deg2rad($point[0])));
            $distanceSql = '((entrance_latitude - ?) * (entrance_latitude - ?) * 12392.1424 + (entrance_longitude - ?) * (entrance_longitude - ?) * ?)';
            $bindings = [$point[0], $point[0], $point[1], $point[1], $longitudeScale ** 2];
            $query->whereHas('locations', fn ($q) => $q->where('is_active', true)->where('status', 'active')->whereNotNull('entrance_latitude')->whereNotNull('entrance_longitude')->whereRaw($distanceSql.' <= 625', $bindings));
        }
        if (($data['sort'] ?? 'name') === 'rating') {
            $query->select('businesses.*')->selectSub($rating, 'public_rating')->orderByDesc('public_rating');
        }
        $query->orderBy('name')->orderBy('id');
        $details = [];
        if (($data['max_price'] ?? null) !== null || ! empty($data['date'])) {
            $matches = [];
            // Stream candidates in bounded batches. Every match is checked against
            // the same public catalogue and availability service used at booking.
            foreach ((clone $query)->lazy(50) as $candidate) {
                $match = app(DiscoveryFilters::class)->match($candidate, $data, $point);
                if ($match !== null) {
                    $matches[] = $candidate->id;
                    $details[$candidate->id] = $match;
                }
            }
            $page = (int) ($data['page'] ?? 1);
            $items = (clone $query)->whereIn('id', array_slice($matches, ($page - 1) * 12, 12))->get();
            $businesses = new LengthAwarePaginator($items, count($matches), 12, $page, ['path' => $request->url(), 'query' => $request->query()]);
        } else {
            $businesses = $query->paginate(12)->withQueryString();
        }
        $businesses->through(function (Business $business) use ($point, $details, $location) {
            $public = app(BookingPageData::class)->business($business);
            $matchedLocations = $business->locations->filter(fn ($l) => $location === '' || str_contains(mb_strtolower((string) $l->address), mb_strtolower($location)));
            $chosen = $point ? $matchedLocations->filter(fn ($l) => $l->entrance_latitude !== null && $l->entrance_longitude !== null)->sortBy(fn ($l) => (($l->entrance_latitude - $point[0]) * 111.32) ** 2 + (($l->entrance_longitude - $point[1]) * 111.32 * cos(deg2rad($point[0]))) ** 2)->first() : $matchedLocations->first();
            $distances = $matchedLocations->filter(fn ($l) => $point && $l->entrance_latitude !== null && $l->entrance_longitude !== null)->map(fn ($l) => sqrt((($l->entrance_latitude - $point[0]) * 111.32) ** 2 + (($l->entrance_longitude - $point[1]) * 111.32 * cos(deg2rad($point[0]))) ** 2));

            return [
                'name' => $business->name, 'url' => '/marketplace/salons/'.$business->booking_slug.($chosen ? '?location='.$chosen->public_id : ''),
                'point' => $chosen && $chosen->entrance_latitude !== null && $chosen->entrance_longitude !== null ? ['lat' => (float) $chosen->entrance_latitude, 'lng' => (float) $chosen->entrance_longitude] : null,
                'image' => $public['gallery'][0]['url'] ?? $public['cover_url'],
                'address' => $chosen?->address,
                'next_time' => $details[$business->id]['next_time'] ?? null,
                'distance' => $distances->isNotEmpty() ? round($distances->min(), 1) : null,
                'reviews' => collect($public['reviews'])->only(['count', 'average'])->all(),
            ];
        });

        return Inertia::render('Marketplace/Discover', [
            'filters' => ['q' => $term, 'location' => $location, 'rating' => $data['rating'] ?? '', 'sort' => $data['sort'] ?? 'name', 'nearby' => (bool) $point, 'date' => $data['date'] ?? '', 'max_price' => $data['max_price'] ?? '', 'currency' => $data['currency'] ?? 'USD', 'category' => $data['category'] ?? ''],
            'results' => $businesses,
        ]);
    }
}
