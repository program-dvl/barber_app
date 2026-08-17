<?php

namespace App\Domain\Billing\Enums;

enum BillingInterval: string
{
    case Monthly = 'monthly';
    case Annual = 'annual';
}
