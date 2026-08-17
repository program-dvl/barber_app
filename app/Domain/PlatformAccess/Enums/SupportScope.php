<?php

namespace App\Domain\PlatformAccess\Enums;

enum SupportScope: string
{
    case AccountSummary = 'account_summary';
    case Billing = 'billing';
    case Communications = 'communications';
    case WebhookFailures = 'webhook_failures';
    case Invitations = 'invitations';
    case Exports = 'exports';
}
