<?php

namespace App\Domain\PlatformAccess\Services;

use App\Domain\PlatformAccess\Enums\PermissionName;
use App\Domain\PlatformAccess\Enums\StarterRole;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\BusinessPermission;
use App\Domain\PlatformAccess\Models\BusinessRole;
use App\Domain\PlatformAccess\Models\Membership;
use App\Models\User;
use App\Support\Audit\AuditWriter;
use InvalidArgumentException;
use Spatie\Permission\PermissionRegistrar;

class MembershipAccessManager
{
    public function __construct(private readonly AuditWriter $audit) {}

    public function assignStarterRole(Membership $membership, StarterRole $role, ?User $actor = null, ?string $reason = null): void
    {
        $this->inMembershipContext($membership, function () use ($membership, $role, $actor, $reason): void {
            $roleModel = BusinessRole::query()
                ->where('business_id', $membership->business_id)
                ->where('name', $role->value)
                ->firstOrFail();
            $before = $membership->getRoleNames()->values()->all();

            $membership->syncRoles([$roleModel]);

            $this->audit->write(
                action: 'membership.role.changed',
                business: $membership->business,
                actor: $actor,
                target: $membership,
                reason: $reason,
                before: ['roles' => $before],
                after: ['roles' => [$role->value]],
            );
        });
    }

    /** @param list<PermissionName|string> $permissions */
    public function defineCustomRole(Business $business, string $name, array $permissions, User $actor, string $reason): BusinessRole
    {
        $normalizedName = str($name)->trim()->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->toString();

        if ($normalizedName === '' || StarterRole::tryFrom($normalizedName)) {
            throw new InvalidArgumentException('Custom role names must be non-empty and may not replace a starter role.');
        }

        $permissionNames = $this->validatedPermissionNames($permissions);

        return $this->inBusinessContext($business->getKey(), function () use ($business, $normalizedName, $permissionNames, $actor, $reason): BusinessRole {
            $role = BusinessRole::query()->firstOrCreate([
                'business_id' => $business->getKey(),
                'name' => $normalizedName,
                'guard_name' => 'web',
            ]);
            $before = $role->permissions()->pluck('name')->all();
            $role->syncPermissions(BusinessPermission::query()->whereIn('name', $permissionNames)->get());

            $this->audit->write(
                action: 'business.role.changed',
                business: $business,
                actor: $actor,
                target: $role,
                reason: $reason,
                before: ['permissions' => $before],
                after: ['name' => $normalizedName, 'permissions' => $permissionNames],
            );

            return $role;
        });
    }

    public function assignCustomRole(Membership $membership, BusinessRole $role, User $actor, string $reason): void
    {
        if ((int) $role->business_id !== (int) $membership->business_id) {
            throw new InvalidArgumentException('A custom role must belong to the Membership Business.');
        }

        $this->inMembershipContext($membership, function () use ($membership, $role, $actor, $reason): void {
            $before = $membership->getRoleNames()->values()->all();
            $membership->syncRoles([$role]);
            $this->audit->write(
                action: 'membership.role.changed',
                business: $membership->business,
                actor: $actor,
                target: $membership,
                reason: $reason,
                before: ['roles' => $before],
                after: ['roles' => [$role->name]],
            );
        });
    }

    /** @param list<PermissionName|string> $permissions */
    public function replaceCustomPermissions(Membership $membership, array $permissions, ?User $actor = null, ?string $reason = null): void
    {
        $permissionNames = $this->validatedPermissionNames($permissions);

        $this->inMembershipContext($membership, function () use ($membership, $permissionNames, $actor, $reason): void {
            $before = $membership->getDirectPermissions()->pluck('name')->values()->all();
            $models = BusinessPermission::query()->whereIn('name', $permissionNames)->get();
            $membership->syncPermissions($models);

            $this->audit->write(
                action: 'membership.permissions.changed',
                business: $membership->business,
                actor: $actor,
                target: $membership,
                reason: $reason,
                before: ['direct_permissions' => $before],
                after: ['direct_permissions' => $permissionNames],
            );
        });
    }

    public function allows(Membership $membership, PermissionName $permission): bool
    {
        if (! $membership->isActive()) {
            return false;
        }

        return $this->inMembershipContext(
            $membership,
            fn (): bool => $membership->fresh()->hasPermissionTo($permission->value, 'web')
        );
    }

    private function inMembershipContext(Membership $membership, callable $callback): mixed
    {
        return $this->inBusinessContext($membership->business_id, function () use ($membership, $callback): mixed {
            $membership->unsetRelation('roles')->unsetRelation('permissions');

            return $callback();
        });
    }

    private function inBusinessContext(int $businessId, callable $callback): mixed
    {
        $registrar = app(PermissionRegistrar::class);
        $previousBusinessId = $registrar->getPermissionsTeamId();
        setPermissionsTeamId($businessId);

        try {
            return $callback();
        } finally {
            setPermissionsTeamId($previousBusinessId);
        }
    }

    /** @param list<PermissionName|string> $permissions
     * @return list<string>
     */
    private function validatedPermissionNames(array $permissions): array
    {
        $permissionNames = array_values(array_unique(array_map(
            fn (PermissionName|string $permission) => $permission instanceof PermissionName ? $permission->value : $permission,
            $permissions
        )));

        if (array_diff($permissionNames, PermissionName::values())) {
            throw new InvalidArgumentException('Unknown business permission.');
        }

        return $permissionNames;
    }
}
