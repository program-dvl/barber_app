<?php

namespace App\Http\Controllers\Marketplace;

use App\Domain\BusinessConfiguration\Services\BookingSlugManager;
use App\Domain\Marketplace\LocalGrowth\Services\MarketplacePublicPage;
use App\Domain\Marketplace\LocalGrowth\Services\PublicOfferQuery;
use App\Domain\Marketplace\Services\MarketplaceListings;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PublicBooking\Services\PublicBookingService;
use App\Domain\SchedulingOperations\Exceptions\BookingRuleViolation;
use App\Http\Controllers\Controller;
use App\Support\BookingPageData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SalonController extends Controller
{
    public function show(string $slug, MarketplaceListings $listings)
    {
        request()->validate(['location' => ['nullable', 'string', 'max:40']]);

        if (config('marketplace-growth.enabled')) {
            $business = app(BookingSlugManager::class)->resolve($slug);
            abort_unless($business, 404);
            $listings->resolve($business->booking_slug);
            $page = DB::table('marketplace_seo_pages')->where('business_id', $business->id)->where('kind', 'profile')->first();
            abort_if($page && in_array($page->status, ['paused', 'withdrawn'], true), 404);
            abort_unless(app(PublicOfferQuery::class)->query()->where('o.business_id', $business->id)->exists(), 404);
            if ($business->booking_slug !== $slug) {
                return redirect('/marketplace/salons/'.$business->booking_slug, 301);
            }

            return $this->render($business, false, request('location'));
        }

        return $this->render($listings->resolve($slug), false, request('location'));
    }

    public function render(Business $business, bool $preview = false, ?string $initialLocation = null)
    {
        $booking = app(PublicBookingService::class);

        $props = [
            'business' => app(BookingPageData::class)->business($business, $preview),
            'catalog' => $preview ? $booking->previewCatalog($business) : $booking->catalog($business),
            'preview' => $preview, 'marketplace' => true,
            'initialChoice' => [...request()->validate(['service' => ['nullable', 'string', 'max:40'], 'date' => ['nullable', 'date_format:Y-m-d']]), 'location' => $initialLocation],
            'saved' => Auth::guard('marketplace')->check() && DB::table('marketplace_favourites')->where('customer_id', Auth::guard('marketplace')->id())->where('business_id', $business->id)->exists(),
        ];
        if (config('marketplace-growth.enabled') && ! $preview) {
            $page = DB::table('marketplace_seo_pages')->where('business_id', $business->id)->where('kind', 'profile')->first();
            $offers = app(PublicOfferQuery::class)->query()->where('o.business_id', $business->id)->orderBy('o.id')->limit(200)->get()->all();
            if ($offers) {
                $pages = app(MarketplacePublicPage::class);

                return $pages->render('Booking/Welcome', $props, $pages->profileContent($offers), $page);
            }
        }

        return Inertia::render('Booking/Welcome', $props);
    }

    public function book(Request $request, string $slug, MarketplaceListings $listings)
    {
        $listings->resolve($slug);
        $choice = $request->validate(['location' => ['nullable', 'string', 'max:40'], 'service' => ['nullable', 'string', 'max:40'], 'date' => ['nullable', 'date_format:Y-m-d']]);

        return redirect()->route('marketplace.salon', ['slug' => $slug, ...$choice], 301);
    }

    public function availability(Request $request, string $slug, MarketplaceListings $listings, PublicBookingService $booking)
    {
        $data = $request->validate(['location' => ['required', 'string', 'max:40'], 'service' => ['required', 'string', 'max:40'], 'date' => ['required', 'date_format:Y-m-d']]);
        try {
            $slots = $booking->search($listings->resolve($slug), $data['location'], [$data['service']], null, $data['date'], $data['date'], 'auto');
        } catch (BookingRuleViolation $error) {
            return response()->json($error->toDomainError(), 409);
        }

        return response()->json(['slots' => array_slice($slots, 0, 12)]);
    }
}
