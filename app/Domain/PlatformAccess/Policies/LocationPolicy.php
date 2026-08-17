<?php

namespace App\Domain\PlatformAccess\Policies;

use App\Domain\PlatformAccess\Enums\PermissionName;
use App\Domain\PlatformAccess\Models\Location;
use App\Domain\PlatformAccess\Policies\Concerns\AuthorizesBusinessPermission;
use App\Models\User;

class LocationPolicy
{
    use AuthorizesBusinessPermission;

    public function view(User $user, Location $location): bool
    {
        return $this->allowsAtLocation($user, $location, PermissionName::CalendarViewAll)
            || $this->allowsAtLocation($user, $location, PermissionName::CalendarViewOwn);
    }

    public function update(User $user, Location $location): bool
    {
        return $this->allowsAtLocation($user, $location, PermissionName::SettingsManage);
    }
}
