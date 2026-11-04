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
            // Blog pages contain different articles. Only the controller's
            // validated page number becomes URL identity; query input stays out.
            if (request()->routeIs('blog.index') && ($page = data_get($props, 'articles.current_page', 1)) > 1) {
                $props['seo']['canonical'] .= '?page='.$page;
            }
        }
        $props['seo'] = [
            'image' => url(config('brand.social_image')),
            'site_name' => config('brand.product_name'),
            'locale' => 'en_IN',
            'type' => request()->routeIs('blog.article', 'marketing.guides.show', 'help.article') ? 'article' : 'website',
            ...($props['seo'] ?? []),
            'robots' => app(PublicIndexation::class)->directive(request()),
        ];
        $props['structuredData'] = app(StructuredDataGraph::class)->for(request(), $props);

        return Inertia::render($component, $props);
    }
}
