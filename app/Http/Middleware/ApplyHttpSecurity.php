<?php

namespace App\Http\Middleware;

use App\Support\Seo\PublicIndexation;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ApplyHttpSecurity
{
    public function __construct(private readonly PublicIndexation $indexation) {}

    public function handle(Request $request, Closure $next): Response
    {
        $supplied = (string) $request->header('X-Correlation-ID', '');
        $correlationId = Str::isUuid($supplied) ? strtolower($supplied) : (string) Str::uuid();

        $request->attributes->set('correlation_id', $correlationId);
        Log::withContext([
            'correlation_id' => $correlationId,
            'http_method' => $request->method(),
        ]);

        try {
            $response = $next($request);
        } finally {
            Log::withoutContext();
        }

        $response->headers->set('X-Correlation-ID', $correlationId);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(self)');
        $response->headers->set('X-Robots-Tag', $this->indexation->directive($request, $response));

        if (app()->environment('production') && $request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        if ($request->user() || $this->isTokenProtectedPath($request)) {
            $response->headers->set('Cache-Control', 'private, no-store');
            $response->headers->set('Pragma', 'no-cache');
        }

        return $response;
    }

    private function isTokenProtectedPath(Request $request): bool
    {
        return $request->is(
            'appointments/secure/*',
            'waitlist-offers/*',
            'client-forms/secure/*',
            'client-files/*',
            'communications/actions/*',
        );
    }
}
