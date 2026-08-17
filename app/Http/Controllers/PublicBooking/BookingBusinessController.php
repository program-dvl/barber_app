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
                ...$business->only(['name', 'booking_slug', 'phone', 'email', 'address', 'map_url']),
                'has_logo' => filled($business->logo_path),
                'has_cover_image' => filled($business->cover_image_path),
            ],
            'catalog' => $booking->catalog($business),
        ]);
    }
}
