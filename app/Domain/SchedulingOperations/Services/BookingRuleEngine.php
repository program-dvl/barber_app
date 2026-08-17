<?php

namespace App\Domain\SchedulingOperations\Services;

use App\Domain\BusinessConfiguration\Contracts\AvailabilityConfiguration;
use App\Domain\BusinessConfiguration\Models\PhysicalResource;
use App\Domain\BusinessConfiguration\Models\Service;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\Location;
use App\Domain\PlatformAccess\Models\StaffProfile;
use App\Domain\SchedulingOperations\Data\BookingLineRequest;
use App\Domain\SchedulingOperations\Data\BookingRequest;
use App\Domain\SchedulingOperations\Data\PlannedVisit;
use App\Domain\SchedulingOperations\Exceptions\BookingRuleViolation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingRuleEngine
{
    private const CAPACITY_STATUSES = [
        'pending_confirmation', 'confirmed', 'arrived', 'checked_in', 'in_service', 'late',
    ];

    public function __construct(private readonly AvailabilityConfiguration $configuration) {}

    public function plan(
        BookingRequest $request,
        bool $checkCapacity = true,
        ?int $excludeHoldId = null,
        ?int $excludeAppointmentId = null,
    ): PlannedVisit {
        if ($request->lines === []) {
            throw new BookingRuleViolation('EMPTY_VISIT', 'Select at least one service.');
        }
        if (! in_array($request->source, ['online', 'phone', 'reception', 'walk_in', 'waitlist', 'self_service', 'recurring', 'consultation'], true)
            || ! in_array($request->clientEligibility, ['new', 'existing'], true)
            || ($request->capacityOwnerKey !== null && ($request->capacityOwnerKey === '' || mb_strlen($request->capacityOwnerKey) > 128))
            || ($request->actorType !== null && ($request->actorType === '' || mb_strlen($request->actorType) > 64))) {
            throw new BookingRuleViolation('INVALID_VISIT', 'The booking request contains an unsupported source or client type.');
        }

        $business = Business::query()->find($request->businessId);
        $location = Location::query()->where('business_id', $request->businessId)->find($request->locationId);
        if (! $business?->isActive() || ! $location || ! $location->is_active || $location->status !== 'active') {
            throw new BookingRuleViolation('LOCATION_UNAVAILABLE', 'This location is not available for booking.');
        }

        $startsAt = $request->startsAtUtc->utc();
        $localStart = $startsAt->setTimezone($location->time_zone);
        $interval = max(1, (int) ($business->appointment_interval_minutes ?: 15));
        if ($localStart->second !== 0 || (($localStart->hour * 60) + $localStart->minute) % $interval !== 0) {
            throw new BookingRuleViolation('APPOINTMENT_INTERVAL', 'Choose a time aligned with the booking interval.');
        }

        $cursor = $startsAt;
        $plannedLines = [];
        $totalPrice = 0;
        $currency = null;
        foreach (array_values($request->lines) as $lineIndex => $lineRequest) {
            if (! $lineRequest instanceof BookingLineRequest) {
                throw new BookingRuleViolation('INVALID_VISIT', 'The selected services could not be read.');
            }
            $service = Service::query()->where('business_id', $business->id)->find($lineRequest->serviceId);
            if (! $service) {
                throw new BookingRuleViolation('SERVICE_UNAVAILABLE', 'A selected service is no longer available.', ['line' => $lineIndex + 1]);
            }
            if ($service->kind === 'addon' && ! $this->addonIsAllowed($service, $request)) {
                throw new BookingRuleViolation('ADDON_NOT_ELIGIBLE', 'A selected add-on cannot be combined with these services.', ['line' => $lineIndex + 1]);
            }

            $this->assertNoticeAndEligibility($service, $request, $startsAt, $lineIndex);
            $planned = $this->planLine($business, $location, $service, $lineRequest, $cursor, $request, $checkCapacity, $excludeHoldId, $excludeAppointmentId, $lineIndex);
            $plannedLines[] = $planned;
            $cursor = $planned['ends_at_utc'];
            $totalPrice += $planned['snapshot']['priceMinor'];
            $currency ??= $planned['snapshot']['currencyCode'];
            if ($currency !== $planned['snapshot']['currencyCode']) {
                throw new BookingRuleViolation('CURRENCY_MISMATCH', 'The selected services cannot be combined.');
            }
        }

        return new PlannedVisit($startsAt, $cursor, $location->time_zone, $plannedLines, $totalPrice, (string) $currency);
    }

    /** @return array<string, mixed> */
    private function planLine(
        Business $business,
        Location $location,
        Service $service,
        BookingLineRequest $lineRequest,
        CarbonImmutable $startsAt,
        BookingRequest $request,
        bool $checkCapacity,
        ?int $excludeHoldId,
        ?int $excludeAppointmentId,
        int $lineIndex,
    ): array {
        $candidateIds = $this->candidateStaffIds($business, $location, $service, $lineRequest);
        if ($candidateIds === []) {
            throw new BookingRuleViolation('STAFF_UNAVAILABLE', 'No qualified team member is available for this service.', ['line' => $lineIndex + 1]);
        }

        $lastViolation = null;
        foreach ($candidateIds as $candidateId) {
            try {
                $primary = StaffProfile::query()->where('business_id', $business->id)->findOrFail($candidateId);
                $effective = $this->configuration->resolveService($service, $primary, $location, $startsAt);
                if (in_array($request->source, ['online', 'waitlist', 'self_service'], true) && ! $effective->onlineVisible) {
                    throw new BookingRuleViolation('SERVICE_UNAVAILABLE', 'A selected service is not available for online booking.', ['line' => $lineIndex + 1]);
                }
                $segments = [];
                $cursor = $startsAt;
                foreach (array_values($effective->segments) as $segmentIndex => $segment) {
                    $sequence = (int) ($segment['sequence'] ?? ($segmentIndex + 1));
                    $duration = (int) ($segment['duration_minutes'] ?? 0);
                    if ($duration <= 0) {
                        continue;
                    }
                    $segmentEnds = $cursor->addMinutes($duration);
                    $occupiesStaff = (bool) ($segment['occupies_staff'] ?? true);
                    $staffId = $lineRequest->segmentStaffIds[$sequence] ?? $primary->id;
                    $staff = StaffProfile::query()->where('business_id', $business->id)->find($staffId);
                    if (! $staff) {
                        throw new BookingRuleViolation('STAFF_UNAVAILABLE', 'A selected team member is not available.', ['line' => $lineIndex + 1]);
                    }

                    // Every attributed provider must remain qualified even when a processing
                    // segment releases their capacity.
                    $this->configuration->resolveService($service, $staff, $location, $cursor);
                    $segments[] = [
                        'service_segment_id' => $segment['id'] ?? null,
                        'staff_profile_id' => $staff->id,
                        'kind' => (string) ($segment['kind'] ?? 'active'),
                        'sequence' => $sequence,
                        'starts_at_utc' => $cursor,
                        'ends_at_utc' => $segmentEnds,
                        'occupies_staff' => $occupiesStaff,
                    ];
                    $cursor = $segmentEnds;
                }
                if ($segments === []) {
                    throw new BookingRuleViolation('SERVICE_UNAVAILABLE', 'A selected service has no bookable duration.', ['line' => $lineIndex + 1]);
                }

                $segments = $this->applyDurationOverride($segments, $lineRequest, $lineIndex);
                foreach ($segments as $segment) {
                    $this->assertLocationAvailable($location, $segment['starts_at_utc'], $segment['ends_at_utc']);
                    if ($segment['occupies_staff']) {
                        $segmentStaff = StaffProfile::query()->where('business_id', $business->id)->findOrFail($segment['staff_profile_id']);
                        $this->assertStaffAvailable(
                            $segmentStaff,
                            $location,
                            $segment['starts_at_utc'],
                            $segment['ends_at_utc'],
                            $request->now(),
                            $checkCapacity,
                            $excludeHoldId,
                            $excludeAppointmentId,
                        );
                    }
                }
                $cursor = collect($segments)->sortByDesc('ends_at_utc')->first()['ends_at_utc'];

                $resourceClaims = $this->planResources(
                    $service,
                    $location,
                    $segments,
                    $startsAt,
                    $cursor,
                    $request->now(),
                    $checkCapacity,
                    $excludeHoldId,
                    $excludeAppointmentId,
                    $lineIndex,
                );

                $snapshot = $effective->snapshot();
                $snapshot['durationMinutes'] = (int) collect($segments)->where('kind', 'active')->sum(fn (array $segment) => $segment['starts_at_utc']->diffInMinutes($segment['ends_at_utc']));
                $snapshot['processingMinutes'] = (int) collect($segments)->where('kind', 'processing')->sum(fn (array $segment) => $segment['starts_at_utc']->diffInMinutes($segment['ends_at_utc']));
                $snapshot['cleanupMinutes'] = (int) collect($segments)->where('kind', 'cleanup')->sum(fn (array $segment) => $segment['starts_at_utc']->diffInMinutes($segment['ends_at_utc']));
                $snapshot['bookableMinutes'] = (int) $startsAt->diffInMinutes($cursor);

                return [
                    'service_id' => $service->id,
                    'primary_staff_profile_id' => $primary->id,
                    'starts_at_utc' => $startsAt,
                    'ends_at_utc' => $cursor,
                    'snapshot' => $snapshot,
                    'segments' => $segments,
                    'resource_claims' => $resourceClaims,
                ];
            } catch (ValidationException|BookingRuleViolation $exception) {
                if ($exception instanceof BookingRuleViolation) {
                    $lastViolation = $exception;
                }
                if ($lineRequest->preferredStaffId && ! $lineRequest->allowAnyQualified) {
                    if ($exception instanceof BookingRuleViolation && in_array($exception->ruleCode, ['NOTICE_WINDOW', 'ADVANCE_WINDOW', 'LOCATION_UNAVAILABLE', 'RESOURCE_UNAVAILABLE'], true)) {
                        throw $exception;
                    }

                    throw new BookingRuleViolation('STAFF_UNAVAILABLE', 'The selected team member is not available.', ['line' => $lineIndex + 1]);
                }
            }
        }

        if ($lastViolation && in_array($lastViolation->ruleCode, ['LOCATION_UNAVAILABLE', 'RESOURCE_UNAVAILABLE', 'SERVICE_UNAVAILABLE'], true)) {
            throw $lastViolation;
        }

        throw new BookingRuleViolation('STAFF_UNAVAILABLE', 'No qualified team member is available at this time.', ['line' => $lineIndex + 1]);
    }

    /** @return list<int> */
    private function candidateStaffIds(Business $business, Location $location, Service $service, BookingLineRequest $request): array
    {
        $ids = [];
        if ($request->preferredStaffId) {
            $ids[] = $request->preferredStaffId;
        }
        if ($request->allowAnyQualified || ! $request->preferredStaffId) {
            $qualified = DB::table('staff_service_assignments as assignments')
                ->join('staff_profiles', 'staff_profiles.id', '=', 'assignments.staff_profile_id')
                ->join('location_staff_profile as locations', function ($join) use ($location): void {
                    $join->on('locations.staff_profile_id', '=', 'staff_profiles.id')
                        ->where('locations.location_id', '=', $location->id);
                })
                ->where('assignments.business_id', $business->id)
                ->where('assignments.service_id', $service->id)
                ->where('assignments.is_active', true)
                ->where('assignments.is_qualified', true)
                ->where('staff_profiles.status', 'active')
                ->orderBy('staff_profiles.id')
                ->pluck('staff_profiles.id')->map(fn ($id) => (int) $id)->all();
            $ids = [...$ids, ...$qualified];
        }

        return array_values(array_unique($ids));
    }

    private function assertNoticeAndEligibility(Service $service, BookingRequest $request, CarbonImmutable $startsAt, int $lineIndex): void
    {
        $now = $request->now();
        $noticeApplies = ! in_array($request->source, ['walk_in', 'personal_block', 'staff_break'], true);
        if ($noticeApplies
            && $startsAt->lt($now->addMinutes((int) $service->minimum_notice_minutes))
            && ! $this->allowsPolicyOverride($request, 'NOTICE_WINDOW')) {
            throw new BookingRuleViolation('NOTICE_WINDOW', 'This time is inside the minimum booking notice.', ['line' => $lineIndex + 1]);
        }
        if ($startsAt->gt($now->addDays((int) $service->maximum_advance_days))
            && ! $this->allowsPolicyOverride($request, 'ADVANCE_WINDOW')) {
            throw new BookingRuleViolation('ADVANCE_WINDOW', 'This time is beyond the booking window.', ['line' => $lineIndex + 1]);
        }
        if ($service->client_eligibility !== 'all' && $service->client_eligibility !== $request->clientEligibility) {
            throw new BookingRuleViolation('CLIENT_ELIGIBILITY', 'This service is not available for the selected client type.', ['line' => $lineIndex + 1]);
        }
    }

    private function allowsPolicyOverride(BookingRequest $request, string $ruleCode): bool
    {
        return in_array($request->source, ['phone', 'reception', 'recurring', 'consultation'], true)
            && in_array($ruleCode, $request->overrideRuleCodes, true)
            && trim((string) $request->overrideReason) !== '';
    }

    private function addonIsAllowed(Service $addon, BookingRequest $request): bool
    {
        $selectedServiceIds = collect($request->lines)
            ->pluck('serviceId')
            ->reject(fn (int $serviceId) => $serviceId === $addon->id)
            ->all();

        return $selectedServiceIds !== [] && DB::table('service_addons')
            ->where('business_id', $request->businessId)
            ->where('addon_service_id', $addon->id)
            ->whereIn('service_id', $selectedServiceIds)
            ->exists();
    }

    /**
     * Operational resize changes the final active segment and shifts later
     * processing/cleanup segments. The adjusted plan is then checked by the
     * same availability and capacity rules as every other booking.
     *
     * @param  list<array<string, mixed>>  $segments
     * @return list<array<string, mixed>>
     */
    private function applyDurationOverride(array $segments, BookingLineRequest $request, int $lineIndex): array
    {
        if ($request->durationOverrideMinutes === null) {
            return $segments;
        }

        if ($request->durationOverrideMinutes < 5 || $request->durationOverrideMinutes > 720) {
            throw new BookingRuleViolation('INVALID_DURATION', 'The adjusted service duration is not allowed.', ['line' => $lineIndex + 1]);
        }

        $configuredMinutes = (int) collect($segments)->sum(
            fn (array $segment) => $segment['starts_at_utc']->diffInMinutes($segment['ends_at_utc'])
        );
        $delta = $request->durationOverrideMinutes - $configuredMinutes;
        if ($delta === 0) {
            return $segments;
        }

        $targetIndex = null;
        foreach ($segments as $index => $segment) {
            if ($segment['kind'] === 'active') {
                $targetIndex = $index;
            }
        }
        if ($targetIndex === null) {
            throw new BookingRuleViolation('INVALID_DURATION', 'This service cannot be resized.', ['line' => $lineIndex + 1]);
        }

        $targetMinutes = (int) $segments[$targetIndex]['starts_at_utc']->diffInMinutes($segments[$targetIndex]['ends_at_utc']);
        if (($targetMinutes + $delta) < 5) {
            throw new BookingRuleViolation('INVALID_DURATION', 'The adjusted service duration is too short.', ['line' => $lineIndex + 1]);
        }

        $segments[$targetIndex]['ends_at_utc'] = $segments[$targetIndex]['ends_at_utc']->addMinutes($delta);
        for ($index = $targetIndex + 1, $count = count($segments); $index < $count; $index++) {
            $segments[$index]['starts_at_utc'] = $segments[$index]['starts_at_utc']->addMinutes($delta);
            $segments[$index]['ends_at_utc'] = $segments[$index]['ends_at_utc']->addMinutes($delta);
        }

        return $segments;
    }

    private function assertLocationAvailable(Location $location, CarbonImmutable $startsAt, CarbonImmutable $endsAt): void
    {
        if (! $this->isCovered($startsAt, $endsAt, $location->time_zone, fn (CarbonImmutable $date) => $this->configuration->locationWindows($location, $date))) {
            throw new BookingRuleViolation('LOCATION_UNAVAILABLE', 'The visit falls outside this location’s available hours.');
        }
    }

    private function assertStaffAvailable(
        StaffProfile $staff,
        Location $location,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
        CarbonImmutable $asOf,
        bool $checkCapacity,
        ?int $excludeHoldId,
        ?int $excludeAppointmentId,
    ): void {
        if (! $this->isCovered($startsAt, $endsAt, $location->time_zone, fn (CarbonImmutable $date) => $this->configuration->staffWindows($staff, $location, $date))) {
            throw new BookingRuleViolation('STAFF_UNAVAILABLE', 'A qualified team member is not available at this time.');
        }
        if ($checkCapacity && $this->hasStaffConflict($staff->business_id, $staff->id, $startsAt, $endsAt, $asOf, $excludeHoldId, $excludeAppointmentId)) {
            throw new BookingRuleViolation('STAFF_UNAVAILABLE', 'A qualified team member is not available at this time.');
        }
    }

    /** @param list<array<string, mixed>> $segments @return list<array<string, mixed>> */
    private function planResources(
        Service $service,
        Location $location,
        array $segments,
        CarbonImmutable $lineStarts,
        CarbonImmutable $lineEnds,
        CarbonImmutable $asOf,
        bool $checkCapacity,
        ?int $excludeHoldId,
        ?int $excludeAppointmentId,
        int $lineIndex,
    ): array {
        $claims = [];
        foreach ($this->configuration->requiredCapacity($service) as $requirement) {
            if (! $requirement->satisfiable) {
                throw new BookingRuleViolation('RESOURCE_UNAVAILABLE', 'Required equipment or space is not available.', ['line' => $lineIndex + 1]);
            }
            $resource = PhysicalResource::query()->where('business_id', $service->business_id)->find($requirement->resourceId);
            if (! $resource || $resource->location_id !== $location->id || ! $resource->is_active) {
                throw new BookingRuleViolation('RESOURCE_UNAVAILABLE', 'Required equipment or space is not available.', ['line' => $lineIndex + 1]);
            }
            $segment = $requirement->segmentId
                ? collect($segments)->firstWhere('service_segment_id', $requirement->segmentId)
                : null;
            if ($requirement->segmentId && ! $segment) {
                // A staff variant may reduce a configured segment kind to zero.
                // Its segment-specific resource requirement then has no interval.
                continue;
            }
            $claimStarts = $segment['starts_at_utc'] ?? $lineStarts;
            $claimEnds = $segment['ends_at_utc'] ?? $lineEnds;
            if (! $this->isCovered($claimStarts, $claimEnds, $location->time_zone, fn (CarbonImmutable $date) => $this->configuration->resourceWindows($resource, $date))) {
                throw new BookingRuleViolation('RESOURCE_UNAVAILABLE', 'Required equipment or space is not available.', ['line' => $lineIndex + 1]);
            }
            if ($this->configuration->resourceMaintenance($resource, $claimStarts, $claimEnds) !== []) {
                throw new BookingRuleViolation('RESOURCE_UNAVAILABLE', 'Required equipment or space is not available.', ['line' => $lineIndex + 1]);
            }
            if ($checkCapacity && ! $this->hasResourceCapacity($resource, $claimStarts, $claimEnds, $requirement->quantity, $asOf, $excludeHoldId, $excludeAppointmentId)) {
                throw new BookingRuleViolation('RESOURCE_UNAVAILABLE', 'Required equipment or space is not available.', ['line' => $lineIndex + 1]);
            }
            $claims[] = [
                'physical_resource_id' => $resource->id,
                'service_segment_id' => $requirement->segmentId,
                'segment_sequence' => $segment['sequence'] ?? null,
                'quantity' => $requirement->quantity,
                'starts_at_utc' => $claimStarts,
                'ends_at_utc' => $claimEnds,
            ];
        }

        return $claims;
    }

    private function hasStaffConflict(
        int $businessId,
        int $staffId,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
        CarbonImmutable $asOf,
        ?int $excludeHoldId,
        ?int $excludeAppointmentId,
    ): bool {
        $appointmentConflict = DB::table('appointment_segments as segments')
            ->join('appointments', 'appointments.id', '=', 'segments.appointment_id')
            ->where('segments.business_id', $businessId)
            ->where('segments.staff_profile_id', $staffId)
            ->where('segments.occupies_staff', true)
            ->whereIn('appointments.status', self::CAPACITY_STATUSES)
            ->when($excludeAppointmentId, fn ($query) => $query->where('appointments.id', '!=', $excludeAppointmentId))
            ->where('segments.starts_at_utc', '<', $endsAt)
            ->where('segments.ends_at_utc', '>', $startsAt)
            ->exists();
        if ($appointmentConflict) {
            return true;
        }

        $blockConflict = DB::table('schedule_blocks')
            ->where('business_id', $businessId)
            ->where('staff_profile_id', $staffId)
            ->where('starts_at_utc', '<', $endsAt)
            ->where('ends_at_utc', '>', $startsAt)
            ->exists();
        if ($blockConflict) {
            return true;
        }

        return DB::table('capacity_hold_segments as segments')
            ->join('capacity_holds as holds', 'holds.id', '=', 'segments.capacity_hold_id')
            ->where('segments.business_id', $businessId)
            ->where('segments.staff_profile_id', $staffId)
            ->where('segments.occupies_staff', true)
            ->where('holds.status', 'active')
            ->where('holds.expires_at', '>', $asOf)
            ->when($excludeHoldId, fn ($query) => $query->where('holds.id', '!=', $excludeHoldId))
            ->where('segments.starts_at_utc', '<', $endsAt)
            ->where('segments.ends_at_utc', '>', $startsAt)
            ->exists();
    }

    private function hasResourceCapacity(
        PhysicalResource $resource,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
        int $needed,
        CarbonImmutable $asOf,
        ?int $excludeHoldId,
        ?int $excludeAppointmentId,
    ): bool {
        $appointments = (int) DB::table('appointment_resource_claims as claims')
            ->join('appointments', 'appointments.id', '=', 'claims.appointment_id')
            ->where('claims.business_id', $resource->business_id)
            ->where('claims.physical_resource_id', $resource->id)
            ->whereIn('appointments.status', self::CAPACITY_STATUSES)
            ->when($excludeAppointmentId, fn ($query) => $query->where('appointments.id', '!=', $excludeAppointmentId))
            ->where('claims.starts_at_utc', '<', $endsAt)
            ->where('claims.ends_at_utc', '>', $startsAt)
            ->sum('claims.quantity');
        $holds = (int) DB::table('capacity_hold_resource_claims as claims')
            ->join('capacity_holds as holds', 'holds.id', '=', 'claims.capacity_hold_id')
            ->where('claims.business_id', $resource->business_id)
            ->where('claims.physical_resource_id', $resource->id)
            ->where('holds.status', 'active')
            ->where('holds.expires_at', '>', $asOf)
            ->when($excludeHoldId, fn ($query) => $query->where('holds.id', '!=', $excludeHoldId))
            ->where('claims.starts_at_utc', '<', $endsAt)
            ->where('claims.ends_at_utc', '>', $startsAt)
            ->sum('claims.quantity');

        return ($appointments + $holds + $needed) <= $resource->quantity;
    }

    /**
     * Check real UTC instants against local wall-clock windows. Previous-day
     * windows are included so 22:00-02:00 schedules cross midnight correctly.
     *
     * @param  callable(CarbonImmutable): list<array{opens_at:string,closes_at:string,source:string}>  $windows
     */
    private function isCovered(CarbonImmutable $startsAt, CarbonImmutable $endsAt, string $timeZone, callable $windows): bool
    {
        $firstDate = $startsAt->setTimezone($timeZone)->startOfDay()->subDay();
        $lastDate = $endsAt->subSecond()->setTimezone($timeZone)->startOfDay();
        $ranges = [];
        for ($date = $firstDate; $date->lte($lastDate); $date = $date->addDay()) {
            foreach ($windows($date) as $window) {
                $open = $this->localWindowInstant($date, $window['opens_at'], $timeZone);
                $close = $this->localWindowInstant($date, $window['closes_at'], $timeZone);
                if (! $open || ! $close) {
                    continue;
                }
                if ($close->lte($open)) {
                    $close = $this->localWindowInstant($date->addDay(), $window['closes_at'], $timeZone);
                }
                if ($close) {
                    $ranges[] = [$open->utc(), $close->utc()];
                }
            }
        }

        usort($ranges, fn (array $left, array $right) => $left[0]->getTimestamp() <=> $right[0]->getTimestamp());
        $coveredUntil = $startsAt;
        foreach ($ranges as [$rangeStart, $rangeEnd]) {
            if ($rangeEnd->lte($coveredUntil) || $rangeStart->gt($coveredUntil)) {
                continue;
            }
            $coveredUntil = $rangeEnd;
            if ($coveredUntil->gte($endsAt)) {
                return true;
            }
        }

        return false;
    }

    private function localWindowInstant(CarbonImmutable $date, string $time, string $timeZone): ?CarbonImmutable
    {
        $clock = substr($time, 0, 8);
        if (strlen($clock) === 5) {
            $clock .= ':00';
        }
        $wall = $date->setTimezone($timeZone)->toDateString().' '.$clock;
        $instant = CarbonImmutable::createFromFormat('!Y-m-d H:i:s', $wall, $timeZone);

        return $instant && $instant->format('Y-m-d H:i:s') === $wall ? $instant : null;
    }
}
