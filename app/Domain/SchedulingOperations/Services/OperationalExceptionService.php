<?php

namespace App\Domain\SchedulingOperations\Services;

use App\Domain\SchedulingOperations\Models\Appointment;
use App\Domain\SchedulingOperations\Models\OperationalException;
use App\Domain\SchedulingOperations\Models\OperationalNotificationEvent;
use Carbon\CarbonImmutable;

class OperationalExceptionService
{
    public function recordAppointmentImpact(
        Appointment $appointment,
        string $kind,
        string $reason,
        ?CarbonImmutable $projectedEnd = null,
        ?string $actorType = null,
        ?int $actorId = null,
    ): OperationalException {
        if (! in_array($kind, ['late_arrival', 'service_overrun', 'staff_unavailable'], true) || trim($reason) === '') {
            throw new \InvalidArgumentException('A supported exception and reason are required.');
        }
        $impactEnd = ($projectedEnd ?? $appointment->ends_at_utc)->utc();
        $staffIds = $appointment->segments()->where('occupies_staff', true)->pluck('staff_profile_id')->filter()->unique();
        $affected = Appointment::query()
            ->where('business_id', $appointment->business_id)
            ->where('location_id', $appointment->location_id)
            ->whereKeyNot($appointment->id)
            ->whereIn('status', ['pending_confirmation', 'confirmed', 'arrived', 'checked_in', 'late'])
            ->where('starts_at_utc', '<', $impactEnd)
            ->whereHas('segments', fn ($segments) => $segments->whereIn('staff_profile_id', $staffIds))
            ->orderBy('starts_at_utc')
            ->pluck('public_id')
            ->all();

        $exception = OperationalException::query()->create([
            'business_id' => $appointment->business_id,
            'location_id' => $appointment->location_id,
            'appointment_id' => $appointment->id,
            'kind' => $kind,
            'status' => 'open',
            'reason' => trim($reason),
            'impact' => ['projected_end_utc' => $impactEnd->toIso8601String(), 'affected_appointments' => $affected],
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'occurred_at' => CarbonImmutable::now()->utc(),
        ]);
        if ($affected !== []) {
            OperationalNotificationEvent::query()->create([
                'business_id' => $appointment->business_id,
                'event_type' => 'appointment.operational_impact',
                'subject_type' => Appointment::class,
                'subject_id' => $appointment->id,
                'payload' => ['kind' => $kind, 'affected_appointments' => $affected],
                'status' => 'pending',
                'idempotency_key' => 'operational-exception:'.$exception->id,
                'occurred_at' => $exception->occurred_at,
            ]);
        }

        return $exception;
    }

    public function unexpectedClosure(
        int $businessId,
        int $locationId,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
        string $reason,
        ?string $actorType = null,
        ?int $actorId = null,
    ): OperationalException {
        $affected = Appointment::query()
            ->where('business_id', $businessId)
            ->where('location_id', $locationId)
            ->whereIn('status', ['pending_confirmation', 'confirmed', 'arrived', 'checked_in', 'late'])
            ->where('starts_at_utc', '<', $endsAt->utc())
            ->where('ends_at_utc', '>', $startsAt->utc())
            ->orderBy('starts_at_utc')
            ->pluck('public_id')
            ->all();

        $exception = OperationalException::query()->create([
            'business_id' => $businessId,
            'location_id' => $locationId,
            'kind' => 'unexpected_closure',
            'status' => 'open',
            'reason' => trim($reason),
            'impact' => [
                'starts_at_utc' => $startsAt->utc()->toIso8601String(),
                'ends_at_utc' => $endsAt->utc()->toIso8601String(),
                'affected_appointments' => $affected,
                'recovery_actions' => ['contact', 'reschedule', 'cancel'],
            ],
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'occurred_at' => CarbonImmutable::now()->utc(),
        ]);

        OperationalNotificationEvent::query()->create([
            'business_id' => $businessId,
            'event_type' => 'location.unexpected_closure',
            'subject_type' => OperationalException::class,
            'subject_id' => $exception->id,
            'payload' => ['affected_appointments' => $affected, 'recovery_actions' => ['contact', 'reschedule', 'cancel']],
            'status' => 'pending',
            'idempotency_key' => 'unexpected-closure:'.$exception->id,
            'occurred_at' => $exception->occurred_at,
        ]);

        return $exception;
    }
}
