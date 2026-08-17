<?php

namespace App\Domain\PlatformAccess\Enums;

enum BusinessStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Closed = 'closed';
}
