<?php

namespace App\Domain\PlatformAccess\Services;

use App\Domain\PlatformAccess\Enums\PlatformRole;
use App\Domain\PlatformAccess\Models\PlatformRoleAssignment;
use App\Models\User;
use App\Support\Audit\AuditWriter;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class PlatformRoleManager
{
    public function __construct(private readonly AuditWriter $audit) {}

    public function revoke(PlatformRoleAssignment $assignment, User $actor, string $reason): void
    {
        abort_unless($actor->hasVerifiedEmail() && $actor->hasPlatformRole(PlatformRole::Administrator), 403);
        abort_if($assignment->user_id === $actor->id, 422, 'Ask another administrator to revoke your platform role.');
        DB::transaction(function () use ($assignment, $actor, $reason): void {
            $assignment = PlatformRoleAssignment::query()->lockForUpdate()->findOrFail($assignment->id);
            if ($assignment->revoked_at) {
                return;
            }
            $assignment->update(['revoked_at' => now(), 'revoked_by_user_id' => $actor->id]);
            $this->audit->write('platform.role.revoked', actor: $actor, target: $assignment, reason: $reason,
                after: ['user_id' => $assignment->user_id, 'role' => $assignment->role->value], source: 'admin');
        });
    }

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
