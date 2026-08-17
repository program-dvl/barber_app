<?php

namespace App\Domain\PlatformAccess\Enums;

enum StarterRole: string
{
    case Owner = 'owner';
    case Manager = 'manager';
    case Receptionist = 'receptionist';
    case BarberStylist = 'barber_stylist';
    case Accountant = 'accountant';
}
