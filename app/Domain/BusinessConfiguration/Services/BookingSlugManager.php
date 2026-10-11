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
            throw ValidationException::withMessages(['booking_slug' => 'Use 3 to 80 letters, numbers and hyphens for your booking address.']);
        }
        if (in_array($slug, config('booking.reserved_slugs'), true)) {
            throw ValidationException::withMessages(['booking_slug' => 'This booking address is reserved. Choose another.']);
        }

        return DB::transaction(function () use ($business, $slug): Business {
            $business = Business::query()->lockForUpdate()->findOrFail($business->getKey());
            $usedByBusiness = Business::query()->where('booking_slug', $slug)->whereKeyNot($business->getKey())->exists();
            $usedByAlias = BookingSlugAlias::query()->where('slug', $slug)->where('business_id', '!=', $business->getKey())->exists();
            if ($usedByBusiness || $usedByAlias) {
                throw ValidationException::withMessages(['booking_slug' => 'This booking link is already in use.']);
            }

            DB::table('booking_slug_reservations')->insertOrIgnore(['slug' => $slug, 'business_id' => $business->id]);
            if ((int) DB::table('booking_slug_reservations')->where('slug', $slug)->value('business_id') !== (int) $business->id) {
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

    public function generate(Business $business): Business
    {
        $base = substr(Str::slug($business->name), 0, 68);
        if (strlen($base) < 3 || in_array($base, config('booking.reserved_slugs'), true)) {
            $base = 'business'.($base ? '-'.$base : '');
        }
        for ($suffix = 1; $suffix <= 10000; $suffix++) {
            try {
                return $this->change($business, $base.($suffix === 1 ? '' : '-'.$suffix));
            } catch (ValidationException $error) {
                if ($suffix === 10000) {
                    throw $error;
                }
            }
        }
    }
}
