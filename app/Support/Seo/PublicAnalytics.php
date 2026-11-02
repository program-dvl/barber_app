<?php

namespace App\Support\Seo;

use Illuminate\Http\Request;

class PublicAnalytics
{
    /** @return array<string, mixed>|null */
    public function for(Request $request): ?array
    {
        $id = config('analytics.measurement_id');
        $origin = rtrim((string) config('brand.website_url'), '/');
        $url = parse_url($origin);
        if (! app()->environment('production') || ! is_string($id) || ! preg_match('/^G-[A-Z0-9]{6,20}$/D', $id)
            || ! $url || ($url['scheme'] ?? null) !== 'https' || empty($url['host'])
            || isset($url['user']) || isset($url['pass']) || isset($url['query']) || isset($url['fragment'])
            || ! in_array($url['path'] ?? '', ['', '/'], true)
            || $request->getSchemeAndHttpHost() !== $origin || $request->user()
            || $request->attributes->get('force_noindex') === true) {
            return null;
        }

        $paths = ['/', '/features', '/solutions', '/use-cases', '/pricing', '/company', '/security', '/resources', '/blog'];
        foreach (['features' => '/features/', 'solutions' => '/solutions/', 'use_cases' => '/use-cases/', 'guides' => '/guides/'] as $key => $prefix) {
            foreach (array_keys(config('frontsite.'.$key, [])) as $slug) {
                if (is_string($slug) && preg_match('/^[a-z0-9-]+$/D', $slug)) {
                    $paths[] = $prefix.$slug;
                }
            }
        }
        $path = '/'.ltrim($request->path(), '/');
        $marketing = $request->routeIs('marketing.*', 'blog.index') && in_array($path, $paths, true);
        if (! $marketing && ! $request->routeIs('terms.show', 'policy.show', 'refund.show')) {
            return null;
        }

        return [
            'measurement_id' => $id,
            'paths' => $paths,
            // No request query, referrer, account data or customer-defined title crosses this boundary.
            'page' => $marketing ? ['location' => $origin.$path, 'path' => $path, 'title' => 'ClipperDesk'] : null,
        ];
    }
}
