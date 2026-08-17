<?php

namespace App\Domain\PublicBooking\Services;

use App\Domain\BusinessConfiguration\Models\Service;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\Location;
use App\Domain\PlatformAccess\Models\StaffProfile;
use App\Domain\PublicBooking\Models\WaitlistMatch;
use App\Domain\PublicBooking\Models\WaitlistRequest;
use App\Domain\SchedulingOperations\Contracts\AvailabilityQuery;
use App\Domain\SchedulingOperations\Contracts\BookingCommitCommand;
use App\Domain\SchedulingOperations\Data\AvailabilitySearch;
use App\Domain\SchedulingOperations\Data\BookingLineRequest;
use App\Domain\SchedulingOperations\Data\BookingRequest;
use App\Domain\SchedulingOperations\Exceptions\BookingRuleViolation;
use App\Domain\SchedulingOperations\Models\Appointment;
use App\Domain\SchedulingOperations\Models\OperationalNotificationEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WaitlistService
{
    public function __construct(private readonly AvailabilityQuery $availability, private readonly BookingCommitCommand $bookings) {}

    /** @param array<string, mixed> $data */
    public function create(Business $business, Location $location, Service $service, ?StaffProfile $staff, array $data): WaitlistRequest
    {
        abort_unless($location->business_id === $business->id && $service->business_id === $business->id && (! $staff || $staff->business_id === $business->id), 404);
        $originAppointmentId = isset($data['origin_appointment_id']) ? (int) $data['origin_appointment_id'] : null;
        if ($originAppointmentId) {
            abort_unless(Appointment::query()->where('business_id', $business->id)->whereKey($originAppointmentId)->exists(), 404);
        }
        $fingerprint = hash('sha256', json_encode([
            strtolower(trim($data['client_mobile'])), strtolower(trim((string) ($data['client_email'] ?? ''))),
            $location->id, $service->id, $staff?->id, $originAppointmentId, $data['acceptable_from'], $data['acceptable_until'],
            $data['time_from'], $data['time_until'], $data['notification_method'],
        ], JSON_THROW_ON_ERROR));

        return WaitlistRequest::query()->firstOrCreate(
            ['business_id' => $business->id, 'active_dedupe_key' => $fingerprint],
            [
                'location_id' => $location->id, 'service_id' => $service->id,
                'preferred_staff_profile_id' => $staff?->id, 'origin_appointment_id' => $originAppointmentId,
                'client_name' => $data['client_name'],
                'client_mobile' => $data['client_mobile'], 'client_email' => $data['client_email'] ?? null,
                'acceptable_from' => $data['acceptable_from'], 'acceptable_until' => $data['acceptable_until'],
                'time_from' => $data['time_from'], 'time_until' => $data['time_until'],
                'notification_method' => $data['notification_method'], 'notes' => $data['notes'] ?? null,
                'status' => 'active', 'expires_at' => CarbonImmutable::parse($data['acceptable_until'], $location->time_zone)->endOfDay()->utc(),
            ],
        );
    }

    /** @return list<array{match:WaitlistMatch,token:string}> */
    public function offerForOpening(Appointment $cancelled, int $ttlMinutes = 15): array
    {
        $cancelled->loadMissing(['location', 'serviceLines.segments', 'business']);
        $this->expireOffers(CarbonImmutable::now(), $cancelled->business_id);
        $line = $cancelled->serviceLines->first();
        if (! $line) {
            return [];
        }
        $local = $cancelled->starts_at_utc->setTimezone($cancelled->time_zone);
        $time = $local->format('H:i:s');
        $candidates = WaitlistRequest::query()
            ->where('business_id', $cancelled->business_id)->where('location_id', $cancelled->location_id)
            ->where('service_id', $line->service_id)->where('status', 'active')->where('expires_at', '>', now())
            ->whereDate('acceptable_from', '<=', $local->toDateString())->whereDate('acceptable_until', '>=', $local->toDateString())
            ->where('time_from', '<=', $time)->where('time_until', '>=', $time)
            ->orderBy('created_at')->limit(max(1, (int) $cancelled->business->waitlist_offer_batch_size) * 5)->get();
        $eligible = $candidates->filter(function (WaitlistRequest $request) use ($cancelled, $local): bool {
            $staffId = $request->preferred_staff_profile_id ?: $cancelled->segments->where('occupies_staff', true)->first()?->staff_profile_id;
            $slots = $this->availability->search(new AvailabilitySearch(
                $cancelled->business_id, $cancelled->location_id, $local->startOfDay(), $local->startOfDay(),
                [new BookingLineRequest($request->service_id, $staffId, [], $staffId === null)], 'waitlist', 'existing', 20,
            ));

            return collect($slots)->contains(fn (array $slot) => CarbonImmutable::parse($slot['starts_at_utc'])->equalTo($cancelled->starts_at_utc));
        })->take(max(1, (int) $cancelled->business->waitlist_offer_batch_size));
        if ($eligible->isEmpty()) {
            return [];
        }
        $batch = (string) Str::ulid();

        return DB::transaction(function () use ($eligible, $cancelled, $batch, $ttlMinutes): array {
            $offers = [];
            foreach ($eligible as $request) {
                $token = bin2hex(random_bytes(32));
                $match = WaitlistMatch::query()->create([
                    'business_id' => $cancelled->business_id, 'waitlist_request_id' => $request->id,
                    'staff_profile_id' => $request->preferred_staff_profile_id ?: $cancelled->segments->where('occupies_staff', true)->first()?->staff_profile_id,
                    'batch_id' => $batch, 'claim_token_hash' => hash('sha256', $token), 'status' => 'offered',
                    'slot_starts_at_utc' => $cancelled->starts_at_utc, 'slot_ends_at_utc' => $cancelled->ends_at_utc,
                    'offered_at' => now(), 'expires_at' => now()->addMinutes($ttlMinutes),
                ]);
                $request->forceFill(['status' => 'offered', 'version' => $request->version + 1])->save();
                OperationalNotificationEvent::query()->create([
                    'business_id' => $cancelled->business_id, 'event_type' => 'waitlist.slot_offered',
                    'subject_type' => WaitlistMatch::class, 'subject_id' => $match->id,
                    'payload' => ['match_public_id' => $match->public_id, 'expires_at' => $match->expires_at->toIso8601String(), 'method' => $request->notification_method],
                    'idempotency_key' => 'waitlist-offer-'.$match->public_id, 'occurred_at' => now(),
                ]);
                $offers[] = compact('match', 'token');
            }

            return $offers;
        }, 5);
    }

    public function resolveClaim(string $token): WaitlistMatch
    {
        abort_unless(preg_match('/^[a-f0-9]{64}$/', $token), 404);
        $match = WaitlistMatch::query()->with(['request.location', 'request.service', 'staff'])->where('claim_token_hash', hash('sha256', $token))->first();
        abort_unless($match, 404);
        $this->expireOffers(CarbonImmutable::now(), $match->business_id);

        return $match->fresh(['request.location', 'request.service', 'staff']);
    }

    public function claim(string $token): Appointment
    {
        $resolved = $this->resolveClaim($token);

        return $this->claimResolved($resolved);
    }

    public function claimResolved(WaitlistMatch $resolved): Appointment
    {
        abort_unless($resolved->business_id && $resolved->id, 404);
        $this->expireOffers(CarbonImmutable::now(), $resolved->business_id);
        $resolved = WaitlistMatch::query()->where('business_id', $resolved->business_id)->findOrFail($resolved->id);

        return DB::transaction(function () use ($resolved): Appointment {
            $batch = WaitlistMatch::query()->where('business_id', $resolved->business_id)->where('batch_id', $resolved->batch_id)->orderBy('id')->lockForUpdate()->get();
            $match = $batch->firstWhere('id', $resolved->id);
            if (! $match || $match->status !== 'offered' || $match->expires_at->isPast()) {
                throw new BookingRuleViolation('WAITLIST_OFFER_EXPIRED', 'This waitlist offer is no longer available.');
            }
            if ($batch->contains(fn (WaitlistMatch $candidate) => $candidate->status === 'claimed')) {
                throw new BookingRuleViolation('WAITLIST_SLOT_CLAIMED', 'Another client has already claimed this opening.');
            }
            $requests = WaitlistRequest::query()->where('business_id', $match->business_id)
                ->whereKey($batch->pluck('waitlist_request_id')->unique()->sort()->values()->all())
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $request = $requests->get($match->waitlist_request_id);
            if (! $request) {
                throw new BookingRuleViolation('WAITLIST_REQUEST_MISSING', 'This waitlist request is no longer available.');
            }
            $booking = new BookingRequest(
                $request->business_id, $request->location_id, $match->slot_starts_at_utc,
                [new BookingLineRequest($request->service_id, $match->staff_profile_id, [], $match->staff_profile_id === null)],
                'waitlist', 'existing', null, 'waitlist:'.$match->batch_id, null, null,
                $request->client_name, $request->client_mobile, $request->notes, [], null, $request->client_email,
            );
            $appointment = $this->bookings->commit($booking, 'waitlist-claim-'.$match->public_id);
            $match->forceFill(['status' => 'claimed', 'appointment_id' => $appointment->id, 'claimed_at' => now()])->save();
            $request->forceFill(['status' => 'booked', 'active_dedupe_key' => null, 'version' => $request->version + 1])->save();
            $losers = $batch->where('id', '!=', $match->id)->where('status', 'offered');
            if ($losers->isNotEmpty()) {
                WaitlistMatch::query()->whereKey($losers->pluck('id')->all())->update(['status' => 'lost', 'updated_at' => now()]);
            }
            foreach ($losers as $loser) {
                $losingRequest = $requests->get($loser->waitlist_request_id);
                if ($losingRequest?->status === 'offered') {
                    $stillEligible = $losingRequest->expires_at->isFuture();
                    $losingRequest->forceFill([
                        'status' => $stillEligible ? 'active' : 'expired',
                        'active_dedupe_key' => $stillEligible ? $losingRequest->active_dedupe_key : null,
                        'version' => $losingRequest->version + 1,
                    ])->save();
                }
            }

            return $appointment;
        }, 5);
    }

    public function expireOffers(?CarbonImmutable $asOfUtc = null, ?int $businessId = null): int
    {
        $asOf = ($asOfUtc ?? CarbonImmutable::now())->utc();

        return DB::transaction(function () use ($asOf, $businessId): int {
            $matches = WaitlistMatch::query()->where('status', 'offered')->where('expires_at', '<=', $asOf)
                ->when($businessId, fn ($query) => $query->where('business_id', $businessId))
                ->orderBy('id')->lockForUpdate()->get();
            if ($matches->isEmpty()) {
                return 0;
            }
            $requests = WaitlistRequest::query()->whereIn('id', $matches->pluck('waitlist_request_id')->unique())
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            foreach ($matches as $match) {
                $match->forceFill(['status' => 'expired'])->save();
                $request = $requests->get($match->waitlist_request_id);
                if ($request?->status === 'offered') {
                    $stillEligible = $request->expires_at->gt($asOf);
                    $request->forceFill([
                        'status' => $stillEligible ? 'active' : 'expired',
                        'active_dedupe_key' => $stillEligible ? $request->active_dedupe_key : null,
                        'version' => $request->version + 1,
                    ])->save();
                }
            }

            return $matches->count();
        }, 5);
    }

    public function leave(WaitlistRequest $request, int $expectedVersion): WaitlistRequest
    {
        return DB::transaction(function () use ($request, $expectedVersion): WaitlistRequest {
            $locked = WaitlistRequest::query()
                ->where('business_id', $request->business_id)
                ->lockForUpdate()
                ->findOrFail($request->id);
            if ($locked->version !== $expectedVersion || ! in_array($locked->status, ['active', 'offered'], true)) {
                throw new BookingRuleViolation('STALE_WAITLIST', 'This waitlist request changed. Refresh before trying again.');
            }
            $locked->forceFill(['status' => 'cancelled', 'active_dedupe_key' => null, 'version' => $locked->version + 1])->save();
            $locked->matches()->where('status', 'offered')->update(['status' => 'revoked', 'updated_at' => now()]);

            return $locked;
        }, 5);
    }
}
