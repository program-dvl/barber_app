<?php

namespace App\Http\Controllers\PublicBooking;

use App\Domain\BusinessConfiguration\Services\BookingSlugManager;
use App\Domain\ClientReviews\Services\ClientReviewService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicClientReviewsController extends Controller
{
    public function __invoke(Request $request, string $slug, BookingSlugManager $slugs, ClientReviewService $reviews): JsonResponse
    {
        $business = $slugs->resolve($slug);
        abort_unless($business?->configuration_published_at && $business->online_booking_enabled && $business->isActive(), 404);
        $data = $request->validate(['before' => ['nullable', 'ulid']]);

        return response()->json($reviews->publicPage($business, $data['before'] ?? null), 200, ['Cache-Control' => 'private, no-store']);
    }
}
