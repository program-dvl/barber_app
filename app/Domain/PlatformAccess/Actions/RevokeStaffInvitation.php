<?php

namespace App\Domain\PlatformAccess\Actions;

use App\Domain\PlatformAccess\Enums\PermissionName;
use App\Domain\PlatformAccess\Models\Membership;
use App\Domain\PlatformAccess\Models\StaffInvitation;
use App\Domain\PlatformAccess\Services\MembershipAccessManager;
use App\Support\Audit\AuditWriter;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class RevokeStaffInvitation
{
    public function __construct(
        private readonly AuditWriter $audit,
        private readonly MembershipAccessManager $access,
    ) {}

    public function handle(StaffInvitation $invitation, Membership $actor, string $reason): StaffInvitation
    {
        if (! $this->access->allows($actor, PermissionName::StaffManage)) {
            throw new AuthorizationException('The actor may not manage staff access.');
        }

        if ((int) $invitation->business_id !== (int) $actor->business_id || ! $invitation->isPending()) {
            throw ValidationException::withMessages(['invitation' => 'Only a pending invitation in the active Business may be revoked.']);
        }

        $invitation->forceFill([
            'revoked_at' => now(),
            'revoked_by_user_id' => $actor->user_id,
        ])->save();

        $this->audit->write(
            action: 'staff.invitation.revoked',
            business: $actor->business,
            actor: $actor->user,
            target: $invitation,
            reason: $reason,
            after: ['revoked_at' => $invitation->revoked_at->toIso8601String()],
        );

        return $invitation;
    }
}
