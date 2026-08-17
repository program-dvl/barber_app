<?php

namespace App\Http\Controllers\Access;

use App\Domain\PlatformAccess\Actions\AcceptStaffInvitation;
use App\Domain\PlatformAccess\Actions\IssueStaffInvitation;
use App\Domain\PlatformAccess\Actions\RevokeStaffInvitation;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\BusinessRole;
use App\Domain\PlatformAccess\Models\StaffInvitation;
use App\Domain\PlatformAccess\Models\StaffProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Access\StoreStaffInvitationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class StaffInvitationController extends Controller
{
    public function show(string $token, Request $request): InertiaResponse
    {
        $invitation = StaffInvitation::query()
            ->with('business')
            ->where('token_hash', hash('sha256', $token))
            ->first();

        if (! $invitation?->isPending() || strcasecmp($invitation->email, $request->user()->email) !== 0) {
            throw ValidationException::withMessages(['invitation' => 'This invitation is invalid, expired, revoked, or belongs to another identity.']);
        }

        return Inertia::render('Access/StaffInvitation', [
            'businessName' => $invitation->business->name,
            'expiresAt' => $invitation->expires_at->toIso8601String(),
            'acceptUrl' => route('staff-invitations.accept', $token),
        ]);
    }

    public function store(Business $business, StoreStaffInvitationRequest $request, IssueStaffInvitation $action): JsonResponse
    {
        $this->authorize('create', [StaffInvitation::class, $business]);
        $membership = $request->attributes->get('tenant_membership');
        $role = BusinessRole::query()
            ->where('business_id', $business->getKey())
            ->whereKey($request->integer('role_id'))
            ->firstOrFail();
        $staffProfile = $request->filled('staff_profile_id')
            ? StaffProfile::query()->forBusiness($business)->whereKey($request->integer('staff_profile_id'))->firstOrFail()
            : null;

        $issued = $action->handle(
            inviter: $membership,
            email: $request->string('email')->toString(),
            role: $role,
            locationIds: $request->input('location_ids', []),
            staffProfile: $staffProfile,
            expiresInDays: $request->integer('expires_in_days', 7),
        );

        return response()->json([
            'invitation' => [
                'public_id' => $issued->invitation->public_id,
                'email' => $issued->invitation->email,
                'expires_at' => $issued->invitation->expires_at->toIso8601String(),
            ],
        ], Response::HTTP_CREATED);
    }

    public function destroy(Business $business, StaffInvitation $invitation, Request $request, RevokeStaffInvitation $action): Response
    {
        $this->authorize('revoke', $invitation);
        $action->handle(
            invitation: $invitation,
            actor: $request->attributes->get('tenant_membership'),
            reason: $request->string('reason', 'Invitation revoked by an authorized staff member.')->toString(),
        );

        return response()->noContent();
    }

    public function accept(string $token, Request $request, AcceptStaffInvitation $action): RedirectResponse
    {
        $action->handle($token, $request->user());

        return redirect()->route('dashboard');
    }
}
