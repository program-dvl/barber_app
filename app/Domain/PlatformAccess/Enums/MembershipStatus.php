<?php

namespace App\Domain\PlatformAccess\Enums;

enum MembershipStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Revoked = 'revoked';
}
