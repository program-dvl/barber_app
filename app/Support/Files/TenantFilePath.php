<?php

namespace App\Support\Files;

use App\Domain\PlatformAccess\Models\Business;
use InvalidArgumentException;

final class TenantFilePath
{
    public static function private(Business $business, string $path): string
    {
        $path = ltrim($path, '/');

        if ($path === '' || str_contains($path, '..')) {
            throw new InvalidArgumentException('Tenant file paths must be relative and may not traverse directories.');
        }

        return 'businesses/'.$business->getKey().'/private/'.$path;
    }
}
