<?php

namespace App\Support\Seo;

use Illuminate\Http\Request;

class StructuredDataGraph
{
    public function for(Request $request, array $props): array
    {
        if (! app(PublicIndexation::class)->isIndexableRouteName($request->route()?->getName())) {
            return [];
        }
        $root = rtrim(config('app.url'), '/');
        $canonical = data_get($props, 'seo.canonical', $request->url());
        $organizationId = $root.'/#organization';
        $websiteId = $root.'/#website';
        $graph = [
            ['@type' => 'Organization', '@id' => $organizationId, 'name' => 'Good Hours', 'url' => $root],
            ['@type' => 'WebSite', '@id' => $websiteId, 'name' => 'Good Hours', 'url' => $root, 'publisher' => ['@id' => $organizationId], 'inLanguage' => 'en-IN'],
            ['@type' => 'WebPage', '@id' => $canonical.'#webpage', 'url' => $canonical, 'name' => data_get($props, 'seo.title', 'Good Hours'), 'description' => data_get($props, 'seo.description', 'One calm place to run bookings, clients, staff, checkout and the day ahead.'), 'isPartOf' => ['@id' => $websiteId], 'inLanguage' => 'en-IN'],
        ];
        if ($request->routeIs('marketing.home', 'marketing.pricing')) {
            $software = ['@type' => 'SoftwareApplication', '@id' => $root.'/#software', 'name' => 'Good Hours', 'applicationCategory' => 'BusinessApplication', 'operatingSystem' => 'Web', 'url' => $root, 'description' => 'The daily operating system for salons and barbershops, helping run work from booking to checkout.', 'publisher' => ['@id' => $organizationId]];
            if ($request->routeIs('marketing.pricing') && data_get($props, 'catalog.available') === true) {
                $software['offers'] = collect(data_get($props, 'catalog.plans', []))->flatMap(fn (array $plan) => collect($plan['prices'])->map(fn (array $price, string $interval) => ['@type' => 'Offer', 'name' => $plan['name'].' '.ucfirst($interval), 'price' => number_format($price['amount_minor'] / 100, 2, '.', ''), 'priceCurrency' => $price['currency'], 'availability' => 'https://schema.org/InStock', 'url' => route('marketing.pricing')]))->values()->all();
            }
            $graph[] = $software;
        }
        if ($request->routeIs('blog.article') && filled(data_get($props, 'article.title'))) {
            $article = data_get($props, 'article', []);
            $graph[] = ['@type' => 'Article', '@id' => $canonical.'#article', 'headline' => $article['title'], 'description' => $article['excerpt'], 'datePublished' => $article['published_at'], 'dateModified' => $article['materially_updated_at'] ?? $article['published_at'], 'author' => ['@type' => 'Person', 'name' => $article['author']], 'publisher' => ['@id' => $organizationId], 'mainEntityOfPage' => ['@id' => $canonical.'#webpage']];
        }
        if ($request->routeIs('marketing.guides.show') && filled(data_get($props, 'guide.title'))) {
            $guide = data_get($props, 'guide', []);
            $graph[] = ['@type' => 'Article', '@id' => $canonical.'#article', 'headline' => $guide['title'], 'description' => $guide['description'], 'datePublished' => $guide['published_at'], 'author' => ['@type' => 'Organization', 'name' => $guide['reviewed_by']], 'publisher' => ['@id' => $organizationId], 'mainEntityOfPage' => ['@id' => $canonical.'#webpage']];
        }

        return ['@context' => 'https://schema.org', '@graph' => $graph];
    }
}
