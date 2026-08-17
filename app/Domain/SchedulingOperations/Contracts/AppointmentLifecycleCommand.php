<?php

namespace App\Domain\SchedulingOperations\Contracts;

use App\Domain\SchedulingOperations\Data\BookingRequest;
use App\Domain\SchedulingOperations\Models\Appointment;
use Carbon\CarbonImmutable;

interface AppointmentLifecycleCommand
{
    public function transition(
        Appointment $appointment,
        string $toStatus,
        string $idempotencyKey,
        int $expectedVersion,
        string $source,
        ?string $actorType = null,
        ?int $actorId = null,
        ?string $reason = null,
        ?CarbonImmutable $occurredAt = null,
    ): Appointment;

    public function replace(
        Appointment $appointment,
        BookingRequest $request,
        string $kind,
        string $idempotencyKey,
        int $expectedVersion,
        string $reason,
    ): Appointment;

    public function updateNotes(
        Appointment $appointment,
        ?string $notes,
        string $idempotencyKey,
        int $expectedVersion,
        string $source,
        ?string $actorType = null,
        ?int $actorId = null,
    ): Appointment;

    /** @param array{name?:string,mobile?:string,email?:string} $contact */
    public function updateContact(
        Appointment $appointment,
        array $contact,
        string $idempotencyKey,
        int $expectedVersion,
        string $source,
        ?string $actorType = null,
        ?int $actorId = null,
    ): Appointment;
}
