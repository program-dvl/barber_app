<?php

namespace App\Support;

final class BookingUrl
{
    public static function forSlug(?string $slug): ?string
    {
        return filled($slug) ? config('booking.scheme').'://'.config('booking.host').'/'.rawurlencode($slug) : null;
    }
}
