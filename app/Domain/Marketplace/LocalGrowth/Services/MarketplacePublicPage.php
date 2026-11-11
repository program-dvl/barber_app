<?php

namespace App\Domain\Marketplace\LocalGrowth\Services;

use App\Support\Seo\PublicIndexation;
use App\Support\Seo\PublicUrl;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;

class MarketplacePublicPage
{
    public function render(string $component, array $props, array $content, ?object $registry = null)
    {
        $request = request();
        $canonical = PublicUrl::canonical($request->url());
        $pagination = $props['results'] ?? null;
        $page = $pagination instanceof LengthAwarePaginator ? $pagination->currentPage() : (int) data_get($pagination, 'current_page', 1);
        if ($page > 1) {
            $canonical .= '?page='.$page;
        }
        $request->attributes->set('marketplace_seo_page', $registry);
        $seo = ['title' => $content['title'].' | Clipperdesk Marketplace', 'description' => mb_substr($content['description'], 0, 300), 'canonical' => $canonical, 'robots' => app(PublicIndexation::class)->directive($request), 'locale' => str_replace('-', '_', $content['locale'] ?? 'en'), 'image' => url(config('brand.social_image'))];

        return Inertia::render($component, [...$props, 'seo' => $seo, 'publicContent' => $content, 'acquisitionPublic' => ['page' => $registry?->path ?? ($content['page_key'] ?? '/marketplace'), 'kind' => $content['kind']], 'structuredData' => $this->schema($canonical, $content)]);
    }

    public function schema(string $canonical, array $content): array
    {
        $graph = [['@type' => 'WebPage', '@id' => $canonical.'#webpage', 'url' => $canonical, 'name' => $content['title'], 'description' => $content['description']]];
        $breadcrumbs = [['@type' => 'ListItem', 'position' => 1, 'name' => 'Marketplace', 'item' => PublicUrl::canonical(url('/marketplace'))]];
        foreach ($content['breadcrumbs'] ?? [] as $i => $link) {
            $breadcrumbs[] = ['@type' => 'ListItem', 'position' => $i + 2, 'name' => $link['name'], 'item' => PublicUrl::canonical(url($link['url']))];
        }
        $graph[] = ['@type' => 'BreadcrumbList', 'itemListElement' => $breadcrumbs];
        if (($content['kind'] ?? '') === 'profile') {
            $entityId = PublicUrl::canonical(url('/marketplace')).'#business-'.$content['public_id'];
            $business = ['@type' => 'LocalBusiness', '@id' => $entityId, 'name' => $content['name'], 'url' => preg_replace('/\?.*/', '', $canonical), 'description' => $content['description']];
            $business['location'] = collect($content['locations'])->map(fn ($l) => ['@type' => 'Place', '@id' => $entityId.'-branch-'.$l['public_id'], 'name' => $l['name'], 'address' => ['@type' => 'PostalAddress', 'streetAddress' => $l['address']], 'openingHoursSpecification' => collect($l['hours'])->map(fn ($h) => ['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'][$h['day']], 'opens' => $h['opens_at'], 'closes' => $h['closes_at']])->all()])->all();
            $business['hasOfferCatalog'] = ['@type' => 'OfferCatalog', 'name' => 'Appointments', 'itemListElement' => collect($content['services'])->map(fn ($s) => ['@type' => 'Offer', 'price' => number_format($s['price_minor'] / 100, 2, '.', ''), 'priceCurrency' => $s['currency'], 'description' => 'Starting price; confirm your selection before booking.', 'itemOffered' => ['@type' => 'Service', 'name' => $s['name']]])->all()];
            $graph[] = $business;
        } else {
            $graph[] = ['@type' => 'ItemList', 'itemListElement' => collect($content['cards'] ?? [])->values()->map(fn ($c, $i) => ['@type' => 'ListItem', 'position' => ($content['offset'] ?? 0) + $i + 1, 'name' => $c['name'], 'url' => PublicUrl::canonical(url(strtok($c['url'], '?')))])->all()];
        }

        return ['@context' => 'https://schema.org', '@graph' => $graph];
    }

    public function profileContent(array $offers): array
    {
        $first = $offers[0];
        $facts = json_decode($first->facts, true);

        return ['kind' => 'profile', 'page_key' => 'provider:'.$facts['business_public_id'], 'public_id' => $facts['business_public_id'], 'title' => $first->business_name.' — services & appointments', 'name' => $first->business_name, 'description' => $facts['description'] ?: 'Explore published services and choose an appointment.', 'locale' => $facts['locale'],
            'locations' => collect($offers)->unique('location_id')->map(fn ($o) => ['public_id' => json_decode($o->facts, true)['location_public_id'], 'name' => json_decode($o->facts, true)['location_name'], 'address' => $o->address, 'hours' => json_decode($o->facts, true)['opening_hours'], 'time_zone' => json_decode($o->facts, true)['time_zone']])->values()->all(),
            'services' => collect($offers)->map(fn ($o) => ['name' => $o->service_name, 'description' => json_decode($o->facts, true)['service_description'], 'price_minor' => (int) $o->price_minor, 'currency' => $o->currency, 'duration_minutes' => json_decode($o->facts, true)['duration_minutes'], 'url' => '/marketplace/salons/'.$o->slug.'?service='.json_decode($o->facts, true)['service_public_id'].'&location='.json_decode($o->facts, true)['location_public_id']])->values()->all(),
            'reviews' => $facts['reviews'], 'links' => app(SeoPageRegistry::class)->publicLinks($first->geography_id), 'breadcrumbs' => [],
        ];
    }
}
