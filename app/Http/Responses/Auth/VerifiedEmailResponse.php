<?php

namespace App\Http\Responses\Auth;

use App\Domain\Billing\Models\OwnerRegistrationIntent;
use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\VerifyEmailResponse;

class VerifiedEmailResponse implements VerifyEmailResponse
{
    public function toResponse($request)
    {
        if ($request->wantsJson()) {
            return new JsonResponse('', 204);
        }

        $business = OwnerRegistrationIntent::query()
            ->where('user_id', $request->user()->getKey())
            ->whereNotNull('business_id')
            ->with('business:id,public_id')
            ->first()?->business;

        $destination = $business
            ? route('business.configuration.show', $business)
            : route('dashboard');

        $request->session()->forget('url.intended');

        return redirect()->to($destination.'?verified=1');
    }
}
