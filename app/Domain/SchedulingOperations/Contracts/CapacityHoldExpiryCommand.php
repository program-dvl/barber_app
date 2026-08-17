<?php

namespace App\Domain\SchedulingOperations\Contracts;

use Carbon\CarbonImmutable;

interface CapacityHoldExpiryCommand
{
    public function expire(CarbonImmutable $asOfUtc, ?int $businessId = null): int;
}
