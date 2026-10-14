<?php

namespace App\Http\Controllers\Shop;

use App\Domain\BusinessConfiguration\Services\BusinessSetupAccess;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PublicBooking\Services\PublicBookingService;
use App\Http\Controllers\Controller;
use App\Support\BookingPageData;
use App\Support\Files\TenantFilePath;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class BookingPreviewController extends Controller
{
    private function authorizePreview(Business $business): void
    {
        abort_unless(app(BusinessSetupAccess::class)->allows($business, app(TenantContext::class)->membership()), 403);
    }

    public function show(Business $business, PublicBookingService $booking, BookingPageData $page)
    {
        $this->authorizePreview($business);

        return Inertia::render('Booking/Welcome', [
            'business' => $page->business($business, true),
            'catalog' => $booking->previewCatalog($business),
            'preview' => true,
        ]);
    }

    public function media(Business $business, string $kind)
    {
        $this->authorizePreview($business);
        abort_unless(in_array($kind, ['logo', 'cover'], true), 404);
        $path = $kind === 'logo' ? $business->logo_path : $business->cover_image_path;
        abort_unless(filled($path), 404);
        $key = TenantFilePath::private($business, $path);
        abort_unless(Storage::disk('private')->exists($key), 404);
        $mime = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'png' => 'image/png', 'webp' => 'image/webp', default => 'image/jpeg',
        };

        return response(Storage::disk('private')->get($key), 200, ['Content-Type' => $mime]);
    }
}
