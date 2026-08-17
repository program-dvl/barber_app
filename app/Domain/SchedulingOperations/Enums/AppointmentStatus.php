<?php

namespace App\Domain\SchedulingOperations\Enums;

enum AppointmentStatus: string
{
    case PendingConfirmation = 'pending_confirmation';
    case Confirmed = 'confirmed';
    case Arrived = 'arrived';
    case CheckedIn = 'checked_in';
    case InService = 'in_service';
    case Completed = 'completed';
    case CancelledByClient = 'cancelled_by_client';
    case CancelledByShop = 'cancelled_by_shop';
    case NoShow = 'no_show';
    case Late = 'late';
    case Rescheduled = 'rescheduled';

    public function consumesCapacity(): bool
    {
        return in_array($this, [
            self::PendingConfirmation, self::Confirmed, self::Arrived,
            self::CheckedIn, self::InService, self::Late,
        ], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::Completed, self::CancelledByClient, self::CancelledByShop,
            self::NoShow, self::Rescheduled,
        ], true);
    }
}
