<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/dashboard';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        foreach (['status' => 30, 'restart' => 5, 'review' => 10, 'confirm' => 5] as $action => $perMinute) {
            RateLimiter::for('billing-capacity-'.$action, function (Request $request) use ($perMinute) {
                return Limit::perMinute($perMinute)->by((string) ($request->user()?->id ?? $request->ip()))
                    ->response(fn (Request $request, array $headers) => response()->json([
                        'code' => 'billing_retry_later',
                        'message' => 'Please wait a moment before trying again. Your selection is saved.',
                        'retry_after' => (int) ($headers['Retry-After'] ?? 60),
                    ], 429, $headers));
            });
        }

        foreach (['search' => 120, 'feedback' => 15] as $action => $perMinute) {
            RateLimiter::for('help-'.$action, function (Request $request) use ($perMinute, $action) {
                return Limit::perMinute($perMinute)->by((string) ($request->user()?->id ?? $request->ip()))
                    ->response(fn (Request $request, array $headers) => response()->json([
                        'code' => 'help_retry_later',
                        'message' => $action === 'search' ? 'Please wait a moment before searching again. You can still browse all topics.' : 'Please wait a moment before sending feedback again. You can continue reading.',
                        'retry_after' => (int) ($headers['Retry-After'] ?? 60),
                    ], 429, $headers));
            });
        }

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }
}
