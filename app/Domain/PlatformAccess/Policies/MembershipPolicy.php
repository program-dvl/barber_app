<?php

namespace App\Domain\PlatformAccess\Policies;

use App\Domain\PlatformAccess\Enums\PermissionName;
use App\Domain\PlatformAccess\Models\Membership;
use App\Domain\PlatformAccess\Policies\Concerns\AuthorizesBusinessPermission;
use App\Models\User;

class MembershipPolicy
{
    use AuthorizesBusinessPermission;

    public function view(User $user, Membership $membership): bool
    {
        return $this->allows($user, $membership->business_id, PermissionName::StaffManage)
            || (int) $membership->user_id === (int) $user->getKey();
    }

    public function update(User $user, Membership $membership): bool
    {
        return $this->allows($user, $membership->business_id, PermissionName::StaffManage);
    }

    public function revoke(User $user, Membership $membership): bool
    {
        return (int) $membership->user_id !== (int) $user->getKey()
            && $this->allows($user, $membership->business_id, PermissionName::StaffManage);
    }
}
