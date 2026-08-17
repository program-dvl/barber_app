<?php

namespace App\Domain\BusinessConfiguration\Services;

use App\Domain\BusinessConfiguration\Models\BookingSlugAlias;
use App\Domain\PlatformAccess\Models\Business;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BookingSlugManager
{
    public function change(Business $business, string $requestedSlug): Business
    {
        $slug = Str::slug($requestedSlug);
        if ($slug === '' || strlen($slug) < 3 || strlen($slug) > 80) {
            throw ValidationException::withMessages(['booking_slug' => 'The booking slug must contain 3 to 80 URL-safe characters.']);
        }

        return DB::transaction(function () use ($business, $slug): Business {
            $business = Business::query()->lockForUpdate()->findOrFail($business->getKey());
            $usedByBusiness = Business::query()->where('booking_slug', $slug)->whereKeyNot($business->getKey())->exists();
            $usedByAlias = BookingSlugAlias::query()->where('slug', $slug)->where('business_id', '!=', $business->getKey())->exists();
            if ($usedByBusiness || $usedByAlias) {
                throw ValidationException::withMessages(['booking_slug' => 'This booking link is already in use.']);
            }

            if ($business->booking_slug && $business->booking_slug !== $slug) {
                BookingSlugAlias::query()->firstOrCreate(
                    ['business_id' => $business->getKey(), 'slug' => $business->booking_slug],
                    ['redirected_at' => now()],
                );
            }
            $business->forceFill(['booking_slug' => $slug])->save();

            return $business->fresh();
        }, 3);
    }

    public function resolve(string $slug): ?Business
    {
        return Business::query()->where('booking_slug', $slug)->first()
            ?? BookingSlugAlias::query()->where('slug', $slug)->first()?->business;
    }
}
