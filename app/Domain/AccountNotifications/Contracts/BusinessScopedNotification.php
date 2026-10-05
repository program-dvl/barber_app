<?php

namespace App\Domain\AccountNotifications\Contracts;

interface BusinessScopedNotification
{
    public function businessId(): ?int;
}
