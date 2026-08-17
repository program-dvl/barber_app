<?php

namespace App\Support\Jobs;

use App\Domain\PlatformAccess\Models\Business;
use App\Support\Tenancy\TenantContext;
use Closure;
use RuntimeException;

class ResolveTenantForJob
{
    public function __construct(private readonly TenantContext $context) {}

    public function handle(TenantAwareJob $job, Closure $next): mixed
    {
        $business = Business::query()->find($job->tenantBusinessId());

        if (! $business?->isActive()) {
            throw new RuntimeException('Tenant-aware job cannot run for a missing or inactive Business.');
        }

        return $this->context->run($business, null, fn () => $next($job));
    }
}
