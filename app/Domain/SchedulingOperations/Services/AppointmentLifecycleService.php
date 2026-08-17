<?php

namespace App\Domain\SchedulingOperations\Services;

use App\Domain\SchedulingOperations\Contracts\AppointmentLifecycleCommand;
use App\Domain\SchedulingOperations\Data\BookingRequest;
use App\Domain\SchedulingOperations\Enums\AppointmentStatus;
use App\Domain\SchedulingOperations\Exceptions\BookingRuleViolation;
use App\Domain\SchedulingOperations\Models\Appointment;
use App\Domain\SchedulingOperations\Models\AppointmentChange;
use App\Domain\SchedulingOperations\Models\AppointmentStatusHistory;
use App\Domain\SchedulingOperations\Models\OperationalNotificationEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class AppointmentLifecycleService implements AppointmentLifecycleCommand
{
    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        'pending_confirmation' => ['confirmed', 'cancelled_by_client', 'cancelled_by_shop'],
        'confirmed' => ['arrived', 'late', 'cancelled_by_client', 'cancelled_by_shop', 'no_show'],
        'late' => ['arrived', 'checked_in', 'in_service', 'cancelled_by_client', 'cancelled_by_shop', 'no_show'],
        'arrived' => ['checked_in', 'in_service', 'cancelled_by_client', 'cancelled_by_shop'],
        'checked_in' => ['in_service', 'cancelled_by_shop'],
        'in_service' => ['completed', 'cancelled_by_shop'],
        'completed' => [],
        'cancelled_by_client' => [],
        'cancelled_by_shop' => [],
        'no_show' => [],
        'rescheduled' => [],
    ];

    private const REASON_REQUIRED = [
        'late', 'cancelled_by_client', 'cancelled_by_shop', 'no_show', 'rescheduled',
    ];

    public function __construct(
        private readonly AtomicBookingService $bookings,
        private readonly BookingRuleEngine $rules,
    ) {}

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
    ): Appointment {
        $this->assertOperationKey($idempotencyKey);
        $occurredAt = ($occurredAt ?? CarbonImmutable::now())->utc();
        $hash = hash('sha256', json_encode([
            'appointment' => $appointment->id,
            'to' => $toStatus,
            'version' => $expectedVersion,
            'source' => $source,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'reason' => $reason,
        ], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($appointment, $toStatus, $idempotencyKey, $expectedVersion, $source, $actorType, $actorId, $reason, $occurredAt, $hash): Appointment {
            $key = $this->bookings->claimCommandKey($appointment->business_id, 'operation', $idempotencyKey, $hash);
            if ($key->result_id) {
                return Appointment::query()->where('business_id', $appointment->business_id)->findOrFail($key->result_id);
            }

            $current = Appointment::query()->where('business_id', $appointment->business_id)->lockForUpdate()->findOrFail($appointment->id);
            $this->assertVersion($current, $expectedVersion);
            $this->assertTransition($current->status, $toStatus, $reason);
            $fromStatus = $current->status;

            $updates = ['status' => $toStatus, 'version' => $current->version + 1];
            $timestampColumn = match ($toStatus) {
                'arrived' => 'arrived_at',
                'checked_in' => 'checked_in_at',
                'in_service' => 'service_started_at',
                'completed' => 'completed_at',
                'cancelled_by_client', 'cancelled_by_shop', 'no_show' => 'cancelled_at',
                default => null,
            };
            if ($timestampColumn) {
                $updates[$timestampColumn] = $occurredAt;
            }
            $current->update($updates);
            AppointmentStatusHistory::query()->create([
                'business_id' => $current->business_id,
                'appointment_id' => $current->id,
                'previous_status' => $fromStatus,
                'status' => $toStatus,
                'source' => $source,
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'reason' => $reason,
                'occurred_at' => $occurredAt,
            ]);
            $this->recordChange($current, 'status', $source, $actorType, $actorId, $reason, ['status' => $fromStatus], ['status' => $toStatus], $occurredAt);
            $this->notification($current, 'appointment.status_changed', $idempotencyKey, [
                'previous_status' => $fromStatus, 'status' => $toStatus,
            ], $occurredAt);
            DB::table('booking_command_keys')->where('id', $key->id)->update([
                'result_type' => 'appointment', 'result_id' => $current->id, 'updated_at' => now(),
            ]);

            return $current->fresh(['statusHistory', 'changes']);
        }, 5);
    }

    public function replace(
        Appointment $appointment,
        BookingRequest $request,
        string $kind,
        string $idempotencyKey,
        int $expectedVersion,
        string $reason,
    ): Appointment {
        $this->assertOperationKey($idempotencyKey);
        if (! in_array($kind, ['reschedule', 'resize', 'reassign', 'services_changed', 'duplicate', 'rebook'], true)) {
            throw new BookingRuleViolation('INVALID_OPERATION', 'This appointment change is not supported.');
        }
        if (trim($reason) === '') {
            throw new BookingRuleViolation('REASON_REQUIRED', 'Explain why this appointment is changing.');
        }
        if ((int) $appointment->business_id !== $request->businessId) {
            throw new BookingRuleViolation('TENANT_MISMATCH', 'The appointment could not be changed.');
        }

        $hash = hash('sha256', json_encode([
            'appointment' => $appointment->id,
            'kind' => $kind,
            'version' => $expectedVersion,
            'request_hash' => $request->hash(),
            'reason' => $reason,
        ], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($appointment, $request, $kind, $idempotencyKey, $expectedVersion, $reason, $hash): Appointment {
            $key = $this->bookings->claimCommandKey($appointment->business_id, 'operation', $idempotencyKey, $hash);
            if ($key->result_id) {
                return Appointment::query()->where('business_id', $appointment->business_id)->findOrFail($key->result_id);
            }

            $current = Appointment::query()->where('business_id', $appointment->business_id)->lockForUpdate()->findOrFail($appointment->id);
            $this->assertVersion($current, $expectedVersion);
            if (AppointmentStatus::from($current->status)->isTerminal()) {
                throw new BookingRuleViolation('TERMINAL_APPOINTMENT', 'This appointment can no longer be changed. Rebook it instead.');
            }

            $this->bookings->lockCapacityRoots($request);
            $plan = $this->rules->plan($request, true, null, $current->id);
            $replacement = $this->bookings->persistAppointment($request, $plan, $idempotencyKey, $hash);
            $replacement->update(['rescheduled_from_appointment_id' => $current->id]);

            $before = $this->snapshot($current);
            $fromStatus = $current->status;
            $current->update([
                'status' => AppointmentStatus::Rescheduled->value,
                'rescheduled_to_appointment_id' => $replacement->id,
                'version' => $current->version + 1,
            ]);
            AppointmentStatusHistory::query()->create([
                'business_id' => $current->business_id,
                'appointment_id' => $current->id,
                'previous_status' => $fromStatus,
                'status' => AppointmentStatus::Rescheduled->value,
                'source' => $request->source,
                'actor_type' => $request->actorType,
                'actor_id' => $request->actorId,
                'reason' => $reason,
                'occurred_at' => $request->now(),
            ]);
            $this->recordChange(
                $current,
                $kind,
                $request->source,
                $request->actorType,
                $request->actorId,
                $reason,
                $before,
                $this->snapshot($replacement),
                $request->now(),
                ['replacement_appointment_id' => $replacement->id, 'override_rule_codes' => $request->overrideRuleCodes],
            );
            $this->notification($replacement, 'appointment.'.$kind, $idempotencyKey, [
                'previous_appointment_public_id' => $current->public_id,
                'appointment_public_id' => $replacement->public_id,
            ], $request->now());
            DB::table('booking_command_keys')->where('id', $key->id)->update([
                'result_type' => 'appointment', 'result_id' => $replacement->id, 'updated_at' => now(),
            ]);

            return $replacement->fresh(['serviceLines.segments', 'resourceClaims', 'statusHistory', 'changes']);
        }, 5);
    }

    public function updateNotes(
        Appointment $appointment,
        ?string $notes,
        string $idempotencyKey,
        int $expectedVersion,
        string $source,
        ?string $actorType = null,
        ?int $actorId = null,
    ): Appointment {
        $this->assertOperationKey($idempotencyKey);
        $notes = $notes !== null ? trim($notes) : null;
        if ($notes !== null && mb_strlen($notes) > 5000) {
            throw new BookingRuleViolation('NOTES_TOO_LONG', 'The internal note is too long.');
        }
        $hash = hash('sha256', json_encode([
            'appointment' => $appointment->id, 'notes' => $notes, 'version' => $expectedVersion,
        ], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($appointment, $notes, $idempotencyKey, $expectedVersion, $source, $actorType, $actorId, $hash): Appointment {
            $key = $this->bookings->claimCommandKey($appointment->business_id, 'operation', $idempotencyKey, $hash);
            if ($key->result_id) {
                return Appointment::query()->where('business_id', $appointment->business_id)->findOrFail($key->result_id);
            }
            $current = Appointment::query()->where('business_id', $appointment->business_id)->lockForUpdate()->findOrFail($appointment->id);
            $this->assertVersion($current, $expectedVersion);
            $beforeHash = hash('sha256', (string) $current->internal_notes);
            $hadNotes = $current->internal_notes !== null;
            $current->update(['internal_notes' => $notes, 'version' => $current->version + 1]);
            $this->recordChange($current, 'notes', $source, $actorType, $actorId, null, [
                'present' => $hadNotes, 'content_hash' => $beforeHash,
            ], [
                'present' => $notes !== null, 'content_hash' => hash('sha256', (string) $notes),
            ], CarbonImmutable::now()->utc());
            DB::table('booking_command_keys')->where('id', $key->id)->update([
                'result_type' => 'appointment', 'result_id' => $current->id, 'updated_at' => now(),
            ]);

            return $current->fresh(['changes']);
        }, 5);
    }

    public function updateContact(
        Appointment $appointment,
        array $contact,
        string $idempotencyKey,
        int $expectedVersion,
        string $source,
        ?string $actorType = null,
        ?int $actorId = null,
    ): Appointment {
        $this->assertOperationKey($idempotencyKey);
        $normalized = [
            'client_name' => trim((string) ($contact['name'] ?? $appointment->client_name)),
            'client_mobile' => trim((string) ($contact['mobile'] ?? $appointment->client_mobile)),
            'client_email' => isset($contact['email']) ? strtolower(trim((string) $contact['email'])) : $appointment->client_email,
        ];
        if ($normalized['client_name'] === '' || $normalized['client_mobile'] === '') {
            throw new BookingRuleViolation('INVALID_CONTACT', 'Name and mobile number are required.');
        }
        $hash = hash('sha256', json_encode(['appointment' => $appointment->id, 'contact' => $normalized, 'version' => $expectedVersion], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($appointment, $normalized, $idempotencyKey, $expectedVersion, $source, $actorType, $actorId, $hash): Appointment {
            $key = $this->bookings->claimCommandKey($appointment->business_id, 'operation', $idempotencyKey, $hash);
            if ($key->result_id) {
                return Appointment::query()->where('business_id', $appointment->business_id)->findOrFail($key->result_id);
            }
            $current = Appointment::query()->where('business_id', $appointment->business_id)->lockForUpdate()->findOrFail($appointment->id);
            $this->assertVersion($current, $expectedVersion);
            $before = [
                'name_hash' => hash('sha256', (string) $current->client_name),
                'mobile_hash' => hash('sha256', (string) $current->client_mobile),
                'email_hash' => hash('sha256', (string) $current->client_email),
            ];
            $current->update([...$normalized, 'version' => $current->version + 1]);
            $after = [
                'name_hash' => hash('sha256', (string) $current->client_name),
                'mobile_hash' => hash('sha256', (string) $current->client_mobile),
                'email_hash' => hash('sha256', (string) $current->client_email),
            ];
            $this->recordChange($current, 'contact', $source, $actorType, $actorId, null, $before, $after, CarbonImmutable::now()->utc());
            $this->notification($current, 'appointment.contact_changed', $idempotencyKey, ['contact_changed' => true], CarbonImmutable::now()->utc());
            DB::table('booking_command_keys')->where('id', $key->id)->update(['result_type' => 'appointment', 'result_id' => $current->id, 'updated_at' => now()]);

            return $current->fresh('changes');
        }, 5);
    }

    private function assertTransition(string $from, string $to, ?string $reason): void
    {
        if (! isset(self::TRANSITIONS[$from]) || ! in_array($to, self::TRANSITIONS[$from], true)) {
            throw new BookingRuleViolation('INVALID_STATUS_TRANSITION', 'That appointment status change is not allowed.');
        }
        if (in_array($to, self::REASON_REQUIRED, true) && trim((string) $reason) === '') {
            throw new BookingRuleViolation('REASON_REQUIRED', 'Explain why this status is changing.');
        }
    }

    private function assertVersion(Appointment $appointment, int $expectedVersion): void
    {
        if ($expectedVersion < 1 || $appointment->version !== $expectedVersion) {
            throw new BookingRuleViolation('STALE_APPOINTMENT', 'This appointment changed in another session. Refresh and try again.');
        }
    }

    private function assertOperationKey(string $key): void
    {
        if ($key === '' || mb_strlen($key) > 128) {
            throw new BookingRuleViolation('INVALID_IDEMPOTENCY_KEY', 'A valid request key is required.');
        }
    }

    /** @return array<string, mixed> */
    private function snapshot(Appointment $appointment): array
    {
        return [
            'public_id' => $appointment->public_id,
            'status' => $appointment->status,
            'location_id' => $appointment->location_id,
            'starts_at_utc' => $appointment->starts_at_utc->toIso8601String(),
            'ends_at_utc' => $appointment->ends_at_utc->toIso8601String(),
            'service_ids' => $appointment->serviceLines()->pluck('service_id')->all(),
            'staff_ids' => $appointment->segments()->pluck('staff_profile_id')->filter()->unique()->values()->all(),
            'price_minor' => $appointment->price_minor,
            'currency_code' => $appointment->currency_code,
        ];
    }

    /** @param array<string, mixed> $before @param array<string, mixed> $after @param array<string, mixed> $metadata */
    private function recordChange(
        Appointment $appointment,
        string $kind,
        string $source,
        ?string $actorType,
        ?int $actorId,
        ?string $reason,
        array $before,
        array $after,
        CarbonImmutable $occurredAt,
        array $metadata = [],
    ): void {
        AppointmentChange::query()->create([
            'business_id' => $appointment->business_id,
            'appointment_id' => $appointment->id,
            'kind' => $kind,
            'source' => $source,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'reason' => $reason,
            'before' => $before,
            'after' => $after,
            'metadata' => $metadata,
            'occurred_at' => $occurredAt,
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function notification(Appointment $appointment, string $type, string $key, array $payload, CarbonImmutable $occurredAt): void
    {
        OperationalNotificationEvent::query()->firstOrCreate([
            'business_id' => $appointment->business_id,
            'idempotency_key' => 'appointment:'.$key.':'.$type,
        ], [
            'event_type' => $type,
            'subject_type' => Appointment::class,
            'subject_id' => $appointment->id,
            'payload' => $payload,
            'status' => 'pending',
            'occurred_at' => $occurredAt,
        ]);
    }
}
