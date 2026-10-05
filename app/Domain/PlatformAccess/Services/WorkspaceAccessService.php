<?php

namespace App\Domain\PlatformAccess\Services;

use App\Domain\Billing\Services\EntitlementEvaluator;
use App\Domain\BusinessConfiguration\Services\BusinessSetupAccess;
use App\Domain\PlatformAccess\Enums\PermissionName;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\Membership;

class WorkspaceAccessService
{
    /** @var array<string, array{permissions?: list<PermissionName>, entitlement?: string, requires_location?: bool}> */
    private const FEATURES = [
        'dashboard' => [],
        'calendar' => [
            'permissions' => [PermissionName::CalendarViewAll, PermissionName::CalendarViewOwn],
            'requires_location' => true,
        ],
        'walk-in-queue' => [
            'permissions' => [PermissionName::WalkInsManage],
            'requires_location' => true,
        ],
        'clients' => ['permissions' => [PermissionName::ClientView]],
        'checkout-sales' => [
            'permissions' => [PermissionName::CheckoutManage, PermissionName::RevenueView],
            'requires_location' => true,
        ],
        'staff' => ['permissions' => [PermissionName::StaffManage, PermissionName::CalendarViewAll, PermissionName::CalendarViewOwn]],
        'activity' => ['permissions' => [PermissionName::AuditView]],
        'services' => ['permissions' => [PermissionName::SettingsManage]],
        'inventory' => [
            'permissions' => [PermissionName::InventoryManage],
            'entitlement' => 'inventory.enabled',
            'requires_location' => true,
        ],
        'reports' => [
            'permissions' => [
                PermissionName::RevenueView,
                PermissionName::CalendarViewAll,
                PermissionName::CalendarViewOwn,
                PermissionName::InventoryManage,
                PermissionName::CommissionsViewAll,
                PermissionName::CommissionsViewOwn,
            ],
            'requires_location' => true,
        ],
        'settings' => ['permissions' => [PermissionName::SettingsManage]],
        'communications' => ['permissions' => [PermissionName::SettingsManage, PermissionName::CalendarViewAll]],
        'subscription-billing' => [
            'permissions' => [PermissionName::BillingManage],
            'entitlement' => 'billing.manage',
        ],
    ];

    public function __construct(
        private readonly MembershipAccessManager $memberships,
        private readonly EntitlementEvaluator $entitlements,
    ) {}

    /** @return array<string, array{allowed: bool, visible: bool, status: string, reason: ?string, entitlement: ?string}> */
    public function navigation(Business $business, Membership $membership): array
    {
        return collect(array_keys(self::FEATURES))
            ->mapWithKeys(fn (string $feature): array => [$feature => $this->decide($business, $membership, $feature)])
            ->all();
    }

    /** @return array{allowed: bool, visible: bool, status: string, reason: ?string, entitlement: ?string} */
    public function decide(Business $business, Membership $membership, string $feature): array
    {
        $definition = self::FEATURES[$feature] ?? null;
        if ($definition === null) {
            return $this->denied('permission_denied', 'This area is not available to this membership.');
        }

        if ($feature === 'settings' && ! app(BusinessSetupAccess::class)->allows($business, $membership)) {
            return $this->denied('permission_denied', 'Business-wide setup requires access to every location. Use your branch settings in Team and Services.');
        }
        $permissions = $definition['permissions'] ?? [];
        if ($feature === 'communications' && ! $this->memberships->allows($membership, PermissionName::SettingsManage)
            && (! $this->memberships->allows($membership, PermissionName::ClientView) || ! $this->memberships->allows($membership, PermissionName::ClientContactView))) {
            return $this->denied('permission_denied', 'Your salon role does not include client communication history.');
        }
        if ($permissions !== [] && ! collect($permissions)->contains(
            fn (PermissionName $permission): bool => $this->memberships->allows($membership, $permission)
        )) {
            return $this->denied('permission_denied', 'Your salon role does not include this area.');
        }

        $entitlement = $definition['entitlement'] ?? null;
        if ($entitlement && ! $this->entitlements->decide($business, $entitlement, 'read')->allowed) {
            return [
                'allowed' => false,
                'visible' => true,
                'status' => 'upgrade_required',
                'reason' => 'This capability is not included in the current plan.',
                'entitlement' => $entitlement,
            ];
        }

        if (($definition['requires_location'] ?? false)
            && ! $this->hasAccessibleLocation($business, $membership)) {
            return [
                'allowed' => false,
                'visible' => true,
                'status' => 'setup_required',
                'reason' => 'Finish the primary location setup before using this area.',
                'entitlement' => $entitlement,
            ];
        }

        return [
            'allowed' => true,
            'visible' => true,
            'status' => 'available',
            'reason' => null,
            'entitlement' => $entitlement,
        ];
    }

    /** @return array{allowed: false, visible: false, status: string, reason: string, entitlement: null} */
    private function denied(string $status, string $reason): array
    {
        return [
            'allowed' => false,
            'visible' => false,
            'status' => $status,
            'reason' => $reason,
            'entitlement' => null,
        ];
    }

    private function hasAccessibleLocation(Business $business, Membership $membership): bool
    {
        if ($membership->hasRole('owner', 'web')) {
            return $business->locations()->where('is_active', true)->exists();
        }

        return $membership->locations()
            ->where('locations.business_id', $business->getKey())
            ->where('locations.is_active', true)
            ->exists();
    }
}
