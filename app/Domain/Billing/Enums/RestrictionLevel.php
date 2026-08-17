<?php

namespace App\Domain\Billing\Enums;

enum RestrictionLevel: string
{
    case None = 'none';
    case Warning = 'warning';
    case ReadOnly = 'read_only';
    case Closed = 'closed';
}
