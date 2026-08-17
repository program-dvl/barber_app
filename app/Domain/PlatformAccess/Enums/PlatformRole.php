<?php

namespace App\Domain\PlatformAccess\Enums;

enum PlatformRole: string
{
    case Administrator = 'platform_administrator';
    case SupportOperator = 'support_operator';
    case SecurityAuditor = 'security_auditor';
}
