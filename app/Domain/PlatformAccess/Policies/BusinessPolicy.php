<?php

namespace App\Domain\PlatformAccess\Policies;

use App\Domain\PlatformAccess\Models\Business;
use App\Models\User;
use App\Support\Tenancy\TenantContext;

class BusinessPolicy
{
    public function view(User $user, Business $business): bool
    {
        $membership = app(TenantContext::class)->membership();

        return (int) $membership?->user_id === (int) $user->getKey()
            && (int) $membership?->business_id === (int) $business->getKey()
            && $membership->isActive();
    }
}
