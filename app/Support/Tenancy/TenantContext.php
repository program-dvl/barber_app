<?php

namespace App\Support\Tenancy;

use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\Membership;
use Closure;
use LogicException;

class TenantContext
{
    private ?Business $business = null;

    private ?Membership $membership = null;

    public function activate(Business $business, ?Membership $membership = null): void
    {
        if ($membership && (int) $membership->business_id !== (int) $business->getKey()) {
            throw new LogicException('Membership and Business tenant context do not match.');
        }

        $this->business = $business;
        $this->membership = $membership;
        setPermissionsTeamId($business->getKey());
    }

    public function run(Business $business, ?Membership $membership, Closure $callback): mixed
    {
        $previousBusiness = $this->business;
        $previousMembership = $this->membership;

        $this->activate($business, $membership);

        try {
            return $callback();
        } finally {
            if ($previousBusiness) {
                $this->activate($previousBusiness, $previousMembership);
            } else {
                $this->clear();
            }
        }
    }

    public function clear(): void
    {
        $this->business = null;
        $this->membership = null;
        setPermissionsTeamId(null);
    }

    public function hasBusiness(): bool
    {
        return $this->business !== null;
    }

    public function business(): Business
    {
        return $this->business ?? throw new LogicException('No tenant context is active.');
    }

    public function membership(): ?Membership
    {
        return $this->membership;
    }
}
