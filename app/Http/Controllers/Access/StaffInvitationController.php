<?php

namespace App\Http\Controllers\Access;

use App\Actions\Fortify\PasswordValidationRules;
use App\Domain\PlatformAccess\Actions\AcceptStaffInvitation;
use App\Domain\PlatformAccess\Actions\IssueStaffInvitation;
use App\Domain\PlatformAccess\Actions\RevokeStaffInvitation;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\BusinessRole;
use App\Domain\PlatformAccess\Models\StaffInvitation;
use App\Domain\PlatformAccess\Models\StaffProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Access\StoreStaffInvitationRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Laravel\Jetstream\Jetstream;
use Symfony\Component\HttpFoundation\Response;

class StaffInvitationController extends Controller
{
    use PasswordValidationRules;

    public function show(string $token, Request $request): InertiaResponse
    {
        $invitation = StaffInvitation::query()
            ->with(['business', 'role', 'locations', 'staffProfile'])
            ->where('token_hash', hash('sha256', $token))
            ->first();

        if (! $invitation?->isPending()) {
            throw ValidationException::withMessages(['invitation' => 'This invitation is invalid, expired, or has already been used.']);
        }

        $existingAccount = User::query()->whereRaw('lower(email) = ?', [$invitation->email])->exists();
        $identityMatches = ! $request->user() || strcasecmp($invitation->email, $request->user()->email) === 0;
        if (! $request->user() && $existingAccount) {
            $request->session()->put('url.intended', route('staff-invitations.show', $token));
        }

        return Inertia::render('Access/StaffInvitation', [
            'businessName' => $invitation->business->name,
            'personName' => $invitation->staffProfile?->display_name,
            'email' => $invitation->email,
            'role' => str($invitation->role->name)->replace('_', ' ')->headline()->toString(),
            'locations' => $invitation->locations->pluck('name')->values(),
            'expiresAt' => $invitation->expires_at->toIso8601String(),
            'acceptUrl' => route('staff-invitations.accept', $token),
            'registerUrl' => route('staff-invitations.register', $token),
            'loginUrl' => route('login'),
            'authenticated' => (bool) $request->user(),
            'identityMatches' => $identityMatches,
            'existingAccount' => $existingAccount,
            'hasTerms' => Jetstream::hasTermsAndPrivacyPolicyFeature(),
        ]);
    }

    public function register(string $token, Request $request, AcceptStaffInvitation $action): RedirectResponse
    {
        $invitation = StaffInvitation::query()
            ->where('token_hash', hash('sha256', $token))
            ->first();
        if (! $invitation?->isPending()) {
            throw ValidationException::withMessages(['invitation' => 'This invitation is invalid, expired, or has already been used.']);
        }
        if (User::query()->whereRaw('lower(email) = ?', [$invitation->email])->exists()) {
            throw ValidationException::withMessages(['email' => 'An account already exists for this email. Sign in to accept the invitation.']);
        }

        $data = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'password' => $this->passwordRules(),
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature() ? ['accepted', 'required'] : [],
        ])->validate();

        [$user, $membership] = DB::transaction(function () use ($invitation, $data, $action, $token): array {
            $user = User::query()->create([
                'name' => trim($data['name']),
                'email' => $invitation->email,
                'password' => Hash::make($data['password']),
            ]);
            // Possession of the single-use invitation sent to this exact email
            // verifies the address without introducing another dead-end step.
            $user->forceFill(['email_verified_at' => now()])->saveQuietly();
            $membership = $action->handle($token, $user);

            return [$user, $membership];
        }, 3);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('business.dashboard', $membership->business);
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

    public function destroy(Business $business, StaffInvitation $invitation, Request $request, RevokeStaffInvitation $action): RedirectResponse
    {
        $this->authorize('revoke', $invitation);
        $action->handle(
            invitation: $invitation,
            actor: $request->attributes->get('tenant_membership'),
            reason: $request->string('reason', 'Invitation revoked by an authorized staff member.')->toString(),
        );

        return back()->with('status', 'Invitation cancelled. The link can no longer be used.');
    }

    public function accept(string $token, Request $request, AcceptStaffInvitation $action): RedirectResponse
    {
        $membership = $action->handle($token, $request->user());

        return redirect()->route('business.dashboard', $membership->business);
    }
}
