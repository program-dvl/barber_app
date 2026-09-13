<?php

namespace App\Http\Controllers\PublicBooking;

use App\Domain\BusinessConfiguration\Services\BookingSlugManager;
use App\Domain\PublicBooking\Services\PublicBookingService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class BookingBusinessController extends Controller
{
    public function __invoke(string $slug, BookingSlugManager $slugs, PublicBookingService $booking): Response|RedirectResponse
    {
        $business = $slugs->resolve($slug);
        abort_unless($business?->configuration_published_at && $business->online_booking_enabled, 404);
        if ($business->booking_slug !== $slug) {
            return redirect()->route('booking.business', $business->booking_slug, 301);
        }

        return Inertia::render('Booking/Welcome', [
            'business' => [
                ...$business->only(['name', 'booking_slug', 'business_type', 'description', 'brand_color', 'country_code', 'currency_code', 'phone', 'email', 'website_url', 'social_links', 'address', 'map_url']),
                'has_logo' => filled($business->logo_path),
                'has_cover_image' => filled($business->cover_image_path),
                'logo_url' => filled($business->logo_path) ? route('public.booking.media', [$business->booking_slug, 'logo']) : null,
                'cover_url' => filled($business->cover_image_path)
                    ? route('public.booking.media', [$business->booking_slug, 'cover'])
                    : config('business-onboarding.business_types.'.$business->business_type.'.image', '/images/marketing/editorial/product-workday.webp'),
                'cover_alt' => filled($business->cover_image_path)
                    ? $business->name.' booking page cover'
                    : config('business-onboarding.business_types.'.$business->business_type.'.label', 'Appointment business').' workspace atmosphere',
            ],
            'catalog' => $booking->catalog($business),
        ]);
    }
}
