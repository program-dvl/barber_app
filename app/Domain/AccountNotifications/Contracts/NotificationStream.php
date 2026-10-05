<?php

namespace App\Domain\AccountNotifications\Contracts;

interface NotificationStream
{
    public function notificationStream(): string;
}
