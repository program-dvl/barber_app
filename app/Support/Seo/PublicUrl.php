<?php

namespace App\Support\Seo;

class PublicUrl
{
    public static function canonical(string $url): string
    {
        if (! app()->environment('production')) {
            return $url;
        }

        return rtrim(config('brand.website_url'), '/').(parse_url($url, PHP_URL_PATH) ?: '/');
    }
}
