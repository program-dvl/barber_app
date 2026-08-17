<?php

namespace App\Domain\SchedulingOperations\Contracts;

use App\Domain\SchedulingOperations\Data\BookingRequest;
use App\Domain\SchedulingOperations\Models\CapacityHold;

interface CapacityHoldCommand
{
    public function hold(BookingRequest $request, string $idempotencyKey, int $ttlSeconds = 600): CapacityHold;
}
