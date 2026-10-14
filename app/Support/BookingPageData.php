<?php

namespace App\Support;

use App\Domain\PlatformAccess\Models\Business;

class BookingPageData
{
    public function business(Business $business, bool $privatePreview = false): array
    {
        $mediaUrl = fn (string $kind) => $privatePreview
            ? route('business.configuration.booking-preview.media', [$business, $kind])
            : route('public.booking.media', [$business->booking_slug, $kind]);

        return [
            'booking_url' => filled($business->booking_slug) ? BookingUrl::forSlug($business->booking_slug) : null,
            ...$business->only(['name', 'booking_slug', 'business_type', 'description', 'brand_color', 'country_code', 'currency_code', 'phone', 'email', 'website_url', 'social_links', 'address', 'map_url']),
            'has_logo' => filled($business->logo_path),
            'has_cover_image' => filled($business->cover_image_path),
            'logo_url' => filled($business->logo_path) ? $mediaUrl('logo') : null,
            'cover_url' => filled($business->cover_image_path)
                ? $mediaUrl('cover')
                : config('business-onboarding.business_types.'.$business->business_type.'.image', '/images/marketing/editorial/product-workday.webp'),
            'cover_alt' => filled($business->cover_image_path)
                ? $business->name.' booking page cover'
                : config('business-onboarding.business_types.'.$business->business_type.'.label', 'Appointment business').' workspace atmosphere',
        ];
    }
}
