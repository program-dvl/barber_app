<?php

use App\Domain\PlatformAccess\Actions\IssueStaffInvitation;
use App\Domain\PlatformAccess\Enums\MembershipStatus;
use App\Domain\PlatformAccess\Enums\PermissionName;
use App\Domain\PlatformAccess\Enums\StarterRole;
use App\Domain\PlatformAccess\Models\AuditEvent;
use App\Domain\PlatformAccess\Models\BusinessRole;
use App\Domain\PlatformAccess\Models\Location;
use App\Domain\PlatformAccess\Models\StaffInvitation;
use App\Domain\PlatformAccess\Models\StaffProfile;
use App\Domain\PlatformAccess\Notifications\StaffInvitationNotification;
use App\Domain\PlatformAccess\Services\MembershipAccessManager;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
});

it('creates a bookable team profile and secure login invitation in one owner workflow', function () {
    Notification::fake();
    [$owner, $business] = createTenantMembership(StarterRole::Owner);
    activateTestSubscription($business, 'pro');
    $location = Location::factory()->create(['business_id' => $business->id, 'name' => 'Central studio']);
    $role = BusinessRole::query()->where('business_id', $business->id)->where('name', 'receptionist')->firstOrFail();

    $this->actingAs($owner)->post(route('business.team.providers.store', $business), [
        'display_name' => 'Morgan Lee',
        'email' => 'morgan@example.test',
        'mobile' => null,
        'title' => 'Front desk coordinator',
        'online_visible' => false,
        'location' => $location->public_id,
        'service_ids' => [],
        'working_days' => [1, 2, 3, 4, 5],
        'starts_at' => '09:00',
        'ends_at' => '17:00',
        'invite_access' => true,
        'access_role_id' => $role->id,
        'custom_access' => false,
        'permission_names' => [],
    ])->assertRedirect()->assertSessionHasNoErrors();

    $profile = StaffProfile::query()->where('business_id', $business->id)->where('email', 'morgan@example.test')->firstOrFail();
    $invitation = StaffInvitation::query()->where('staff_profile_id', $profile->id)->firstOrFail();
    expect($profile->membership_id)->toBeNull()
        ->and($invitation->role_id)->toBe($role->id)
        ->and($invitation->locations()->pluck('locations.id')->all())->toBe([$location->id])
        ->and(AuditEvent::query()->where('business_id', $business->id)->where('action', 'staff.invitation.issued')->exists())->toBeTrue();
    Notification::assertSentOnDemand(StaffInvitationNotification::class);

    $this->actingAs($owner)->get(route('business.team.index', $business))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Team/Index')
            ->has('accessRoles', 5)
            ->has('accessModules', 7)
            ->has('pendingInvitations', 1)
            ->where('pendingInvitations.0.email', 'morgan@example.test')
            ->where('seatAllowance.used', 1));

    $savedWithoutLogin = StaffProfile::factory()->create([
        'business_id' => $business->id,
        'email' => 'later@example.test',
        'display_name' => 'Invite Later',
    ]);
    $savedWithoutLogin->locations()->syncWithPivotValues([$location->id], ['business_id' => $business->id]);
    $this->actingAs($owner)->post(route('business.team.staff.invite', [$business, $savedWithoutLogin]), [
        'access_role_id' => $role->id,
        'custom_access' => false,
        'permission_names' => [],
        'location_ids' => [$location->public_id],
    ])->assertRedirect()->assertSessionHasNoErrors();
    expect(StaffInvitation::query()->where('staff_profile_id', $savedWithoutLogin->id)->whereNull('revoked_at')->exists())->toBeTrue();
});

it('lets a new invitee create a password and join the correct workspace without creating an owner workspace', function () {
    Notification::fake();
    [$owner, $business, $ownerMembership] = createTenantMembership(StarterRole::Owner);
    activateTestSubscription($business, 'pro');
    $location = Location::factory()->create(['business_id' => $business->id]);
    $profile = StaffProfile::factory()->create([
        'business_id' => $business->id,
        'email' => 'new.member@example.test',
        'display_name' => 'New Member',
    ]);
    $role = BusinessRole::query()->where('business_id', $business->id)->where('name', 'barber_stylist')->firstOrFail();
    $issued = app(IssueStaffInvitation::class)->handle($ownerMembership, $profile->email, $role, [$location->id], $profile);

    $this->get(route('staff-invitations.show', $issued->plainTextToken))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Access/StaffInvitation')
            ->where('businessName', $business->name)
            ->where('email', 'new.member@example.test')
            ->where('authenticated', false)
            ->where('existingAccount', false));

    $this->post(route('staff-invitations.register', $issued->plainTextToken), [
        'name' => 'New Member',
        'password' => 'Strong-password-2026!',
        'password_confirmation' => 'Strong-password-2026!',
        'terms' => true,
    ])->assertRedirect(route('business.dashboard', $business));

    $user = User::query()->where('email', 'new.member@example.test')->firstOrFail();
    $membership = $user->memberships()->where('business_id', $business->id)->firstOrFail();
    expect(Auth::id())->toBe($user->id)
        ->and($user->hasVerifiedEmail())->toBeTrue()
        ->and($membership->status)->toBe(MembershipStatus::Active)
        ->and($profile->fresh()->membership_id)->toBe($membership->id)
        ->and(app(MembershipAccessManager::class)->allows($membership, PermissionName::CalendarViewOwn))->toBeTrue()
        ->and(StaffInvitation::query()->findOrFail($issued->invitation->id)->accepted_at)->not->toBeNull();
});

it('lets an owner tailor module access and revoke it with complete audit evidence', function () {
    [$owner, $business] = createTenantMembership(StarterRole::Owner);
    activateTestSubscription($business, 'pro');
    $location = Location::factory()->create(['business_id' => $business->id]);
    $memberUser = User::factory()->create();
    $membership = $business->memberships()->create([
        'user_id' => $memberUser->id,
        'status' => MembershipStatus::Active,
        'joined_at' => now(),
    ]);
    app(MembershipAccessManager::class)->assignStarterRole($membership, StarterRole::Receptionist, $owner, 'Initial role.');
    StaffProfile::factory()->create([
        'business_id' => $business->id,
        'membership_id' => $membership->id,
        'user_id' => $memberUser->id,
        'email' => $memberUser->email,
    ]);

    $this->actingAs($owner)->patch(route('business.team.memberships.access.update', [$business, $membership]), [
        'custom_access' => true,
        'permission_names' => [PermissionName::CalendarViewAll->value, PermissionName::AppointmentsManageAll->value, PermissionName::WalkInsManage->value],
        'location_ids' => [$location->public_id],
        'reason' => 'Front desk cover for this location.',
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect(app(MembershipAccessManager::class)->allows($membership, PermissionName::AppointmentsManageAll))->toBeTrue()
        ->and(app(MembershipAccessManager::class)->allows($membership, PermissionName::RevenueView))->toBeFalse()
        ->and($membership->locations()->pluck('locations.id')->all())->toBe([$location->id]);

    $this->actingAs($owner)->get(route('business.activity.index', $business))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Team/Activity')->where('events.total', fn (int $total) => $total >= 2));

    $this->actingAs($owner)->delete(route('business.team.memberships.access.destroy', [$business, $membership]), [
        'reason' => 'Employment ended.',
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($membership->fresh()->status)->toBe(MembershipStatus::Revoked)
        ->and(AuditEvent::query()->where('business_id', $business->id)->where('action', 'membership.access.revoked')->where('reason', 'Employment ended.')->exists())->toBeTrue();

    $this->actingAs($owner)->post(route('business.team.memberships.access.restore', [$business, $membership]), [
        'reason' => 'Returned to the team.',
    ])->assertRedirect()->assertSessionHasNoErrors();
    expect($membership->fresh()->status)->toBe(MembershipStatus::Active)
        ->and(AuditEvent::query()->where('business_id', $business->id)->where('action', 'membership.access.restored')->where('reason', 'Returned to the team.')->exists())->toBeTrue();
});

it('prevents a manager from granting access beyond their own role', function () {
    [$manager, $business, $managerMembership] = createTenantMembership(StarterRole::Manager);
    activateTestSubscription($business, 'pro');
    $location = Location::factory()->create(['business_id' => $business->id]);
    $profile = StaffProfile::factory()->create(['business_id' => $business->id, 'email' => 'candidate@example.test']);
    $ownerRole = BusinessRole::query()->where('business_id', $business->id)->where('name', 'owner')->firstOrFail();

    $this->actingAs($manager)->postJson(route('staff-invitations.store', $business), [
        'email' => $profile->email,
        'role_id' => $ownerRole->id,
        'staff_profile_id' => $profile->id,
        'location_ids' => [$location->id],
    ])->assertForbidden();

    expect(app(MembershipAccessManager::class)->allows($managerMembership, PermissionName::StaffManage))->toBeTrue();
});
