<?php

namespace App\Domain\Billing\Enums;

enum SubscriptionStatus: string
{
    case Trialing = 'trialing';
    case Active = 'active';
    case PastDue = 'past_due';
    case Grace = 'grace';
    case Restricted = 'restricted';
    case CancelScheduled = 'cancel_scheduled';
    case Canceled = 'canceled';
    case Terminated = 'terminated';

    public function permitsNormalWrites(): bool
    {
        return in_array($this, [self::Trialing, self::Active, self::PastDue, self::Grace, self::CancelScheduled], true);
    }
}
