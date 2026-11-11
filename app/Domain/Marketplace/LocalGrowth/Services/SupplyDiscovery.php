<?php

namespace App\Domain\Marketplace\LocalGrowth\Services;

use App\Domain\Marketplace\Services\DiscoveryFilters;
use App\Domain\PlatformAccess\Models\Business;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class SupplyDiscovery
{
    public function search(array $filters = [], ?array $point = null, ?int $geography = null, ?array $term = null, ?string $country = null, int $radius = 25): array
    {
        $public = app(PublicOfferQuery::class);
        $q = $public->query();
        if ($geography) {
            $public->geography($q, $geography);
        }
        if ($country) {
            $q->whereIn('o.geography_id', DB::table('marketplace_geographies')->where('country', $country)->select('id'));
        }
        if ($term) {
            $public->term($q, $term[0], $term[1]);
        }
        if (! empty($filters['category'])) {
            $public->term($q, 'audience', app(DiscoveryTaxonomy::class)->category($filters['category']));
        }
        $text = trim($filters['q'] ?? '');
        $concepts = $text ? app(DiscoveryTaxonomy::class)->concepts($text, 'other') : [];
        $literal = fn ($value) => '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value).'%';
        if ($text !== '') {
            $q->where(function ($w) use ($literal, $text, $concepts) {
                $w->whereRaw("o.business_name LIKE ? ESCAPE '!'", [$literal($text)])->orWhereRaw("o.service_name LIKE ? ESCAPE '!'", [$literal($text)]);
                if ($concepts) {
                    $w->orWhereExists(fn ($t) => $t->selectRaw('1')->from('marketplace_offer_terms')->whereColumn('offer_id', 'o.id')->where('kind', 'concept')->whereIn('term', $concepts));
                }
            });
        }
        if (! empty($filters['location'])) {
            $q->whereRaw("o.address LIKE ? ESCAPE '!'", [$literal($filters['location'])]);
        }
        if (! empty($filters['rating'])) {
            $q->where('o.review_average', '>=', $filters['rating']);
        }
        if (isset($filters['max_price']) && $filters['max_price'] !== '' && $filters['max_price'] !== null) {
            $q->where('o.currency', $filters['currency'])->where('o.price_minor', '<=', (int) round($filters['max_price'] * 100));
        }
        if ($point) {
            $lat = $radius / 111.32;
            $lng = $radius / max(.01, 111.32 * cos(deg2rad($point[0])));
            $q->whereBetween('o.latitude', [$point[0] - $lat, $point[0] + $lat])->whereBetween('o.longitude', [$point[1] - $lng, $point[1] + $lng]);
        }
        // Select a bounded business pool before hydrating offers. One large tenant
        // cannot consume the pool with hundreds of services. Ranking never scans CRM.
        $limit = (int) config('marketplace-growth.candidate_limit');
        $exploration = max(1, (int) ceil($limit / 10));
        $core = (clone $q)->select('o.business_id')->groupBy('o.business_id');
        if ($text !== '') {
            $core->orderByRaw('MIN(CASE WHEN LOWER(o.service_name) = ? THEN 0 ELSE 1 END)', [mb_strtolower($text)]);
        }
        if ($point) {
            $core->orderByRaw('MIN((o.latitude - ?) * (o.latitude - ?) + (o.longitude - ?) * (o.longitude - ?))', [$point[0], $point[0], $point[1], $point[1]]);
        }
        $pool = $core->orderBy('o.business_id')->limit($limit - $exploration)->pluck('o.business_id');
        $new = (clone $q)->where('o.first_listed_at', '>=', now()->subDays(30))->select('o.business_id')->groupBy('o.business_id')
            ->orderByRaw('MIN(o.first_listed_at) DESC')->orderByRaw('((o.business_id * 1103515245 + ?) % 2147483647)', [crc32(now('UTC')->toDateString())])->limit($exploration)->pluck('o.business_id');
        $pool = $pool->merge($new)->unique()->values();
        // Window bound prevents a large catalogue consuming other businesses' facts.
        $boundedOffers = (clone $q)->whereIn('o.business_id', $pool)->selectRaw('ROW_NUMBER() OVER (PARTITION BY o.business_id ORDER BY o.id) as offer_rank');
        $offers = $pool->isEmpty() ? collect() : DB::query()->fromSub($boundedOffers, 'bounded_offers')->where('offer_rank', '<=', 12)->get();
        $cards = $offers->groupBy('business_id')->map(function ($rows) use ($text, $point, $radius) {
            $rows = $rows->map(function ($row) use ($text, $point) {
                $row->facts = json_decode($row->facts, true, flags: JSON_THROW_ON_ERROR);
                $row->distance = $point && $row->latitude !== null && $row->longitude !== null
                    ? sqrt((($row->latitude - $point[0]) * 111.32) ** 2 + (($row->longitude - $point[1]) * 111.32 * cos(deg2rad($point[0]))) ** 2) : null;
                $row->match = $text !== '' && mb_strtolower($row->service_name) === mb_strtolower($text) ? 0 : 1;

                return $row;
            })->filter(fn ($r) => ! $point || ($r->distance !== null && $r->distance <= $radius));
            if ($rows->isEmpty()) {
                return null;
            }

            return $rows->sortBy(fn ($r) => [$r->match, $r->distance ?? 0, $r->id])->first();
        })->filter()->values();
        $sort = $filters['sort'] ?? 'recommended';
        $cards = $cards->sortBy(fn ($o) => match ($sort) {
            'name' => [mb_strtolower($o->business_name), $o->business_id],
            'rating' => [-($o->review_average ?? 0), -$o->review_count, $o->business_id],
            default => [$o->match, (int) (($o->distance ?? 0) / 5), -(($o->review_average ?? 3) * min(10, $o->review_count) + 15) / (min(10, $o->review_count) + 5), $o->business_id],
        })->values();
        // One in ten comparable positions may explore a recently listed provider.
        // It never crosses an intent or 5km distance band; no fake review/exposure data.
        if ($sort === 'recommended' && $cards->count() >= 10) {
            $anchor = $cards[9];
            $new = $cards->slice(9)->filter(fn ($o) => $o->match === $anchor->match && (int) (($o->distance ?? 0) / 5) === (int) (($anchor->distance ?? 0) / 5) && CarbonImmutable::parse($o->first_listed_at)->gt(now()->subDays(30)))
                ->sortBy(fn ($o) => hash('sha256', now('UTC')->toDateString().'|'.$o->business_id))->first();
            if ($new) {
                $cards = $cards->reject(fn ($o) => $o->id === $new->id)->values();
                $cards->splice(9, 0, [$new]);
            }
        }
        if (! empty($filters['date'])) {
            $limited = $cards->count() > config('marketplace-growth.date_business_limit');
            $cards = $cards->take(config('marketplace-growth.date_business_limit'))->filter(function ($row) use ($filters, $point) {
                $business = Business::query()->find($row->business_id);
                $match = app(DiscoveryFilters::class)->match($business, [...$filters, '_location' => $row->facts['location_public_id'], '_service' => $row->facts['service_public_id']], $point);
                $row->next_time = $match['next_time'] ?? null;

                return $match !== null;
            })->values();
        } else {
            $limited = $pool->count() >= $limit - $exploration;
        }
        $page = max(1, (int) ($filters['page'] ?? 1));
        $total = $cards->count();
        $paginator = new LengthAwarePaginator($cards->slice(($page - 1) * 12, 12)->map($this->card(...))->values(), $total, 12, $page, ['path' => request()->url(), 'query' => collect($filters)->except(['_location', '_service'])->all()]);

        return ['results' => $paginator, 'bounded' => $limited, 'ranking_version' => config('marketplace-growth.ranking_version')];
    }

    public function card(object $row): array
    {
        return [
            'name' => $row->business_name, 'url' => '/marketplace/salons/'.$row->slug.'?location='.$row->facts['location_public_id'],
            'point' => $row->latitude !== null && $row->longitude !== null ? ['lat' => (float) $row->latitude, 'lng' => (float) $row->longitude] : null,
            'image' => $row->facts['image'], 'address' => $row->address,
            'reviews' => ['count' => $row->review_count, 'average' => $row->review_average !== null ? (float) $row->review_average : null],
            'service' => $row->service_name, 'price_minor' => (int) $row->price_minor, 'currency' => $row->currency,
            'locale' => $row->facts['locale'], 'new_client_rule' => $row->facts['new_client_rule'], 'client_eligibility' => $row->facts['client_eligibility'],
            'next_time' => $row->next_time ?? null, 'distance' => $row->distance !== null ? round($row->distance, 1) : null,
            'new_on_marketplace' => CarbonImmutable::parse($row->first_listed_at)->gt(now()->subDays(30)),
        ];
    }

    public function suggestions(): array
    {
        return app(PublicOfferQuery::class)->query()->select('o.service_name')->distinct()->orderBy('o.service_name')->limit(100)->get()
            ->map(fn ($o) => ['name' => $o->service_name, 'category' => 'Bookable services'])->all();
    }
}
