<?php

namespace App\Domain\PlatformAccess\Services;

use App\Domain\PlatformAccess\Enums\PlatformRole;
use App\Domain\PlatformAccess\Models\PlatformRoleAssignment;
use App\Models\User;
use App\Support\Audit\AuditWriter;
use Carbon\CarbonInterface;

class PlatformRoleManager
{
    public function __construct(private readonly AuditWriter $audit) {}

    public function grant(User $user, PlatformRole $role, User $actor, string $reason, ?CarbonInterface $expiresAt = null): PlatformRoleAssignment
    {
        $assignment = PlatformRoleAssignment::query()->create([
            'user_id' => $user->getKey(),
            'role' => $role,
            'granted_by_user_id' => $actor->getKey(),
            'reason' => $reason,
            'expires_at' => $expiresAt,
        ]);

        $this->audit->write(
            action: 'platform.role.granted',
            actor: $actor,
            target: $assignment,
            reason: $reason,
            after: ['user_id' => $user->getKey(), 'role' => $role->value, 'expires_at' => $expiresAt?->toIso8601String()],
            source: 'platform',
        );

        return $assignment;
    }
}
