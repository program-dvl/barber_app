<?php

namespace App\Http\Controllers\Marketplace;

use App\Domain\Marketplace\LocalGrowth\Services\BookingAttribution;
use App\Domain\PlatformAccess\Models\Business;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AcquisitionController extends Controller
{
    public function observe(Request $request, BookingAttribution $attribution)
    {
        abort_unless(config('marketplace-growth.enabled'), 404);
        $data = $request->validate([
            'consent' => ['required', 'accepted'], 'version' => ['required', 'in:mpg-consent-v1'],
            'page' => ['required', 'string', 'max:255', 'regex:#^(?:/marketplace(?:/[a-z0-9/-]+)?|provider:[0-9A-HJKMNP-TV-Z]{26})$#D'],
            'source' => ['required', Rule::in(['marketplace_discovery', 'external_search', 'owner_link', 'direct'])],
            'owner_source' => ['required', Rule::in(['unknown', 'owner_website', 'whatsapp', 'google_business_profile'])],
        ]);
        $path = $data['page'];
        $public = (str_starts_with($path, 'provider:') && Business::query()->where('public_id', substr($path, 9))->where('status', 'active')->whereNotNull('configuration_published_at')->where('online_booking_enabled', true)->exists()) || $path === '/marketplace' || DB::table('marketplace_seo_pages')->where('path', $path)->where('status', 'approved')->exists();
        // No account, token, arbitrary URL, search phrase or referrer can become evidence.
        abort_unless($public, 422);
        if ($data['source'] === 'marketplace_discovery') {
            abort_unless($path === '/marketplace' || DB::table('marketplace_seo_pages')->where('path', $path)->where('kind', '!=', 'profile')->exists(), 422);
        }
        $attribution->observe($request, $path, $data['source'], $data['owner_source']);

        return response()->noContent();
    }

    public function forget(Request $request, BookingAttribution $attribution)
    {
        abort_unless(config('marketplace-growth.enabled'), 404);
        $attribution->forget($request);

        return response()->noContent();
    }
}
