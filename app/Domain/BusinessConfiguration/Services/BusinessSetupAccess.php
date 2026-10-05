<?php

namespace App\Domain\BusinessConfiguration\Services;

use App\Domain\PlatformAccess\Enums\PermissionName;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\Membership;

class BusinessSetupAccess
{
    public function allows(Business $business, ?Membership $membership): bool
    {
        return $membership && (int) $membership->business_id === (int) $business->id
            && $membership->hasPermissionTo(PermissionName::SettingsManage->value, 'web')
            && ($membership->hasRole('owner', 'web') || $business->locations()->pluck('id')
                ->diff($membership->locations()->pluck('locations.id'))->isEmpty());
    }
}
