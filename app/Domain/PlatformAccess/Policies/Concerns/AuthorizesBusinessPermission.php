<?php

namespace App\Domain\PlatformAccess\Policies\Concerns;

use App\Domain\PlatformAccess\Enums\PermissionName;
use App\Domain\PlatformAccess\Enums\StarterRole;
use App\Domain\PlatformAccess\Models\Location;
use App\Models\User;
use App\Support\Tenancy\TenantContext;

trait AuthorizesBusinessPermission
{
    private function allows(User $user, int $businessId, PermissionName $permission): bool
    {
        $context = app(TenantContext::class);
        $membership = $context->membership();

        return $context->hasBusiness()
            && (int) $context->business()->getKey() === $businessId
            && (int) $membership?->user_id === (int) $user->getKey()
            && $membership->isActive()
            && $membership->hasPermissionTo($permission->value, 'web');
    }

    private function allowsAtLocation(User $user, Location $location, PermissionName $permission): bool
    {
        if (! $this->allows($user, $location->business_id, $permission)) {
            return false;
        }

        $membership = app(TenantContext::class)->membership();

        return $membership->hasRole(StarterRole::Owner->value, 'web')
            || $membership->locations()->whereKey($location->getKey())->exists();
    }
}
