<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class EnsureAdminSecurity
{
    public function handle(Request $request, Closure $next): Response
    {
        $panel = Filament::getPanel('admin');
        $user = $panel->auth()->user();
        abort_unless($user?->canAccessPanel($panel), 403);

        if ($request->routeIs('filament.admin.auth.logout')) {
            return $next($request);
        }

        if (! $user->hasVerifiedEmail()) {
            return $request->routeIs('filament.admin.auth.email-verification.*')
                ? $next($request)
                : redirect()->to($panel->getEmailVerificationPromptUrl());
        }

        // Validate session limits separately so a resource's own 403 does not log out the operator.
        try {
            app(EnsurePlatformAccess::class)->enforceSessionLimits($request);
        } catch (HttpException $exception) {
            if ($exception->getStatusCode() !== 403) {
                throw $exception;
            }

            $panel->auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->to($panel->getLoginUrl());
        }

        return $next($request);
    }
}
