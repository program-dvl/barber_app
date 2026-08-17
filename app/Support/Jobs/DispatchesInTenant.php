<?php

namespace App\Support\Jobs;

use App\Domain\PlatformAccess\Models\Business;
use Illuminate\Support\Str;

trait DispatchesInTenant
{
    public int $businessId;

    public string $tenantCorrelationId;

    public function initializeTenantPayload(Business|int $business, ?string $correlationId = null): void
    {
        $this->businessId = $business instanceof Business ? (int) $business->getKey() : $business;
        $this->tenantCorrelationId = $correlationId ?? (string) Str::uuid();
    }

    public function tenantBusinessId(): int
    {
        return $this->businessId;
    }

    public function correlationId(): string
    {
        return $this->tenantCorrelationId;
    }

    public function middleware(): array
    {
        return [app(ResolveTenantForJob::class)];
    }
}
