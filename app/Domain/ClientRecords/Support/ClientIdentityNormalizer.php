<?php

namespace App\Domain\ClientRecords\Support;

use Illuminate\Support\Str;

final class ClientIdentityNormalizer
{
    public static function name(?string $value): string
    {
        $value = Str::lower(trim((string) $value));

        return preg_replace('/[^\pL\pN]+/u', '', $value) ?: '';
    }

    public static function email(?string $value): ?string
    {
        $value = Str::lower(trim((string) $value));

        return $value === '' ? null : $value;
    }

    public static function mobile(?string $value): ?string
    {
        $value = preg_replace('/\D+/', '', (string) $value) ?: '';

        return $value === '' ? null : $value;
    }
}
