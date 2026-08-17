<?php

use App\Domain\PlatformAccess\Actions\AcceptStaffInvitation;
use App\Domain\PlatformAccess\Actions\IssueStaffInvitation;
use App\Domain\PlatformAccess\Actions\RevokeStaffInvitation;
use App\Domain\PlatformAccess\Enums\PermissionName;
use App\Domain\PlatformAccess\Enums\PlatformRole;
use App\Domain\PlatformAccess\Enums\StarterRole;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\BusinessRole;
use App\Domain\PlatformAccess\Models\Location;
use App\Domain\PlatformAccess\Models\Membership;
use App\Domain\PlatformAccess\Models\PlatformRoleAssignment;
use App\Domain\PlatformAccess\Models\StaffProfile;
use App\Domain\PlatformAccess\Services\BusinessAccessBootstrapper;
use App\Domain\PlatformAccess\Services\MembershipAccessManager;
use App\Domain\PlatformAccess\Services\MembershipRevoker;
use App\Models\User;
use App\Support\Cache\TenantCacheKey;
use App\Support\Exports\TenantExportName;
use App\Support\Files\TenantFilePath;
use App\Support\Files\TenantPrivateStorage;
use App\Support\Jobs\DispatchesInTenant;
use App\Support\Jobs\ResolveTenantForJob;
use App\Support\Jobs\TenantAwareJob;
use App\Support\Search\TenantSearchDocument;
use App\Support\Tenancy\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

uses(RefreshDatabase::class);

it('provisions the documented starter-role authorization matrix', function (StarterRole $role) {
    [$user, $business, $membership] = createTenantMembership($role);
    $expected = collect(BusinessAccessBootstrapper::matrix()[$role->value])->map->value->sort()->values()->all();

    $actual = collect(PermissionName::cases())
        ->filter(fn (PermissionName $permission) => app(MembershipAccessManager::class)->allows($membership, $permission))
        ->map->value
        ->sort()
        ->values()
        ->all();

    expect($actual)->toBe($expected)
        ->and($user->memberships()->where('business_id', $business->id)->exists())->toBeTrue();
})->with(StarterRole::cases());

it('keeps permissions distinct when one user belongs to two businesses', function () {
    $user = User::factory()->create();
    $firstBusiness = Business::factory()->create();
    $secondBusiness = Business::factory()->create();
    $ownerMembership = Membership::factory()->create(['business_id' => $firstBusiness->id, 'user_id' => $user->id]);
    $stylistMembership = Membership::factory()->create(['business_id' => $secondBusiness->id, 'user_id' => $user->id]);
    $access = app(MembershipAccessManager::class);
    $access->assignStarterRole($ownerMembership, StarterRole::Owner, $user, 'Owner in first Business.');
    $access->assignStarterRole($stylistMembership, StarterRole::BarberStylist, $user, 'Stylist in second Business.');

    expect($access->allows($ownerMembership, PermissionName::BillingManage))->toBeTrue()
        ->and($access->allows($stylistMembership, PermissionName::BillingManage))->toBeFalse()
        ->and($access->allows($stylistMembership, PermissionName::CalendarViewOwn))->toBeTrue();
});

it('supports audited custom direct permission sets without changing shared starter roles', function () {
    [$owner, $business] = createTenantMembership(StarterRole::Owner);
    $receptionist = User::factory()->create();
    $membership = Membership::factory()->create(['business_id' => $business->id, 'user_id' => $receptionist->id]);
    $access = app(MembershipAccessManager::class);
    $access->assignStarterRole($membership, StarterRole::Receptionist, $owner, 'Base receptionist role.');

    expect($access->allows($membership, PermissionName::RefundIssue))->toBeFalse();

    $access->replaceCustomPermissions($membership, [PermissionName::RefundIssue], $owner, 'Approved exception.');

    expect($access->allows($membership, PermissionName::RefundIssue))->toBeTrue()
        ->and($membership->fresh()->business_id)->toBe($business->id);
});

it('supports fully custom tenant roles with an exact permission set', function () {
    [$owner, $business] = createTenantMembership(StarterRole::Owner);
    $customUser = User::factory()->create();
    $membership = Membership::factory()->create(['business_id' => $business->id, 'user_id' => $customUser->id]);
    $access = app(MembershipAccessManager::class);
    $role = $access->defineCustomRole(
        $business,
        'Refund reviewer',
        [PermissionName::RefundIssue, PermissionName::AuditView],
        $owner,
        'Custom least-privilege role.',
    );
    $access->assignCustomRole($membership, $role, $owner, 'Assign custom role.');

    expect($access->allows($membership, PermissionName::RefundIssue))->toBeTrue()
        ->and($access->allows($membership, PermissionName::AuditView))->toBeTrue()
        ->and($access->allows($membership, PermissionName::RevenueView))->toBeFalse()
        ->and($role->business_id)->toBe($business->id);
});

it('layers tenant membership, scoped route binding, and policy checks', function () {
    [$receptionist, $business, $membership] = createTenantMembership(StarterRole::Receptionist);
    $location = Location::factory()->create(['business_id' => $business->id]);
    $unassignedLocation = Location::factory()->create(['business_id' => $business->id]);
    $membership->locations()->syncWithPivotValues([$location->id], ['business_id' => $business->id]);
    $otherBusiness = Business::factory()->create();
    $otherLocation = Location::factory()->create(['business_id' => $otherBusiness->id]);

    $this->actingAs($receptionist)
        ->getJson(route('business.locations.show', [$business, $location]))
        ->assertOk()
        ->assertJsonPath('business_public_id', $business->public_id);

    $this->actingAs($receptionist)
        ->getJson(route('business.locations.show', [$business, $otherLocation]))
        ->assertNotFound();

    $this->actingAs($receptionist)
        ->getJson(route('business.locations.show', [$business, $unassignedLocation]))
        ->assertForbidden();

    $this->actingAs($receptionist)
        ->getJson(route('business.locations.show', [$otherBusiness, $otherLocation]))
        ->assertForbidden();

    [$accountant, $accountingBusiness] = createTenantMembership(StarterRole::Accountant);
    $accountingLocation = Location::factory()->create(['business_id' => $accountingBusiness->id]);

    $this->actingAs($accountant)
        ->getJson(route('business.locations.show', [$accountingBusiness, $accountingLocation]))
        ->assertForbidden();
});

it('binds an expiring invitation to its business, role, locations, staff profile, and email identity', function () {
    Notification::fake();
    [$owner, $business, $ownerMembership] = createTenantMembership(StarterRole::Owner);
    $location = Location::factory()->create(['business_id' => $business->id]);
    $profile = StaffProfile::factory()->create(['business_id' => $business->id, 'email' => 'new.staff@example.test']);
    $role = BusinessRole::query()
        ->where('business_id', $business->id)
        ->where('name', StarterRole::BarberStylist->value)
        ->firstOrFail();

    $issued = app(IssueStaffInvitation::class)->handle(
        inviter: $ownerMembership,
        email: 'NEW.STAFF@example.test',
        role: $role,
        locationIds: [$location->id],
        staffProfile: $profile,
        expiresInDays: 3,
    );

    expect($issued->invitation->business_id)->toBe($business->id)
        ->and($issued->invitation->token_hash)->toBe(hash('sha256', $issued->plainTextToken))
        ->and($issued->invitation->expires_at->isFuture())->toBeTrue()
        ->and($issued->invitation->locations()->pluck('locations.id')->all())->toBe([$location->id]);

    $wrongIdentity = User::factory()->create(['email' => 'wrong@example.test']);
    expect(fn () => app(AcceptStaffInvitation::class)->handle($issued->plainTextToken, $wrongIdentity))
        ->toThrow(ValidationException::class);

    $newStaff = User::factory()->create(['email' => 'new.staff@example.test']);
    $this->actingAs($newStaff)
        ->get(route('staff-invitations.show', $issued->plainTextToken))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Access/StaffInvitation')
            ->where('businessName', $business->name)
        );
    $membership = app(AcceptStaffInvitation::class)->handle($issued->plainTextToken, $newStaff);

    expect($membership->business_id)->toBe($business->id)
        ->and($membership->locations()->pluck('locations.id')->all())->toBe([$location->id])
        ->and($profile->fresh()->membership_id)->toBe($membership->id)
        ->and(app(MembershipAccessManager::class)->allows($membership, PermissionName::CalendarViewOwn))->toBeTrue();

    expect(fn () => app(AcceptStaffInvitation::class)->handle($issued->plainTextToken, $newStaff))
        ->toThrow(ValidationException::class);
});

it('rejects cross-tenant identifiers while issuing invitations', function () {
    Notification::fake();
    [$owner, $business, $membership] = createTenantMembership(StarterRole::Owner);
    $otherBusiness = Business::factory()->create();
    $foreignRole = BusinessRole::query()->where('business_id', $otherBusiness->id)->firstOrFail();

    expect(fn () => app(IssueStaffInvitation::class)->handle(
        inviter: $membership,
        email: 'staff@example.test',
        role: $foreignRole,
    ))->toThrow(ValidationException::class);
});

it('denies invitation issuance to a role without staff-management permission', function () {
    Notification::fake();
    [$receptionist, $business, $membership] = createTenantMembership(StarterRole::Receptionist);
    $role = BusinessRole::query()
        ->where('business_id', $business->id)
        ->where('name', StarterRole::Receptionist->value)
        ->firstOrFail();

    expect(fn () => app(IssueStaffInvitation::class)->handle($membership, 'new@example.test', $role))
        ->toThrow(AuthorizationException::class);

    $this->actingAs($receptionist)
        ->postJson(route('staff-invitations.store', $business), [
            'email' => 'new@example.test',
            'role_id' => $role->id,
        ])
        ->assertForbidden();
});

it('rejects expired and revoked staff invitations', function () {
    Notification::fake();
    [$owner, $business, $membership] = createTenantMembership(StarterRole::Owner);
    $role = BusinessRole::query()
        ->where('business_id', $business->id)
        ->where('name', StarterRole::Receptionist->value)
        ->firstOrFail();
    $invitee = User::factory()->create(['email' => 'expiry@example.test']);

    $expired = app(IssueStaffInvitation::class)->handle($membership, $invitee->email, $role);
    $expired->invitation->forceFill(['expires_at' => now()->subMinute()])->save();
    expect(fn () => app(AcceptStaffInvitation::class)->handle($expired->plainTextToken, $invitee))
        ->toThrow(ValidationException::class);

    $revoked = app(IssueStaffInvitation::class)->handle($membership, $invitee->email, $role);
    app(RevokeStaffInvitation::class)->handle($revoked->invitation, $membership, 'Position withdrawn.');
    expect(fn () => app(AcceptStaffInvitation::class)->handle($revoked->plainTextToken, $invitee))
        ->toThrow(ValidationException::class);
});

it('removes tenant access promptly and revokes active sessions and scoped or legacy tokens', function () {
    [$owner, $business] = createTenantMembership(StarterRole::Owner);
    $employee = User::factory()->create();
    $employeeMembership = Membership::factory()->create(['business_id' => $business->id, 'user_id' => $employee->id]);
    app(MembershipAccessManager::class)->assignStarterRole($employeeMembership, StarterRole::Receptionist, $owner, 'Employee access.');
    $location = Location::factory()->create(['business_id' => $business->id]);

    DB::table('sessions')->insert([
        'id' => 'former-employee-session',
        'user_id' => $employee->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Test browser',
        'payload' => 'serialized-test-payload',
        'last_activity' => now()->timestamp,
    ]);
    DB::table('personal_access_tokens')->insert([
        'tokenable_type' => User::class,
        'tokenable_id' => $employee->id,
        'business_id' => $business->id,
        'membership_id' => $employeeMembership->id,
        'name' => 'tenant token',
        'token' => hash('sha256', 'tenant-token'),
        'abilities' => '["*"]',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $employeeMembership->locations()->syncWithPivotValues([$location->id], ['business_id' => $business->id]);

    $this->actingAs($employee)
        ->getJson(route('business.locations.show', [$business, $location]))
        ->assertOk();

    app(MembershipRevoker::class)->revoke($employeeMembership, $owner, 'Employment ended.');

    expect(DB::table('sessions')->where('user_id', $employee->id)->exists())->toBeFalse()
        ->and(DB::table('personal_access_tokens')->where('tokenable_id', $employee->id)->exists())->toBeFalse();

    $this->actingAs($employee)
        ->getJson(route('business.locations.show', [$business, $location]))
        ->assertForbidden();
});

it('uses tenant-prefixed cache, private file, search, export, and job conventions', function () {
    Storage::fake('private');
    $firstBusiness = Business::factory()->create();
    $secondBusiness = Business::factory()->create();
    $context = app(TenantContext::class);
    $storage = app(TenantPrivateStorage::class);

    $context->activate($firstBusiness);
    $key = $storage->put($firstBusiness, 'attachments/consent.pdf', 'first-business');

    expect($key)->toBe(TenantFilePath::private($firstBusiness, 'attachments/consent.pdf'))
        ->and(TenantCacheKey::make($firstBusiness, 'clients', '42'))->toBe('business:'.$firstBusiness->id.':clients:42')
        ->and(TenantExportName::make($firstBusiness, 'Client export'))->toStartWith('business-'.$firstBusiness->public_id.'-client-export-')
        ->and(TenantSearchDocument::make($firstBusiness, 'client', 42, ['name' => 'Redacted'])['document_id'])
        ->toBe($firstBusiness->id.':client:42');

    expect(fn () => $storage->get($secondBusiness, 'attachments/consent.pdf'))
        ->toThrow(AccessDeniedHttpException::class);

    $job = new TenantIsolationProbeJob($firstBusiness, 'correlation-test');
    app(ResolveTenantForJob::class)->handle($job, function () use ($context, $firstBusiness): void {
        expect($context->business()->is($firstBusiness))->toBeTrue();
    });

    expect($job->tenantBusinessId())->toBe($firstBusiness->id)
        ->and($job->correlationId())->toBe('correlation-test')
        ->and(serialize($job))->toContain('businessId')->toContain('tenantCorrelationId');

    $context->clear();
});

it('keeps platform roles separate from tenant access and requires verified TOTP administration', function () {
    $platformAdministrator = User::factory()->create(['two_factor_confirmed_at' => now()]);
    PlatformRoleAssignment::query()->create([
        'user_id' => $platformAdministrator->id,
        'role' => PlatformRole::Administrator,
        'reason' => 'Platform isolation test.',
    ]);
    $business = Business::factory()->create();
    $location = Location::factory()->create(['business_id' => $business->id]);

    $this->actingAs($platformAdministrator)->get(route('platform.overview'))->assertOk();
    $this->actingAs($platformAdministrator)
        ->getJson(route('business.locations.show', [$business, $location]))
        ->assertForbidden();

    [$owner] = createTenantMembership(StarterRole::Owner);
    $this->actingAs($owner)->get(route('platform.overview'))->assertForbidden();

    $unverifiedOrWeakAdministrator = User::factory()->create(['two_factor_confirmed_at' => null]);
    PlatformRoleAssignment::query()->create([
        'user_id' => $unverifiedOrWeakAdministrator->id,
        'role' => PlatformRole::Administrator,
        'reason' => 'Missing TOTP test.',
    ]);
    $this->actingAs($unverifiedOrWeakAdministrator)->get(route('platform.overview'))->assertForbidden();

    $expiredAdministrator = User::factory()->create(['two_factor_confirmed_at' => now()]);
    PlatformRoleAssignment::query()->create([
        'user_id' => $expiredAdministrator->id,
        'role' => PlatformRole::Administrator,
        'reason' => 'Expired platform role test.',
        'expires_at' => now()->subMinute(),
    ]);
    $this->actingAs($expiredAdministrator)->get(route('platform.overview'))->assertForbidden();
});

it('enforces verified launch identities and leaves unsafe magic and social routes disabled', function () {
    [$user, $business] = createTenantMembership(StarterRole::Owner);
    $user->forceFill(['email_verified_at' => null])->save();

    expect(Route::has('magic.link'))->toBeFalse()
        ->and(Route::has('magic.link.login'))->toBeFalse()
        ->and(Route::has('socialite.redirect'))->toBeFalse()
        ->and(Route::has('socialite.callback'))->toBeFalse();

    $this->actingAs($user)
        ->get(route('business.dashboard', $business))
        ->assertRedirect(route('verification.notice'));
});

class TenantIsolationProbeJob implements TenantAwareJob
{
    use DispatchesInTenant;

    public function __construct(Business $business, string $correlationId)
    {
        $this->initializeTenantPayload($business, $correlationId);
    }
}
