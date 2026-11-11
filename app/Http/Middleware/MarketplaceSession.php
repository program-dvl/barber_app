<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class MarketplaceSession
{
    public function handle(Request $request, Closure $next)
    {
        if (! Auth::guard('marketplace')->check()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Sign in to continue. Your entered work is still on this page.'], 401);
            }

            if ($request->isMethod('GET') && $request->routeIs('marketplace.messages') && Str::isUlid((string) $request->route('appointment'))) {
                $request->session()->put('marketplace_conversation_return', ['path' => '/marketplace/account/bookings/'.$request->route('appointment').'/messages', 'expires_at' => now()->addMinutes(30)->timestamp]);
            }

            return redirect('/marketplace/sign-in');
        }

        return $next($request);
    }
}
