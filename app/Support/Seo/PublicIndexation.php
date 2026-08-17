<?php

namespace App\Support\Seo;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PublicIndexation
{
    private const INDEXABLE = ['marketing.home', 'marketing.features', 'marketing.features.show', 'marketing.solutions', 'marketing.solutions.show', 'marketing.use-cases', 'marketing.use-cases.show', 'marketing.pricing', 'marketing.company', 'marketing.security', 'marketing.resources', 'marketing.guides.show', 'blog.index', 'blog.article'];

    public function directive(Request $request, ?Response $response = null): string
    {
        if ($request->attributes->get('force_noindex') === true) {
            return 'noindex, nofollow, noarchive';
        }

        if ($response && $response->getStatusCode() >= 400) {
            return 'noindex, nofollow, noarchive';
        }
        $name = $request->route()?->getName();
        if (in_array($name, self::INDEXABLE, true)) {
            return 'index, follow, max-image-preview:large';
        }
        if ($request->is('book', 'book/*') || in_array($name, ['login', 'register', 'terms.show', 'policy.show'], true)) {
            return 'noindex, follow, noarchive';
        }

        return 'noindex, nofollow, noarchive';
    }

    public function isIndexableRouteName(?string $name): bool
    {
        return in_array($name, self::INDEXABLE, true);
    }
}
