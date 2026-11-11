<?php

namespace App\Support\Seo;

use App\Support\Legal\PrivacyNotice;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PublicIndexation
{
    private const INDEXABLE = ['help.index', 'help.category', 'help.article', 'marketing.home', 'marketing.product-tour', 'marketing.features', 'marketing.features.show', 'marketing.solutions', 'marketing.solutions.show', 'marketing.use-cases', 'marketing.use-cases.show', 'marketing.pricing', 'marketing.company', 'marketing.security', 'marketing.resources', 'marketing.guides.show', 'blog.index', 'blog.article'];

    public function directive(Request $request, ?Response $response = null): string
    {
        if ($request->attributes->get('force_noindex') === true) {
            return 'noindex, nofollow, noarchive';
        }

        if ($response && $response->getStatusCode() >= 400) {
            return 'noindex, nofollow, noarchive';
        }
        $name = $request->route()?->getName();
        if (in_array($name, ['marketplace.discover', 'marketplace.local', 'marketplace.local.country', 'marketplace.local.service', 'marketplace.salon'], true)) {
            $page = $request->attributes->get('marketplace_seo_page');
            $allowedQuery = $name === 'marketplace.salon' ? [] : ['page'];
            if ($page && array_diff(array_keys($request->query()), $allowedQuery) === []
                && app(\App\Domain\Marketplace\LocalGrowth\Services\SeoPageRegistry::class)->indexable($page)) {
                return 'index, follow, max-image-preview:large';
            }
            return 'noindex, follow, noarchive';
        }
        if ($name === 'policy.show' && app(PrivacyNotice::class)->isPublished()) {
            return 'index, follow, max-image-preview:large';
        }
        if (in_array($name, self::INDEXABLE, true)) {
            return 'index, follow, max-image-preview:large';
        }
        if ($request->is('book', 'book/*') || $name === 'booking.business' || in_array($name, ['login', 'register', 'terms.show', 'policy.show', 'refund.show'], true)) {
            return 'noindex, follow, noarchive';
        }

        return 'noindex, nofollow, noarchive';
    }

    public function isIndexableRouteName(?string $name): bool
    {
        return in_array($name, self::INDEXABLE, true);
    }
}
