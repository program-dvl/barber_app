<?php

namespace App\Domain\PlatformAccess\Services;

use App\Domain\PlatformAccess\Enums\PermissionName;
use App\Domain\PlatformAccess\Enums\StarterRole;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\BusinessPermission;
use App\Domain\PlatformAccess\Models\BusinessRole;
use Spatie\Permission\PermissionRegistrar;

class BusinessAccessBootstrapper
{
    /** @return array<string, list<PermissionName>> */
    public static function matrix(): array
    {
        return [
            StarterRole::Owner->value => PermissionName::cases(),
            StarterRole::Manager->value => [
                PermissionName::CalendarViewAll,
                PermissionName::AppointmentsManageAll,
                PermissionName::WalkInsManage,
                PermissionName::ScheduleOverride,
                PermissionName::ClientContactView,
                PermissionName::ClientView,
                PermissionName::ClientManage,
                PermissionName::ClientNotesManage,
                PermissionName::ClientMerge,
                PermissionName::ClientFormsManage,
                PermissionName::ClientPrivacyManage,
                PermissionName::SensitiveNotesView,
                PermissionName::ClientAttachmentsView,
                PermissionName::AppointmentDelete,
                PermissionName::DiscountApply,
                PermissionName::CheckoutManage,
                PermissionName::RefundIssue,
                PermissionName::CashCloseManage,
                PermissionName::RevenueView,
                PermissionName::CommissionsViewAll,
                PermissionName::InventoryManage,
                PermissionName::SettingsManage,
                PermissionName::StaffManage,
                PermissionName::AuditView,
                PermissionName::ExportCreate,
            ],
            StarterRole::Receptionist->value => [
                PermissionName::CalendarViewAll,
                PermissionName::AppointmentsManageAll,
                PermissionName::WalkInsManage,
                PermissionName::ClientContactView,
                PermissionName::ClientView,
                PermissionName::ClientManage,
                PermissionName::ClientNotesManage,
                PermissionName::ClientFormsManage,
                PermissionName::ClientAttachmentsView,
                PermissionName::CheckoutManage,
            ],
            StarterRole::BarberStylist->value => [
                PermissionName::CalendarViewOwn,
                PermissionName::AppointmentsManageOwn,
                PermissionName::ClientContactView,
                PermissionName::ClientView,
                PermissionName::ClientNotesManage,
                PermissionName::ClientFormsManage,
                PermissionName::CommissionsViewOwn,
            ],
            StarterRole::Accountant->value => [
                PermissionName::RevenueView,
                PermissionName::CashCloseManage,
                PermissionName::CommissionsViewAll,
                PermissionName::AuditView,
                PermissionName::ExportCreate,
            ],
        ];
    }

    public function bootstrap(Business $business): void
    {
        $registrar = app(PermissionRegistrar::class);
        $previousBusinessId = $registrar->getPermissionsTeamId();
        $registrar->forgetCachedPermissions();
        setPermissionsTeamId($business->getKey());

        try {
            $permissions = collect(PermissionName::cases())->mapWithKeys(function (PermissionName $permission): array {
                $model = BusinessPermission::findOrCreate($permission->value, 'web');

                return [$permission->value => $model];
            });

            foreach (self::matrix() as $roleName => $rolePermissions) {
                $role = BusinessRole::query()->firstOrCreate([
                    'business_id' => $business->getKey(),
                    'name' => $roleName,
                    'guard_name' => 'web',
                ]);

                $role->syncPermissions(array_map(
                    fn (PermissionName $permission) => $permissions->get($permission->value),
                    $rolePermissions
                ));
            }
        } finally {
            setPermissionsTeamId($previousBusinessId);
            $registrar->forgetCachedPermissions();
        }
    }
}
