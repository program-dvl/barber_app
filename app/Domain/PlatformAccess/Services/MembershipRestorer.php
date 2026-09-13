<?php

namespace App\Domain\PlatformAccess\Services;

use App\Domain\PlatformAccess\Enums\MembershipStatus;
use App\Domain\PlatformAccess\Enums\PermissionName;
use App\Domain\PlatformAccess\Models\Membership;
use App\Models\User;
use App\Support\Audit\AuditWriter;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MembershipRestorer
{
    public function __construct(
        private readonly AuditWriter $audit,
        private readonly MembershipAccessManager $access,
    ) {}

    public function restore(Membership $membership, User $actor, string $reason): Membership
    {
        $actorMembership = $actor->memberships()
            ->active()
            ->with('business')
            ->where('business_id', $membership->business_id)
            ->first();

        if (! $actorMembership
            || (int) $membership->user_id === (int) $actor->getKey()
            || ! $this->access->allows($actorMembership, PermissionName::StaffManage)
        ) {
            throw new AuthorizationException('The actor may not restore this Membership.');
        }

        return DB::transaction(function () use ($membership, $actor, $reason): Membership {
            $membership = Membership::query()->with('business')->lockForUpdate()->findOrFail($membership->getKey());
            if ($membership->status !== MembershipStatus::Revoked) {
                throw ValidationException::withMessages(['membership' => 'Only revoked workspace access can be restored.']);
            }

            $before = ['status' => $membership->status->value, 'revoked_at' => $membership->revoked_at?->toIso8601String()];
            $membership->forceFill([
                'status' => MembershipStatus::Active,
                'suspended_at' => null,
                'revoked_at' => null,
                'revoked_by_user_id' => null,
                'revocation_reason' => null,
            ])->save();
            $this->audit->write(
                action: 'membership.access.restored',
                business: $membership->business,
                actor: $actor,
                target: $membership,
                reason: $reason,
                before: $before,
                after: ['status' => MembershipStatus::Active->value],
            );

            return $membership;
        });
    }
}
