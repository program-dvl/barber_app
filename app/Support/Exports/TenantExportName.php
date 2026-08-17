<?php

namespace App\Support\Exports;

use App\Domain\PlatformAccess\Models\Business;
use Illuminate\Support\Str;

final class TenantExportName
{
    public static function make(Business $business, string $label, string $extension = 'csv'): string
    {
        return sprintf(
            'business-%s-%s-%s.%s',
            $business->public_id,
            Str::slug($label),
            now()->utc()->format('Ymd-His'),
            ltrim($extension, '.')
        );
    }
}
