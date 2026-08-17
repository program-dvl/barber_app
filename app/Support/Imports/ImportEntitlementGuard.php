<?php

namespace App\Support\Imports;

use App\Domain\Billing\Services\EntitlementEvaluator;
use App\Domain\PlatformAccess\Models\Business;

class ImportEntitlementGuard
{
    public function __construct(private readonly EntitlementEvaluator $entitlements) {}

    public function authorize(Business $business, string $entitlementKey, int $rowsThatIncreaseUsage): void
    {
        $this->entitlements->authorize($business, $entitlementKey, 'import', $rowsThatIncreaseUsage);
    }
}
