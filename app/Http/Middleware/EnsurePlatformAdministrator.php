<?php

namespace App\Http\Middleware;

use App\Domain\PlatformAccess\Enums\PlatformRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformAdministrator
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless(
            $user
            && $user->hasVerifiedEmail()
            && filled($user->two_factor_confirmed_at)
            && $user->hasPlatformRole(PlatformRole::Administrator),
            403
        );

        return $next($request);
    }
}
