<?php

namespace App\Support\Jobs;

use App\Domain\Billing\Services\EntitlementEvaluator;
use App\Domain\PlatformAccess\Models\Business;

class EnsureJobEntitlement
{
    public function __construct(private readonly EntitlementEvaluator $entitlements) {}

    public function handle(EntitlementAwareJob $job, callable $next): mixed
    {
        $business = Business::query()->findOrFail($job->businessId());
        $this->entitlements->authorize($business, $job->entitlementKey(), $job->entitlementOperation(), $job->entitlementIncrease());

        return $next($job);
    }
}
