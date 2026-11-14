<?php

namespace App\Domain\Marketplace\Launch;

class LaunchArea
{
    public static function normalize(string $area): string
    {
        return preg_replace('/\s+/u', ' ', trim($area));
    }

    public static function key(string $area): string
    {
        return hash('sha256', mb_strtolower(self::normalize($area)));
    }
}
