<?php

namespace App\Domain\PlatformAccess\Policies;

use App\Domain\PlatformAccess\Enums\PermissionName;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\StaffInvitation;
use App\Domain\PlatformAccess\Policies\Concerns\AuthorizesBusinessPermission;
use App\Models\User;

class StaffInvitationPolicy
{
    use AuthorizesBusinessPermission;

    public function create(User $user, Business $business): bool
    {
        return $this->allows($user, $business->getKey(), PermissionName::StaffManage);
    }

    public function revoke(User $user, StaffInvitation $invitation): bool
    {
        return $this->allows($user, $invitation->business_id, PermissionName::StaffManage);
    }
}
