<?php

namespace App\Http\Controllers\PublicBooking;

use App\Domain\BusinessConfiguration\Services\BookingSlugManager;
use App\Domain\PublicBooking\Services\PublicBookingService;
use App\Http\Controllers\Controller;
use App\Support\BookingPageData;
use App\Support\BookingUrl;
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
            return redirect(BookingUrl::forSlug($business->booking_slug), 301);
        }

        return Inertia::render('Booking/Welcome', [
            'seo' => ['title' => 'Book with '.$business->name, 'description' => 'Choose a service and book an appointment with '.$business->name.'.', 'canonical' => BookingUrl::forSlug($business->booking_slug)],
            'business' => app(BookingPageData::class)->business($business),
            'catalog' => $booking->catalog($business),
        ]);
    }
}
