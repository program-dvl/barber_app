<?php

namespace App\Domain\SchedulingOperations\Policies;

use App\Domain\PlatformAccess\Enums\PermissionName;
use App\Domain\SchedulingOperations\Models\WalkInEntry;
use App\Models\User;
use App\Support\Tenancy\TenantContext;

class WalkInEntryPolicy
{
    public function manage(User $user, WalkInEntry $entry): bool
    {
        $context = app(TenantContext::class);
        $membership = $context->membership();

        return $context->hasBusiness()
            && $context->business()->id === $entry->business_id
            && $membership?->user_id === $user->id
            && $membership->isActive()
            && $membership->hasPermissionTo(PermissionName::WalkInsManage->value, 'web')
            && ($membership->hasRole('owner', 'web') || $membership->locations()->whereKey($entry->location_id)->exists());
    }
}
