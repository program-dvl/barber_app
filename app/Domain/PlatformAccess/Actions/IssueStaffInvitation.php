<?php

namespace App\Domain\PlatformAccess\Actions;

use App\Domain\PlatformAccess\Enums\PermissionName;
use App\Domain\PlatformAccess\Models\BusinessRole;
use App\Domain\PlatformAccess\Models\Location;
use App\Domain\PlatformAccess\Models\Membership;
use App\Domain\PlatformAccess\Models\StaffInvitation;
use App\Domain\PlatformAccess\Models\StaffProfile;
use App\Domain\PlatformAccess\Notifications\StaffInvitationNotification;
use App\Domain\PlatformAccess\Services\MembershipAccessManager;
use App\Support\Audit\AuditWriter;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class IssueStaffInvitation
{
    public function __construct(
        private readonly AuditWriter $audit,
        private readonly MembershipAccessManager $access,
    ) {}

    /** @param list<int> $locationIds */
    public function handle(
        Membership $inviter,
        string $email,
        BusinessRole $role,
        array $locationIds = [],
        ?StaffProfile $staffProfile = null,
        int $expiresInDays = 7,
    ): IssuedStaffInvitation {
        $email = Str::lower(trim($email));

        if (! $this->access->allows($inviter, PermissionName::StaffManage)) {
            throw new AuthorizationException('The inviter may not manage staff access.');
        }

        if ((int) $role->business_id !== (int) $inviter->business_id
            || ($staffProfile && (int) $staffProfile->business_id !== (int) $inviter->business_id)
        ) {
            throw ValidationException::withMessages(['tenant' => 'Invitation inputs must belong to the active Business.']);
        }

        $validLocationIds = Location::query()
            ->forBusiness($inviter->business_id)
            ->whereKey($locationIds)
            ->pluck('id')
            ->all();

        if (count($validLocationIds) !== count(array_unique($locationIds))) {
            throw ValidationException::withMessages(['locations' => 'Every invitation location must belong to the active Business.']);
        }

        $issued = DB::transaction(function () use ($inviter, $email, $role, $staffProfile, $expiresInDays, $validLocationIds): IssuedStaffInvitation {
            StaffInvitation::query()
                ->forBusiness($inviter->business_id)
                ->where('email', $email)
                ->whereNull('accepted_at')
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            $plainTextToken = Str::random(64);
            $invitation = StaffInvitation::query()->create([
                'business_id' => $inviter->business_id,
                'staff_profile_id' => $staffProfile?->getKey(),
                'invited_by_membership_id' => $inviter->getKey(),
                'role_id' => $role->getKey(),
                'email' => $email,
                'token_hash' => hash('sha256', $plainTextToken),
                'expires_at' => now()->addDays(max(1, min($expiresInDays, 30))),
            ]);
            $invitation->locations()->syncWithPivotValues($validLocationIds, ['business_id' => $inviter->business_id]);

            $this->audit->write(
                action: 'staff.invitation.issued',
                business: $inviter->business,
                actor: $inviter->user,
                target: $invitation,
                after: [
                    'email' => $email,
                    'role' => $role->name,
                    'location_ids' => $validLocationIds,
                    'expires_at' => $invitation->expires_at->toIso8601String(),
                ],
            );

            return new IssuedStaffInvitation($invitation, $plainTextToken);
        });

        Notification::route('mail', $email)->notify(new StaffInvitationNotification(
            businessName: $inviter->business->name,
            plainTextToken: $issued->plainTextToken,
            expiresAt: $issued->invitation->expires_at,
        ));

        return $issued;
    }
}
