<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DashboardRedirectController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $membership = $request->user()->memberships()->active()->with('business')->oldest('id')->first();

        abort_unless($membership?->business->isActive(), 403, 'No active Business membership is available.');

        return redirect()->route('business.dashboard', $membership->business);
    }
}
