<?php

namespace App\Domain\PlatformAccess\Services;

use App\Domain\PlatformAccess\Enums\MembershipStatus;
use App\Domain\PlatformAccess\Enums\PermissionName;
use App\Domain\PlatformAccess\Models\Membership;
use App\Models\User;
use App\Support\Audit\AuditWriter;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class MembershipRevoker
{
    public function __construct(
        private readonly AuditWriter $audit,
        private readonly MembershipAccessManager $access,
    ) {}

    public function revoke(Membership $membership, User $actor, string $reason): Membership
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
            throw new AuthorizationException('The actor may not revoke this Membership.');
        }

        return DB::transaction(function () use ($membership, $actor, $reason): Membership {
            $membership = Membership::query()->with(['business', 'user'])->lockForUpdate()->findOrFail($membership->getKey());

            if ($membership->status === MembershipStatus::Revoked) {
                return $membership;
            }

            $before = ['status' => $membership->status->value, 'revoked_at' => null];
            $membership->forceFill([
                'status' => MembershipStatus::Revoked,
                'revoked_at' => now(),
                'revoked_by_user_id' => $actor->getKey(),
                'revocation_reason' => $reason,
            ])->save();

            if (Schema::hasTable('sessions')) {
                DB::table('sessions')->where('user_id', $membership->user_id)->delete();
            }

            if (Schema::hasTable('personal_access_tokens')) {
                DB::table('personal_access_tokens')
                    ->where('tokenable_type', User::class)
                    ->where('tokenable_id', $membership->user_id)
                    ->where(function ($query) use ($membership): void {
                        $query->where('membership_id', $membership->getKey())
                            ->orWhere('business_id', $membership->business_id)
                            ->orWhereNull('membership_id');
                    })
                    ->delete();
            }

            $membership->user->forceFill(['remember_token' => Str::random(60)])->saveQuietly();

            $this->audit->write(
                action: 'membership.access.revoked',
                business: $membership->business,
                actor: $actor,
                target: $membership,
                reason: $reason,
                before: $before,
                after: ['status' => MembershipStatus::Revoked->value, 'revoked_at' => $membership->revoked_at?->toIso8601String()],
            );

            return $membership;
        });
    }
}
