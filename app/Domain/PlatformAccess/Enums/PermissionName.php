<?php

namespace App\Domain\PlatformAccess\Enums;

enum PermissionName: string
{
    case CalendarViewAll = 'calendar.view.all';
    case CalendarViewOwn = 'calendar.view.own';
    case AppointmentsManageAll = 'appointments.manage.all';
    case AppointmentsManageOwn = 'appointments.manage.own';
    case WalkInsManage = 'walk_ins.manage';
    case ScheduleOverride = 'schedule.override';
    case ClientContactView = 'clients.contact.view';
    case ClientView = 'clients.view';
    case ClientManage = 'clients.manage';
    case ClientNotesManage = 'clients.notes.manage';
    case ClientMerge = 'clients.merge';
    case ClientFormsManage = 'clients.forms.manage';
    case ClientPrivacyManage = 'clients.privacy.manage';
    case SensitiveNotesView = 'clients.notes.sensitive.view';
    case ClientAttachmentsView = 'clients.attachments.view';
    case AppointmentDelete = 'appointments.delete';
    case DiscountApply = 'discounts.apply';
    case CheckoutManage = 'checkout.manage';
    case RefundIssue = 'refunds.issue';
    case CashCloseManage = 'cash_close.manage';
    case RevenueView = 'revenue.view';
    case CommissionsViewAll = 'commissions.view.all';
    case CommissionsViewOwn = 'commissions.view.own';
    case InventoryManage = 'inventory.manage';
    case SettingsManage = 'settings.manage';
    case BillingManage = 'billing.manage';
    case StaffManage = 'staff.manage';
    case AuditView = 'audit.view';
    case ExportCreate = 'exports.create';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
