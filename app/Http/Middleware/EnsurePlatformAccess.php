<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformAccess
{
    private const IDLE_SECONDS = 900;

    private const ABSOLUTE_SECONDS = 28800;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user && $user->hasVerifiedEmail() && filled($user->two_factor_confirmed_at) && $user->activePlatformRoles()->exists(), 403);

        $now = now()->timestamp;
        $started = (int) $request->session()->get('platform_session_started_at', $now);
        $lastUsed = (int) $request->session()->get('platform_session_last_used_at', $now);
        if (($now - $started) > self::ABSOLUTE_SECONDS || ($now - $lastUsed) > self::IDLE_SECONDS) {
            $request->session()->forget(['platform_session_started_at', 'platform_session_last_used_at', 'support_access_session_id']);
            abort(403, 'The protected platform session expired. Re-authenticate to continue.');
        }
        $request->session()->put(['platform_session_started_at' => $started, 'platform_session_last_used_at' => $now]);

        return $next($request);
    }
}
