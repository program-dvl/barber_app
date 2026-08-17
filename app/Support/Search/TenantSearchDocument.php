<?php

namespace App\Support\Search;

use App\Domain\PlatformAccess\Models\Business;

final class TenantSearchDocument
{
    /** @param array<string, mixed> $attributes */
    public static function make(Business $business, string $type, string|int $id, array $attributes): array
    {
        return [
            'document_id' => implode(':', [$business->getKey(), $type, $id]),
            'business_id' => $business->getKey(),
            'type' => $type,
            'record_id' => (string) $id,
            'attributes' => $attributes,
        ];
    }
}
