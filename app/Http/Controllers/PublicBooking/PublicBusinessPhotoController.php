<?php

namespace App\Http\Controllers\PublicBooking;

use App\Domain\BusinessConfiguration\Services\BookingSlugManager;
use App\Domain\BusinessConfiguration\Services\BusinessPhotoGallery;
use App\Http\Controllers\Controller;

class PublicBusinessPhotoController extends Controller
{
    public function __invoke(string $slug, string $photo, BookingSlugManager $slugs, BusinessPhotoGallery $gallery)
    {
        $business = $slugs->resolve($slug);
        abort_unless($business?->configuration_published_at && $business->online_booking_enabled && $business->isActive(), 404);

        return $gallery->image($business, $photo);
    }
}
