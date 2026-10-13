<?php

namespace App\Support\Seo;

use Inertia\Inertia;
use Inertia\Response;

class PublicPage
{
    public static function render(string $component, array $props): Response
    {
        if (filled($props['seo']['canonical'] ?? null)) {
            $props['seo']['canonical'] = PublicUrl::canonical($props['seo']['canonical']);
        }
        $props['seo'] = [
            'image' => url(config('brand.social_image')),
            'site_name' => config('brand.product_name'),
            'locale' => 'en_IN',
            'type' => request()->routeIs('blog.article', 'marketing.guides.show') ? 'article' : 'website',
            ...($props['seo'] ?? []),
            'robots' => app(PublicIndexation::class)->directive(request()),
        ];
        $props['structuredData'] = app(StructuredDataGraph::class)->for(request(), $props);

        return Inertia::render($component, $props);
    }
}
