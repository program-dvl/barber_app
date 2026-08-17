<?php

namespace App\Support\Jobs;

interface TenantAwareJob
{
    public function tenantBusinessId(): int;

    public function correlationId(): string;
}
