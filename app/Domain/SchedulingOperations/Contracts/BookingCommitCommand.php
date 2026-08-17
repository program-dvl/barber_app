<?php

namespace App\Domain\SchedulingOperations\Contracts;

use App\Domain\SchedulingOperations\Data\BookingRequest;
use App\Domain\SchedulingOperations\Models\Appointment;
use Carbon\CarbonImmutable;

interface BookingCommitCommand
{
    public function commit(BookingRequest $request, string $idempotencyKey): Appointment;

    /** @param array<string, mixed> $appointmentDetails */
    public function confirmHold(int $businessId, string $holdPublicId, string $idempotencyKey, ?CarbonImmutable $asOfUtc = null, array $appointmentDetails = []): Appointment;
}
