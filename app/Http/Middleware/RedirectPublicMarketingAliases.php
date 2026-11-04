<?php

namespace App\Http\Middleware;

use App\Support\Seo\PublicIndexation;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class RedirectPublicMarketingAliases
{
    public function __construct(private readonly PublicIndexation $indexation) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! app()->environment('production')
            || ! in_array($request->method(), ['GET', 'HEAD'], true)
            || $response->getStatusCode() !== 200
            || ! $this->indexation->isIndexableRouteName($request->route()?->getName())
            || ! str_starts_with($this->indexation->directive($request, $response), 'index,')
            || $request->query->has('signature')) {
            return $response;
        }

        $origin = $this->primaryOrigin();
        if ($origin === null) {
            return $response;
        }

        $primaryHost = parse_url($origin, PHP_URL_HOST);
        $bookingHost = parse_url('https://'.(string) config('booking.host'), PHP_URL_HOST);
        $host = strtolower($request->getHost());
        $isWwwAlias = $host === 'www.'.$primaryHost;
        $isBookingAlias = is_string($bookingHost) && $host === strtolower($bookingHost) && $host !== $primaryHost;

        // Booking entry points and tenant/task routes are outside the explicit
        // acquisition registry, including /book and each business's /{slug}.
        if (! $isWwwAlias && ! $isBookingAlias) {
            return $response;
        }

        $target = $origin.$request->getPathInfo();
        $query = $request->server->get('QUERY_STRING');
        if (is_string($query) && $query !== '') {
            $target .= '?'.$query;
        }

        // Inertia must perform a full external visit rather than an off-origin XHR.
        return $request->header('X-Inertia')
            ? Inertia::location($target)
            : redirect()->away($target, 301);
    }

    private function primaryOrigin(): ?string
    {
        $url = (string) config('brand.website_url');
        $parts = parse_url($url);
        if (! filter_var($url, FILTER_VALIDATE_URL) || ! is_array($parts)
            || ($parts['scheme'] ?? null) !== 'https' || blank($parts['host'] ?? null)
            || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['query']) || isset($parts['fragment'])
            || ! in_array($parts['path'] ?? '', ['', '/'], true)) {
            return null;
        }

        return 'https://'.strtolower($parts['host']).(isset($parts['port']) ? ':'.$parts['port'] : '');
    }
}
