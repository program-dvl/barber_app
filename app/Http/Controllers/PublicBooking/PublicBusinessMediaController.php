<?php

namespace App\Http\Controllers\PublicBooking;

use App\Domain\BusinessConfiguration\Services\BookingSlugManager;
use App\Http\Controllers\Controller;
use App\Support\Files\TenantFilePath;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class PublicBusinessMediaController extends Controller
{
    public function __invoke(string $slug, string $kind, BookingSlugManager $slugs): Response
    {
        abort_unless(in_array($kind, ['logo', 'cover'], true), 404);
        $business = $slugs->resolve($slug);
        abort_unless($business?->configuration_published_at && $business->online_booking_enabled && $business->isActive(), 404);

        $path = $kind === 'logo' ? $business->logo_path : $business->cover_image_path;
        abort_unless(filled($path), 404);
        $storedKey = TenantFilePath::private($business, $path);
        abort_unless(Storage::disk('private')->exists($storedKey), 404);

        $mime = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };

        return response(Storage::disk('private')->get($storedKey), 200, [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
