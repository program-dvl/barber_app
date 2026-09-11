<?php

namespace App\Http\Responses\Auth;

use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\RegisterResponse;
use Laravel\Fortify\Features;

class RegistrationResponse implements RegisterResponse
{
    public function toResponse($request)
    {
        if ($request->wantsJson()) {
            return new JsonResponse('', 201);
        }

        // A registration must never inherit a protected URL left in the guest
        // session by another tab. Establish a fresh authenticated session and
        // move deliberately to verification (or the dashboard when disabled).
        $request->session()->regenerate();
        $request->session()->forget('url.intended');
        $destination = Features::enabled(Features::emailVerification())
            ? route('verification.notice')
            : route('dashboard');

        return $request->header('X-Inertia')
            ? Inertia::location($destination)
            : redirect()->to($destination);
    }
}
