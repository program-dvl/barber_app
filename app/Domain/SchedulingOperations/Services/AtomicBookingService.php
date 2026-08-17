<?php

namespace App\Domain\SchedulingOperations\Services;

use App\Domain\BusinessConfiguration\Models\PhysicalResource;
use App\Domain\BusinessConfiguration\Models\Service;
use App\Domain\ClientRecords\Contracts\ClientIdentityLinker;
use App\Domain\PlatformAccess\Models\Location;
use App\Domain\PlatformAccess\Models\StaffProfile;
use App\Domain\SchedulingOperations\Contracts\BookingCommitCommand;
use App\Domain\SchedulingOperations\Contracts\CapacityHoldCommand;
use App\Domain\SchedulingOperations\Contracts\CapacityHoldExpiryCommand;
use App\Domain\SchedulingOperations\Data\BookingLineRequest;
use App\Domain\SchedulingOperations\Data\BookingRequest;
use App\Domain\SchedulingOperations\Data\PlannedVisit;
use App\Domain\SchedulingOperations\Exceptions\BookingRuleViolation;
use App\Domain\SchedulingOperations\Models\Appointment;
use App\Domain\SchedulingOperations\Models\AppointmentChange;
use App\Domain\SchedulingOperations\Models\AppointmentResourceClaim;
use App\Domain\SchedulingOperations\Models\AppointmentSegment;
use App\Domain\SchedulingOperations\Models\AppointmentServiceLine;
use App\Domain\SchedulingOperations\Models\AppointmentStatusHistory;
use App\Domain\SchedulingOperations\Models\CapacityHold;
use App\Domain\SchedulingOperations\Models\CapacityHoldLine;
use App\Domain\SchedulingOperations\Models\CapacityHoldResourceClaim;
use App\Domain\SchedulingOperations\Models\CapacityHoldSegment;
use App\Domain\SchedulingOperations\Models\OperationalNotificationEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class AtomicBookingService implements BookingCommitCommand, CapacityHoldCommand, CapacityHoldExpiryCommand
{
    public function __construct(
        private readonly BookingRuleEngine $rules,
        private readonly ClientIdentityLinker $clients,
    ) {}

    public function hold(BookingRequest $request, string $idempotencyKey, int $ttlSeconds = 600): CapacityHold
    {
        $this->assertIdempotencyKey($idempotencyKey);
        if ($ttlSeconds < 30 || $ttlSeconds > 1800) {
            throw new BookingRuleViolation('INVALID_HOLD_TTL', 'The hold duration is not allowed.');
        }

        return DB::transaction(function () use ($request, $idempotencyKey, $ttlSeconds): CapacityHold {
            $key = $this->claimCommandKey($request->businessId, 'hold', $idempotencyKey, $request->hash());
            if ($key->result_id) {
                return CapacityHold::query()->where('business_id', $request->businessId)->findOrFail($key->result_id);
            }

            $this->lockCapacityRoots($request);
            $plan = $this->rules->plan($request);
            $hold = CapacityHold::query()->create([
                'business_id' => $request->businessId,
                'location_id' => $request->locationId,
                'idempotency_key' => $idempotencyKey,
                'request_hash' => $request->hash(),
                'status' => 'active',
                'source' => $request->source,
                'client_eligibility' => $request->clientEligibility,
                'owner_key' => $request->capacityOwnerKey ?: 'command:'.substr($request->hash(), 0, 32),
                'actor_type' => $request->actorType,
                'actor_id' => $request->actorId,
                'starts_at_utc' => $plan->startsAtUtc,
                'ends_at_utc' => $plan->endsAtUtc,
                'expires_at' => $request->now()->addSeconds($ttlSeconds),
            ]);
            $this->persistHoldPlan($hold, $plan);
            DB::table('booking_command_keys')->where('id', $key->id)->update([
                'result_type' => 'capacity_hold', 'result_id' => $hold->id, 'updated_at' => now(),
            ]);

            return $hold->fresh(['lines', 'segments', 'resourceClaims']);
        }, 5);
    }

    public function commit(BookingRequest $request, string $idempotencyKey): Appointment
    {
        $this->assertIdempotencyKey($idempotencyKey);

        return DB::transaction(function () use ($request, $idempotencyKey): Appointment {
            $key = $this->claimCommandKey($request->businessId, 'booking', $idempotencyKey, $request->hash());
            if ($key->result_id) {
                return Appointment::query()->where('business_id', $request->businessId)->findOrFail($key->result_id);
            }

            $this->lockCapacityRoots($request);
            $plan = $this->rules->plan($request);
            $appointment = $this->persistAppointment($request, $plan, $idempotencyKey, $request->hash());
            DB::table('booking_command_keys')->where('id', $key->id)->update([
                'result_type' => 'appointment', 'result_id' => $appointment->id, 'updated_at' => now(),
            ]);

            return $appointment->fresh(['serviceLines.segments', 'resourceClaims', 'statusHistory']);
        }, 5);
    }

    public function confirmHold(int $businessId, string $holdPublicId, string $idempotencyKey, ?CarbonImmutable $asOfUtc = null, array $appointmentDetails = []): Appointment
    {
        $this->assertIdempotencyKey($idempotencyKey);
        $now = ($asOfUtc ?? CarbonImmutable::now())->utc();
        $hash = hash('sha256', json_encode(['business_id' => $businessId, 'hold_public_id' => $holdPublicId, 'details' => $appointmentDetails], JSON_THROW_ON_ERROR));

        // Release by the persisted expiry instant before entering the
        // confirmation transaction. Throwing inside that transaction would
        // otherwise roll the expired status back with the error response.
        CapacityHold::query()
            ->where('business_id', $businessId)
            ->where('public_id', $holdPublicId)
            ->where('status', 'active')
            ->where('expires_at', '<=', $now)
            ->update(['status' => 'expired', 'expired_at' => $now, 'updated_at' => now()]);

        return DB::transaction(function () use ($businessId, $holdPublicId, $idempotencyKey, $now, $hash, $appointmentDetails): Appointment {
            $key = $this->claimCommandKey($businessId, 'booking', $idempotencyKey, $hash);
            if ($key->result_id) {
                return Appointment::query()->where('business_id', $businessId)->findOrFail($key->result_id);
            }

            $hold = CapacityHold::query()
                ->where('business_id', $businessId)
                ->where('public_id', $holdPublicId)
                ->lockForUpdate()
                ->first();
            if (! $hold) {
                throw new BookingRuleViolation('HOLD_NOT_FOUND', 'This booking hold is no longer available.');
            }
            if ($hold->appointment_id) {
                $appointment = Appointment::query()->where('business_id', $businessId)->findOrFail($hold->appointment_id);
                DB::table('booking_command_keys')->where('id', $key->id)->update([
                    'result_type' => 'appointment', 'result_id' => $appointment->id, 'updated_at' => now(),
                ]);

                return $appointment;
            }
            if ($hold->status !== 'active' || $hold->expires_at->lte($now)) {
                throw new BookingRuleViolation('HOLD_EXPIRED', 'This booking hold has expired. Choose another time.');
            }

            $hold->load(['lines.segments']);
            $request = $this->requestFromHold($hold, $now, $appointmentDetails);
            $this->lockCapacityRoots($request);
            $plan = $this->rules->plan($request, true, $hold->id);
            if (! $plan->endsAtUtc->equalTo($hold->ends_at_utc) || ! $this->holdPlanStillMatches($hold, $plan)) {
                throw new BookingRuleViolation('STALE_AVAILABILITY', 'Availability changed while this booking was held. Choose another time.');
            }

            $appointment = $this->persistAppointment($request, $plan, $idempotencyKey, $hash);
            $hold->update([
                'status' => 'confirmed', 'appointment_id' => $appointment->id, 'confirmed_at' => $now,
            ]);
            DB::table('booking_command_keys')->where('id', $key->id)->update([
                'result_type' => 'appointment', 'result_id' => $appointment->id, 'updated_at' => now(),
            ]);

            return $appointment->fresh(['serviceLines.segments', 'resourceClaims', 'statusHistory']);
        }, 5);
    }

    public function expire(CarbonImmutable $asOfUtc, ?int $businessId = null): int
    {
        return CapacityHold::query()
            ->where('status', 'active')
            ->where('expires_at', '<=', $asOfUtc->utc())
            ->when($businessId, fn ($query) => $query->where('business_id', $businessId))
            ->update(['status' => 'expired', 'expired_at' => $asOfUtc->utc(), 'updated_at' => now()]);
    }

    private function assertIdempotencyKey(string $key): void
    {
        if ($key === '' || mb_strlen($key) > 128) {
            throw new BookingRuleViolation('INVALID_IDEMPOTENCY_KEY', 'A valid idempotency key is required.');
        }
    }

    public function claimCommandKey(int $businessId, string $scope, string $key, string $hash): object
    {
        DB::table('booking_command_keys')->insertOrIgnore([
            'business_id' => $businessId,
            'scope' => $scope,
            'idempotency_key' => $key,
            'request_hash' => $hash,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $record = DB::table('booking_command_keys')
            ->where('business_id', $businessId)
            ->where('scope', $scope)
            ->where('idempotency_key', $key)
            ->lockForUpdate()
            ->first();
        if (! $record || ! hash_equals($record->request_hash, $hash)) {
            throw new BookingRuleViolation('IDEMPOTENCY_KEY_REUSED', 'This request key was already used for a different booking.');
        }

        return $record;
    }

    public function lockCapacityRoots(BookingRequest $request): void
    {
        Location::query()->where('business_id', $request->businessId)->whereKey($request->locationId)->sharedLock()->firstOrFail();
        $serviceIds = collect($request->lines)->pluck('serviceId')->unique()->sort()->values()->all();
        Service::query()->where('business_id', $request->businessId)->whereKey($serviceIds)->orderBy('id')->sharedLock()->get();

        $staffIds = collect($request->lines)
            ->flatMap(fn (BookingLineRequest $line) => array_filter([$line->preferredStaffId, ...array_values($line->segmentStaffIds)]))
            ->merge(DB::table('staff_service_assignments as assignments')
                ->join('location_staff_profile as locations', function ($join) use ($request): void {
                    $join->on('locations.staff_profile_id', '=', 'assignments.staff_profile_id')
                        ->where('locations.location_id', '=', $request->locationId);
                })
                ->where('assignments.business_id', $request->businessId)
                ->whereIn('assignments.service_id', $serviceIds)
                ->where('assignments.is_active', true)
                ->where('assignments.is_qualified', true)
                ->sharedLock()
                ->pluck('assignments.staff_profile_id'))
            ->map(fn ($id) => (int) $id)->unique()->sort()->values()->all();
        if ($staffIds !== []) {
            StaffProfile::query()->where('business_id', $request->businessId)->whereKey($staffIds)->orderBy('id')->lockForUpdate()->get();
        }

        $resourceIds = DB::table('service_resource_requirements')
            ->where('business_id', $request->businessId)
            ->whereIn('service_id', $serviceIds)
            ->orderBy('physical_resource_id')
            ->sharedLock()
            ->pluck('physical_resource_id')->unique()->all();
        if ($resourceIds !== []) {
            PhysicalResource::query()->where('business_id', $request->businessId)->whereKey($resourceIds)->orderBy('id')->lockForUpdate()->get();
        }
    }

    private function persistHoldPlan(CapacityHold $hold, PlannedVisit $plan): void
    {
        foreach ($plan->lines as $lineIndex => $plannedLine) {
            $line = CapacityHoldLine::query()->create([
                'business_id' => $hold->business_id,
                'capacity_hold_id' => $hold->id,
                'service_id' => $plannedLine['service_id'],
                'primary_staff_profile_id' => $plannedLine['primary_staff_profile_id'],
                'sequence' => $lineIndex + 1,
                'configuration_snapshot' => $plannedLine['snapshot'],
            ]);
            $segmentIds = [];
            foreach ($plannedLine['segments'] as $segment) {
                $stored = CapacityHoldSegment::query()->create([
                    'business_id' => $hold->business_id,
                    'capacity_hold_id' => $hold->id,
                    'capacity_hold_line_id' => $line->id,
                    'service_segment_id' => $segment['service_segment_id'],
                    'staff_profile_id' => $segment['staff_profile_id'],
                    'kind' => $segment['kind'],
                    'sequence' => $segment['sequence'],
                    'starts_at_utc' => $segment['starts_at_utc'],
                    'ends_at_utc' => $segment['ends_at_utc'],
                    'occupies_staff' => $segment['occupies_staff'],
                ]);
                $segmentIds[$segment['sequence']] = $stored->id;
            }
            foreach ($plannedLine['resource_claims'] as $claim) {
                CapacityHoldResourceClaim::query()->create([
                    'business_id' => $hold->business_id,
                    'capacity_hold_id' => $hold->id,
                    'capacity_hold_line_id' => $line->id,
                    'capacity_hold_segment_id' => $claim['segment_sequence'] ? ($segmentIds[$claim['segment_sequence']] ?? null) : null,
                    'physical_resource_id' => $claim['physical_resource_id'],
                    'quantity' => $claim['quantity'],
                    'starts_at_utc' => $claim['starts_at_utc'],
                    'ends_at_utc' => $claim['ends_at_utc'],
                ]);
            }
        }
    }

    public function persistAppointment(BookingRequest $request, PlannedVisit $plan, string $idempotencyKey, string $hash): Appointment
    {
        $now = $request->now();
        $appointment = Appointment::query()->create([
            'business_id' => $request->businessId,
            'location_id' => $request->locationId,
            'idempotency_key' => $idempotencyKey,
            'request_hash' => $hash,
            'status' => 'confirmed',
            'source' => $request->source,
            'client_name' => $request->clientName,
            'client_mobile' => $request->clientMobile,
            'client_email' => $request->clientEmail,
            'client_date_of_birth' => $request->clientDateOfBirth,
            'referral_source' => $request->referralSource,
            'communication_preferences' => $request->communicationPreferences,
            'marketing_opt_in' => $request->marketingOptIn,
            'special_request' => $request->specialRequest,
            'public_policy_snapshot' => $request->publicPolicySnapshot,
            'internal_notes' => $request->internalNotes,
            'starts_at_utc' => $plan->startsAtUtc,
            'ends_at_utc' => $plan->endsAtUtc,
            'time_zone' => $plan->timeZone,
            'local_starts_at' => $plan->startsAtUtc->setTimezone($plan->timeZone)->format('Y-m-d H:i:s P'),
            'local_ends_at' => $plan->endsAtUtc->setTimezone($plan->timeZone)->format('Y-m-d H:i:s P'),
            'price_minor' => $plan->priceMinor,
            'currency_code' => $plan->currencyCode,
            'confirmed_at' => $now,
        ]);

        foreach ($plan->lines as $lineIndex => $plannedLine) {
            $snapshot = $plannedLine['snapshot'];
            $line = AppointmentServiceLine::query()->create([
                'business_id' => $request->businessId,
                'appointment_id' => $appointment->id,
                'service_id' => $plannedLine['service_id'],
                'primary_staff_profile_id' => $plannedLine['primary_staff_profile_id'],
                'sequence' => $lineIndex + 1,
                'name' => $snapshot['name'],
                'price_minor' => $snapshot['priceMinor'],
                'currency_code' => $snapshot['currencyCode'],
                'bookable_minutes' => $snapshot['bookableMinutes'],
                'configuration_snapshot' => $snapshot,
            ]);
            $segmentIds = [];
            foreach ($plannedLine['segments'] as $segment) {
                $stored = AppointmentSegment::query()->create([
                    'business_id' => $request->businessId,
                    'appointment_id' => $appointment->id,
                    'appointment_service_line_id' => $line->id,
                    'service_segment_id' => $segment['service_segment_id'],
                    'staff_profile_id' => $segment['staff_profile_id'],
                    'kind' => $segment['kind'],
                    'sequence' => $segment['sequence'],
                    'starts_at_utc' => $segment['starts_at_utc'],
                    'ends_at_utc' => $segment['ends_at_utc'],
                    'time_zone' => $plan->timeZone,
                    'local_starts_at' => $segment['starts_at_utc']->setTimezone($plan->timeZone)->format('Y-m-d H:i:s P'),
                    'local_ends_at' => $segment['ends_at_utc']->setTimezone($plan->timeZone)->format('Y-m-d H:i:s P'),
                    'occupies_staff' => $segment['occupies_staff'],
                ]);
                $segmentIds[$segment['sequence']] = $stored->id;
            }
            foreach ($plannedLine['resource_claims'] as $claim) {
                AppointmentResourceClaim::query()->create([
                    'business_id' => $request->businessId,
                    'appointment_id' => $appointment->id,
                    'appointment_service_line_id' => $line->id,
                    'appointment_segment_id' => $claim['segment_sequence'] ? ($segmentIds[$claim['segment_sequence']] ?? null) : null,
                    'physical_resource_id' => $claim['physical_resource_id'],
                    'quantity' => $claim['quantity'],
                    'starts_at_utc' => $claim['starts_at_utc'],
                    'ends_at_utc' => $claim['ends_at_utc'],
                ]);
            }
        }
        AppointmentStatusHistory::query()->create([
            'business_id' => $request->businessId,
            'appointment_id' => $appointment->id,
            'previous_status' => null,
            'status' => 'confirmed',
            'source' => $request->source,
            'actor_type' => $request->actorType,
            'actor_id' => $request->actorId,
            'occurred_at' => $now,
        ]);
        $this->clients->linkAppointment($appointment);
        OperationalNotificationEvent::query()->firstOrCreate([
            'business_id' => $appointment->business_id,
            'idempotency_key' => 'appointment:'.$idempotencyKey.':created',
        ], [
            'event_type' => 'appointment.created', 'subject_type' => Appointment::class,
            'subject_id' => $appointment->id, 'payload' => [], 'status' => 'pending', 'occurred_at' => $now,
        ]);

        if ($request->overrideRuleCodes !== []) {
            AppointmentChange::query()->create([
                'business_id' => $request->businessId,
                'appointment_id' => $appointment->id,
                'kind' => 'manager_override',
                'source' => $request->source,
                'actor_type' => $request->actorType,
                'actor_id' => $request->actorId,
                'reason' => $request->overrideReason,
                'before' => [],
                'after' => ['overridden_rule_codes' => array_values($request->overrideRuleCodes)],
                'metadata' => ['warning_acknowledged' => true],
                'occurred_at' => $now,
            ]);
        }

        return $appointment;
    }

    /** @param array<string, mixed> $details */
    private function requestFromHold(CapacityHold $hold, CarbonImmutable $now, array $details = []): BookingRequest
    {
        $lines = $hold->lines->map(function (CapacityHoldLine $line): BookingLineRequest {
            $segmentStaff = $line->segments->mapWithKeys(fn (CapacityHoldSegment $segment) => [$segment->sequence => $segment->staff_profile_id])->all();

            return new BookingLineRequest($line->service_id, $line->primary_staff_profile_id, $segmentStaff, false);
        })->all();

        return new BookingRequest(
            $hold->business_id,
            $hold->location_id,
            $hold->starts_at_utc,
            $lines,
            $hold->source,
            $hold->client_eligibility,
            $now,
            $hold->owner_key,
            $hold->actor_type,
            $hold->actor_id,
            $details['client_name'] ?? null,
            $details['client_mobile'] ?? null,
            $details['internal_notes'] ?? null,
            [],
            null,
            $details['client_email'] ?? null,
            $details['client_date_of_birth'] ?? null,
            $details['referral_source'] ?? null,
            $details['communication_preferences'] ?? [],
            (bool) ($details['marketing_opt_in'] ?? false),
            $details['special_request'] ?? null,
            $details['public_policy_snapshot'] ?? [],
        );
    }

    private function holdPlanStillMatches(CapacityHold $hold, PlannedVisit $plan): bool
    {
        if ($hold->lines->count() !== count($plan->lines)) {
            return false;
        }
        foreach ($plan->lines as $index => $plannedLine) {
            $heldLine = $hold->lines[$index];
            if ($heldLine->service_id !== $plannedLine['service_id']
                || $heldLine->primary_staff_profile_id !== $plannedLine['primary_staff_profile_id']
                || $this->snapshotForComparison($heldLine->configuration_snapshot) !== $this->snapshotForComparison($plannedLine['snapshot'])) {
                return false;
            }
        }

        return true;
    }

    /** @param array<string, mixed> $snapshot @return array<string, mixed> */
    private function snapshotForComparison(array $snapshot): array
    {
        // resolvedAt records when a snapshot was observed, not a policy value.
        // A later confirmation must reject changed commercial/capacity fields,
        // but must not go stale merely because the clock advanced after hold.
        unset($snapshot['resolvedAt']);

        return $this->normalizeSnapshot($snapshot);
    }

    /** @param array<mixed> $value @return array<mixed> */
    private function normalizeSnapshot(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->normalizeSnapshot($item);
            }
        }
        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }
}
