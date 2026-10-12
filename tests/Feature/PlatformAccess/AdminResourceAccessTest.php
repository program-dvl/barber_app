<?php

use App\Domain\PlatformAccess\Enums\PermissionName;
use App\Domain\PlatformAccess\Enums\PlatformRole;
use App\Domain\PlatformAccess\Models\AuditEvent;
use App\Domain\PlatformAccess\Models\BusinessRole;
use App\Domain\PlatformAccess\Models\PlatformRoleAssignment;
use App\Domain\PlatformAccess\Services\PlatformRoleManager;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Roles\Pages\CreateRole;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\RelationManagers\RoleRelationManager;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

function resourceAdministrator(): User
{
    $user = User::factory()->create();
    PlatformRoleAssignment::query()->create(['user_id' => $user->id, 'role' => PlatformRole::Administrator, 'reason' => 'Admin resource test.']);

    return $user;
}

beforeEach(fn () => Filament::setCurrentPanel(Filament::getPanel('admin')));

it('opens every restored resource for a verified platform administrator', function (string $path) {
    $this->actingAs(resourceAdministrator())->get('/admin/'.$path)->assertOk();
})->with(['users', 'roles', 'permissions', 'products', 'prices', 'stripe-subscriptions', 'stripe-orders', 'invoices', 'lemon-squeezy-subscriptions', 'lemon-squeezy-orders']);

it('denies restored resources for ordinary support auditor revoked expired and unverified identities', function (string $scenario) {
    $user = $scenario === 'ordinary' ? User::factory()->create() : resourceAdministrator();
    if (in_array($scenario, ['support', 'auditor'])) {
        $user->platformRoleAssignments()->update(['role' => $scenario === 'support' ? PlatformRole::SupportOperator : PlatformRole::SecurityAuditor]);
    }
    if (in_array($scenario, ['revoked', 'expired'])) {
        $user->platformRoleAssignments()->update($scenario === 'revoked' ? ['revoked_at' => now()] : ['expires_at' => now()->subMinute()]);
    }
    if ($scenario === 'unverified') {
        $user->forceFill(['email_verified_at' => null])->save();
    }
    $this->actingAs($user);
    expect(UserResource::canAccess())->toBeFalse()->and(UserResource::shouldRegisterNavigation())->toBeFalse()
        ->and(InvoiceResource::canViewAny())->toBeFalse();
    $response = $this->get('/admin/users');
    $scenario === 'unverified' ? $response->assertRedirect(Filament::getEmailVerificationPromptUrl()) : $response->assertForbidden();
})->with(['ordinary', 'support', 'auditor', 'revoked', 'expired', 'unverified']);

it('creates and edits users with audit evidence and invalidates verification on email change', function () {
    $this->actingAs(resourceAdministrator());
    Livewire::test(CreateUser::class)->fillForm([
        'name' => 'Synthetic operator', 'email' => 'synthetic-admin@example.test', 'password' => 'Synthetic-password-123',
        '_admin_reason' => 'Create a synthetic internal operator.',
    ])->call('create')->assertHasNoFormErrors();
    $user = User::where('email', 'synthetic-admin@example.test')->firstOrFail();
    expect(Hash::check('Synthetic-password-123', $user->password))->toBeTrue()
        ->and($user->platformRoleAssignments()->exists())->toBeFalse();
    $user->markEmailAsVerified();
    Livewire::test(EditUser::class, ['record' => $user->id])->fillForm([
        'email' => 'synthetic-new@example.test', '_admin_reason' => 'Correct the synthetic email address.',
    ])->call('save')->assertHasNoFormErrors();
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse()
        ->and(Hash::check('Synthetic-password-123', $user->fresh()->password))->toBeTrue()
        ->and(AuditEvent::where('action', 'platform.user.created')->exists())->toBeTrue()
        ->and(AuditEvent::where('action', 'platform.user.updated')->exists())->toBeTrue()
        ->and(UserResource::canDelete($user))->toBeFalse();
});

it('grants and revokes actual platform roles with append-only audit evidence', function () {
    $admin = resourceAdministrator();
    $target = User::factory()->create();
    $this->actingAs($admin);
    Livewire::test(RoleRelationManager::class, ['ownerRecord' => $target, 'pageClass' => EditUser::class])
        ->callAction(TestAction::make('grantRole')->table(), ['role' => PlatformRole::Administrator->value, 'reason' => 'Approved synthetic administrator access.'])
        ->assertHasNoActionErrors();
    $assignment = $target->platformRoleAssignments()->firstOrFail();
    expect($target->hasPlatformRole(PlatformRole::Administrator))->toBeTrue()
        ->and(AuditEvent::where('action', 'platform.role.granted')->exists())->toBeTrue();
    app(PlatformRoleManager::class)->revoke($assignment, $admin, 'Synthetic access is no longer required.');
    expect($target->hasPlatformRole(PlatformRole::Administrator))->toBeFalse()
        ->and(AuditEvent::where('action', 'platform.role.revoked')->exists())->toBeTrue()
        ->and($assignment->fresh()->revoked_at)->not->toBeNull();
});

it('defines a custom role within one business using the audited membership service', function () {
    [, $business] = createTenantMembership();
    [, $other] = createTenantMembership();
    $this->actingAs(resourceAdministrator());
    Livewire::test(CreateRole::class)->fillForm([
        'business_id' => $business->id, 'name' => 'custom_reviewer',
        '_admin_permissions' => [PermissionName::ClientView->value], '_admin_reason' => 'Approved synthetic business reviewer role.',
    ])->call('create')->assertHasNoFormErrors();
    expect(BusinessRole::where('business_id', $business->id)->where('name', 'custom_reviewer')->exists())->toBeTrue()
        ->and(BusinessRole::where('business_id', $other->id)->where('name', 'custom_reviewer')->exists())->toBeFalse()
        ->and(AuditEvent::where('action', 'business.role.changed')->where('business_id', $business->id)->exists())->toBeTrue();
});

it('keeps financial history read only even for platform administrators', function () {
    $this->actingAs(resourceAdministrator());
    expect(InvoiceResource::canViewAny())->toBeTrue()->and(InvoiceResource::canCreate())->toBeFalse()
        ->and(InvoiceResource::canDeleteAny())->toBeFalse();
    $this->get('/admin/invoices/create')->assertForbidden();
    $this->get('/admin/permissions/create')->assertForbidden();
});

it('rejects protected starter-role editing and self revocation', function () {
    [, $business] = createTenantMembership();
    $admin = resourceAdministrator();
    $this->actingAs($admin);
    $role = BusinessRole::where('business_id', $business->id)->where('name', 'owner')->firstOrFail();
    $this->get('/admin/roles/'.$role->id.'/edit')->assertForbidden();
    Livewire::test(CreateRole::class)->fillForm([
        'business_id' => $business->id, 'name' => 'owner', '_admin_permissions' => [PermissionName::ClientView->value],
        '_admin_reason' => 'A reserved role may not be replaced.',
    ])->call('create')->assertHasFormErrors(['name']);
    expect(fn () => app(PlatformRoleManager::class)->revoke($admin->platformRoleAssignments()->first(), $admin, 'Do not lock out the current administrator.'))
        ->toThrow(HttpException::class);
    expect($admin->hasPlatformRole(PlatformRole::Administrator))->toBeTrue();
});
