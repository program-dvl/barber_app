<?php

namespace App\Domain\PlatformAccess\Actions;

use App\Domain\PlatformAccess\Enums\MembershipStatus;
use App\Domain\PlatformAccess\Models\Membership;
use App\Domain\PlatformAccess\Models\StaffInvitation;
use App\Models\User;
use App\Support\Audit\AuditWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

class AcceptStaffInvitation
{
    public function __construct(private readonly AuditWriter $audit) {}

    public function handle(string $plainTextToken, User $user): Membership
    {
        return DB::transaction(function () use ($plainTextToken, $user): Membership {
            $invitation = StaffInvitation::query()
                ->with(['business', 'role', 'locations', 'staffProfile'])
                ->where('token_hash', hash('sha256', $plainTextToken))
                ->lockForUpdate()
                ->first();

            if (! $invitation?->isPending() || Str::lower($user->email) !== $invitation->email) {
                throw ValidationException::withMessages(['invitation' => 'This invitation is invalid, expired, revoked, or belongs to another identity.']);
            }

            if ((int) $invitation->role->business_id !== (int) $invitation->business_id) {
                throw ValidationException::withMessages(['invitation' => 'The invitation role does not belong to this Business.']);
            }

            $membership = Membership::query()->firstOrCreate(
                ['business_id' => $invitation->business_id, 'user_id' => $user->getKey()],
                [
                    'status' => MembershipStatus::Active,
                    'joined_at' => now(),
                ]
            );

            if (! $membership->wasRecentlyCreated && ! $membership->isActive()) {
                throw ValidationException::withMessages(['invitation' => 'Existing revoked or suspended access must be restored explicitly.']);
            }

            $registrar = app(PermissionRegistrar::class);
            $previousBusinessId = $registrar->getPermissionsTeamId();
            setPermissionsTeamId($invitation->business_id);

            try {
                $membership->syncRoles([$invitation->role]);
            } finally {
                setPermissionsTeamId($previousBusinessId);
            }
            $membership->locations()->syncWithPivotValues(
                $invitation->locations->modelKeys(),
                ['business_id' => $invitation->business_id]
            );

            if ($invitation->staffProfile) {
                $invitation->staffProfile->forceFill([
                    'membership_id' => $membership->getKey(),
                    'user_id' => $user->getKey(),
                    'email' => $user->email,
                ])->save();
                $invitation->staffProfile->locations()->syncWithPivotValues(
                    $invitation->locations->modelKeys(),
                    ['business_id' => $invitation->business_id],
                    false
                );
            }

            $invitation->forceFill([
                'accepted_at' => now(),
                'accepted_by_user_id' => $user->getKey(),
            ])->save();

            $this->audit->write(
                action: 'staff.invitation.accepted',
                business: $invitation->business,
                actor: $user,
                target: $invitation,
                after: ['membership_id' => $membership->getKey(), 'role' => $invitation->role->name],
            );

            return $membership;
        });
    }
}
