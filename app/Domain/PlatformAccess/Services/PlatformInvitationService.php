<?php

namespace App\Domain\PlatformAccess\Services;

use App\Domain\PlatformAccess\Models\StaffInvitation;
use App\Domain\PlatformAccess\Notifications\StaffInvitationNotification;
use App\Models\User;
use App\Support\Audit\AuditWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class PlatformInvitationService
{
    public function __construct(private readonly AuditWriter $audit) {}

    public function resend(StaffInvitation $invitation, User $operator, string $reason): StaffInvitation
    {
        abort_unless($invitation->accepted_at === null, 422, 'Accepted invitations cannot be resent.');
        $issued = DB::transaction(function () use ($invitation, $operator, $reason): array {
            $locked = StaffInvitation::query()->lockForUpdate()->with('locations')->findOrFail($invitation->id);
            $locked->update(['revoked_at' => now(), 'revoked_by_user_id' => $operator->id]);
            $token = Str::random(64);
            $replacement = StaffInvitation::query()->create([
                'business_id' => $locked->business_id, 'staff_profile_id' => $locked->staff_profile_id,
                'invited_by_membership_id' => $locked->invited_by_membership_id, 'role_id' => $locked->role_id,
                'email' => $locked->email, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays(7),
            ]);
            $replacement->locations()->syncWithPivotValues($locked->locations->pluck('id')->all(), ['business_id' => $locked->business_id]);
            $this->audit->write('platform.staff_invitation.resent', $locked->business, $operator, $replacement, $reason, ['replaced_invitation_id' => $locked->public_id], ['expires_at' => $replacement->expires_at->toIso8601String()], source: 'platform');

            return [$replacement, $token];
        });
        Notification::route('mail', $issued[0]->email)->notify(new StaffInvitationNotification($issued[0]->business->name, $issued[1], $issued[0]->expires_at));

        return $issued[0];
    }
}
