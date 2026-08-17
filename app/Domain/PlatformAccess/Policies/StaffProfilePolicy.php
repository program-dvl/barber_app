<?php

namespace App\Domain\PlatformAccess\Policies;

use App\Domain\PlatformAccess\Enums\PermissionName;
use App\Domain\PlatformAccess\Models\StaffProfile;
use App\Domain\PlatformAccess\Policies\Concerns\AuthorizesBusinessPermission;
use App\Models\User;

class StaffProfilePolicy
{
    use AuthorizesBusinessPermission;

    public function view(User $user, StaffProfile $staffProfile): bool
    {
        return $this->allows($user, $staffProfile->business_id, PermissionName::StaffManage)
            || (int) $staffProfile->user_id === (int) $user->getKey();
    }

    public function update(User $user, StaffProfile $staffProfile): bool
    {
        return $this->allows($user, $staffProfile->business_id, PermissionName::StaffManage);
    }
}
