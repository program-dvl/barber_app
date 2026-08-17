<?php

namespace App\Domain\PlatformAccess\Policies;

use App\Domain\PlatformAccess\Enums\PermissionName;
use App\Domain\PlatformAccess\Models\AuditEvent;
use App\Domain\PlatformAccess\Policies\Concerns\AuthorizesBusinessPermission;
use App\Models\User;

class AuditEventPolicy
{
    use AuthorizesBusinessPermission;

    public function view(User $user, AuditEvent $event): bool
    {
        return $event->business_id !== null
            && $this->allows($user, $event->business_id, PermissionName::AuditView);
    }

    public function update(): bool
    {
        return false;
    }

    public function delete(): bool
    {
        return false;
    }
}
