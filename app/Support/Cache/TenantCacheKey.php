<?php

namespace App\Support\Cache;

use App\Domain\PlatformAccess\Models\Business;
use InvalidArgumentException;

final class TenantCacheKey
{
    public static function make(Business|int $business, string ...$segments): string
    {
        $businessId = $business instanceof Business ? $business->getKey() : $business;
        $segments = array_map(fn (string $segment) => trim($segment, ': '), $segments);

        if (! $businessId || in_array('', $segments, true)) {
            throw new InvalidArgumentException('Tenant cache keys require a Business ID and non-empty segments.');
        }

        return implode(':', ['business', $businessId, ...$segments]);
    }
}
