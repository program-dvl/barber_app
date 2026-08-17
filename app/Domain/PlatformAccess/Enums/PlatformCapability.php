<?php

namespace App\Domain\PlatformAccess\Enums;

enum PlatformCapability: string
{
    case TenantView = 'tenant.view';
    case TenantLifecycle = 'tenant.lifecycle';
    case BillingManage = 'billing.manage';
    case FailureView = 'failure.view';
    case FailureReplay = 'failure.replay';
    case FeatureFlagManage = 'feature_flag.manage';
    case NoticeManage = 'notice.manage';
    case NotesManage = 'notes.manage';
    case SupportGrantManage = 'support_grant.manage';
    case SupportEnter = 'support.enter';
    case HealthView = 'health.view';
    case AuditView = 'audit.view';
    case ExportInitiate = 'export.initiate';
    case AlertView = 'alert.view';
}
