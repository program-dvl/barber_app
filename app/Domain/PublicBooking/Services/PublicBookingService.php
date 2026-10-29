<?php

namespace App\Domain\PublicBooking\Services;

use App\Domain\BusinessConfiguration\Models\Service;
use App\Domain\BusinessConfiguration\Services\StaffPhotoService;
use App\Domain\ClientRecords\Services\ClientIdentityService;
use App\Domain\MoneyCommerce\Services\DepositPolicyService;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\Location;
use App\Domain\PlatformAccess\Models\StaffProfile;
use App\Domain\PublicBooking\Models\PublicBookingEvent;
use App\Domain\PublicBooking\Models\PublicBookingFlow;
use App\Domain\SchedulingOperations\Contracts\AvailabilityQuery;
use App\Domain\SchedulingOperations\Contracts\BookingCommitCommand;
use App\Domain\SchedulingOperations\Contracts\CapacityHoldCommand;
use App\Domain\SchedulingOperations\Data\AvailabilitySearch;
use App\Domain\SchedulingOperations\Data\BookingLineRequest;
use App\Domain\SchedulingOperations\Data\BookingRequest;
use App\Domain\SchedulingOperations\Exceptions\BookingRuleViolation;
use App\Domain\SchedulingOperations\Models\Appointment;
use App\Support\Locations\LocationData;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PublicBookingService
{
    public function __construct(
        private readonly AvailabilityQuery $availability,
        private readonly CapacityHoldCommand $holds,
        private readonly BookingCommitCommand $bookings,
        private readonly SecureAppointmentLinkService $links,
        private readonly DepositPolicyService $deposits,
    ) {}

    /** @return array{flow:PublicBookingFlow,secret:string} */
    public function start(Business $business): array
    {
        $this->assertBookable($business);
        $secret = bin2hex(random_bytes(32));
        $flow = PublicBookingFlow::query()->create([
            'business_id' => $business->id,
            'secret_hash' => hash('sha256', $secret),
            'status' => 'started',
            'last_step' => 1,
            'policy_version' => max(1, (int) $business->public_booking_policy_version),
            'state' => [],
            'expires_at' => now()->addHours(2),
        ]);
        $this->track($business, $flow, 'booking_started', hash('sha256', $secret));

        return compact('flow', 'secret');
    }

    public function resolve(Business $business, string $publicId, string $secret): PublicBookingFlow
    {
        $flow = PublicBookingFlow::query()->where('business_id', $business->id)->where('public_id', $publicId)->first();
        abort_unless($flow && preg_match('/^[a-f0-9]{64}$/', $secret) && hash_equals($flow->secret_hash, hash('sha256', $secret)), 404);
        if ($flow->expires_at->isPast() && $flow->status !== 'confirmed') {
            $flow->forceFill(['status' => 'expired'])->save();
            throw new BookingRuleViolation('BOOKING_FLOW_EXPIRED', 'This booking session expired. Start again to see current availability.');
        }

        return $flow;
    }

    /** @param list<string> $servicePublicIds */
    public function search(Business $business, string $locationPublicId, array $servicePublicIds, ?string $staffPublicId, string $fromDate, string $untilDate, string $clientEligibility): array
    {
        [$location, $services, $staff, $lines] = $this->resolveSelection($business, $locationPublicId, $servicePublicIds, $staffPublicId);
        if ($clientEligibility === 'auto') {
            $clientEligibility = $this->provisionalEligibility($business, $services);
        }
        $this->assertClientRule($business, $services, $clientEligibility);

        $slots = $this->availability->search(new AvailabilitySearch(
            $business->id,
            $location->id,
            CarbonImmutable::parse($fromDate, $location->time_zone),
            CarbonImmutable::parse($untilDate, $location->time_zone),
            $lines,
            'online',
            $clientEligibility,
            200,
        ));

        return array_map(fn (array $slot) => $this->publicSlot($slot), $slots);
    }

    public function publicSlot(array $slot): array
    {
        // Scheduling plans contain internal IDs, resource claims and private
        // configuration snapshots. Only appointment choices cross this boundary.
        return collect($slot)->only(['starts_at_utc', 'ends_at_utc', 'local_starts_at', 'local_ends_at', 'time_zone', 'price_minor', 'currency_code'])->all();
    }

    /** @param array<string, mixed> $selection */
    public function hold(Business $business, PublicBookingFlow $flow, array $selection, string $idempotencyKey): PublicBookingFlow
    {
        $selection = collect($selection)->only(['location', 'services', 'staff', 'starts_at', 'client_eligibility'])->all();

        return DB::transaction(function () use ($business, $flow, $selection, $idempotencyKey): PublicBookingFlow {
            $business = Business::query()->lockForUpdate()->findOrFail($business->id);
            $flow = PublicBookingFlow::query()->where('business_id', $business->id)->lockForUpdate()->findOrFail($flow->id);
            if ($flow->status === 'confirmed') {
                throw new BookingRuleViolation('FLOW_CONFIRMED', 'This appointment is already confirmed. Open your appointment link.');
            }
            if ($flow->expires_at->isPast()) {
                throw new BookingRuleViolation('BOOKING_FLOW_EXPIRED', 'This booking session expired. Choose a new time.');
            }
            [$location, $services, $staff, $lines] = $this->resolveSelection($business, $selection['location'], $selection['services'], $selection['staff'] ?? null);
            if ($selection['client_eligibility'] === 'auto') {
                $selection['client_eligibility'] = $this->provisionalEligibility($business, $services);
            }
            if ($flow->hold?->idempotency_key === $idempotencyKey) {
                foreach (['location', 'services', 'staff', 'starts_at', 'client_eligibility'] as $key) {
                    if (($flow->state[$key] ?? null) !== ($selection[$key] ?? null)) {
                        throw new BookingRuleViolation('IDEMPOTENCY_KEY_REUSED', 'This request was already used for a different time.');
                    }
                }

                return $flow;
            }
            $this->assertClientRule($business, $services, $selection['client_eligibility']);
            $startsAt = CarbonImmutable::parse($selection['starts_at'])->utc();
            $request = new BookingRequest(
                $business->id, $location->id, $startsAt, $lines, 'online', $selection['client_eligibility'], null,
                'public-flow:'.$flow->public_id,
            );
            // Retire the former hold atomically. A failed replacement rolls back,
            // leaving the customer's previous choice intact.
            $this->expireHold($flow);
            $hold = $this->holds->hold($request, $idempotencyKey, 600);
            if ($hold->status !== 'active' || $hold->expires_at->isPast()) {
                throw new BookingRuleViolation('HOLD_EXPIRED', 'Choose that time again to reserve it.');
            }
            $hold->load('lines');
            $policy = $this->policySnapshot($business, $location, $hold->lines->pluck('configuration_snapshot')->all(), $selection['client_eligibility']);
            $staffNames = StaffProfile::query()->where('business_id', $business->id)->whereIn('id', $hold->lines->pluck('primary_staff_profile_id'))->pluck('display_name', 'id');
            foreach ($hold->lines as $index => $line) {
                $policy['services'][$index]['staff_name'] = $staffNames[$line->primary_staff_profile_id] ?? null;
            }
            $flow->forceFill([
                'capacity_hold_id' => $hold->id,
                'status' => 'held',
                'last_step' => 3,
                'policy_version' => max(1, (int) $business->public_booking_policy_version),
                'state' => [...$selection, 'policy' => $policy, 'hold_expires_at' => $hold->expires_at->toIso8601String()],
                'expires_at' => $hold->expires_at,
            ])->save();
            $this->track($business, $flow, 'slot_held', $flow->secret_hash, ['service_count' => count($services)]);

            return $flow->fresh('hold.lines');
        }, 3);
    }

    public function release(Business $business, PublicBookingFlow $flow): PublicBookingFlow
    {
        return DB::transaction(function () use ($business, $flow): PublicBookingFlow {
            $business = Business::query()->lockForUpdate()->findOrFail($business->id);
            $flow = PublicBookingFlow::query()->where('business_id', $business->id)->lockForUpdate()->findOrFail($flow->id);
            $this->assertBookable($business);
            if ($flow->status === 'confirmed') {
                throw new BookingRuleViolation('FLOW_CONFIRMED', 'Your appointment is already confirmed.');
            }
            $this->expireHold($flow);
            $flow->forceFill(['capacity_hold_id' => null, 'status' => 'started', 'last_step' => 2, 'state' => [], 'expires_at' => now()->addHours(2)])->save();

            return $flow->fresh();
        }, 3);
    }

    private function expireHold(PublicBookingFlow $flow): void
    {
        if (! $flow->capacity_hold_id) {
            return;
        }
        if (DB::table('payment_intents')->where('capacity_hold_id', $flow->capacity_hold_id)->whereIn('status', ['pending', 'processing', 'succeeded'])->exists()) {
            throw new BookingRuleViolation('PAYMENT_IN_PROGRESS', 'A payment is being checked. Contact the business before changing this appointment.');
        }
        $flow->hold()->where('business_id', $flow->business_id)->where('status', 'active')->update(['status' => 'expired', 'expires_at' => now(), 'expired_at' => now()]);
    }

    private function provisionalEligibility(Business $business, Collection $services): string
    {
        if ($business->online_new_client_rule === 'existing_only' || $services->contains('client_eligibility', 'existing')
            || ($business->online_new_client_rule === 'consultation_only' && $services->contains(fn ($service) => ! $service->consultation_required))) {
            return 'existing';
        }

        return 'new';
    }

    public function reviewDetails(Business $business, PublicBookingFlow $flow, array $details): PublicBookingFlow
    {
        return DB::transaction(function () use ($business, $flow, $details): PublicBookingFlow {
            $business = Business::query()->lockForUpdate()->findOrFail($business->id);
            $this->assertBookable($business);
            $flow = PublicBookingFlow::query()->where('business_id', $business->id)->lockForUpdate()->findOrFail($flow->id);
            if ($flow->status !== 'held' || $flow->expires_at->isPast()) {
                throw new BookingRuleViolation('HOLD_EXPIRED', 'Your reserved time ended. Choose another time.');
            }
            $client = app(ClientIdentityService::class)->bookingIdentity($business, ['name' => $details['client_name'], 'mobile' => $details['client_mobile'], 'email' => $details['client_email']]);
            $eligibility = $client ? 'existing' : 'new';
            $hold = $flow->hold()->where('business_id', $business->id)->lockForUpdate()->firstOrFail();
            $hold->load('lines');
            $services = Service::query()->where('business_id', $business->id)->whereIn('id', $hold->lines->pluck('service_id'))->get();
            if (($eligibility === 'new' && $business->online_new_client_rule === 'existing_only')
                || ($eligibility === 'new' && $business->online_new_client_rule === 'consultation_only' && $services->contains(fn ($service) => ! $service->consultation_required))
                || $services->contains(fn ($service) => $service->client_eligibility !== 'all' && $service->client_eligibility !== $eligibility)) {
                throw ValidationException::withMessages(['booking' => 'These services have a first-visit restriction. Choose another service or contact the business to arrange your appointment.']);
            }
            $hold->forceFill(['client_eligibility' => $eligibility])->save();
            $policy = $flow->state['policy'];
            $deposit = $this->deposits->resolve($business, $hold->lines->pluck('configuration_snapshot')->all(), $eligibility, $client?->id);
            $policy['deposit'] = $this->publicDeposit($deposit);
            $policy['deposit_status'] = $deposit['amount_minor'] > 0 ? 'payment_required' : 'not_required';
            $flow->forceFill(['state' => [...$flow->state, 'client_eligibility' => $eligibility, 'policy' => $policy, 'details_fingerprint' => $this->detailsFingerprint($details)]])->save();

            return $flow->fresh();
        }, 3);
    }

    private function detailsFingerprint(array $details): string
    {
        return hash_hmac('sha256', json_encode(collect($details)->only(['client_name', 'client_mobile', 'client_email'])->all(), JSON_THROW_ON_ERROR), config('app.key'));
    }

    private function publicDeposit(array $deposit): array
    {
        return collect($deposit)->except(['prior_no_show_count', 'client_eligibility'])->all();
    }

    /** @param array<string, mixed> $details @return array{appointment:Appointment,view_url:string,calendar_url:string} */
    public function confirm(Business $business, PublicBookingFlow $flow, array $details, string $idempotencyKey, ?int $paidDepositIntentId = null): array
    {
        return DB::transaction(function () use ($business, $flow, $details, $idempotencyKey, $paidDepositIntentId): array {
            $business = Business::query()->lockForUpdate()->findOrFail($business->id);
            $flow = PublicBookingFlow::query()->where('business_id', $business->id)->lockForUpdate()->findOrFail($flow->id);
            if ($flow->status === 'confirmed' && $flow->appointment_id) {
                $appointment = Appointment::query()->where('business_id', $business->id)->findOrFail($flow->appointment_id);
            } else {
                $this->assertBookable($business);
                if (isset($flow->state['details_fingerprint']) && ! hash_equals($flow->state['details_fingerprint'], $this->detailsFingerprint($details))) {
                    throw new BookingRuleViolation('DETAILS_REVIEW_REQUIRED', 'Your details changed. Review the appointment again.');
                }
                if (isset($flow->state['details_fingerprint']) && ! $paidDepositIntentId) {
                    $displayedDeposit = $flow->state['policy']['deposit']['amount_minor'];
                    $flow = $this->reviewDetails($business, $flow, $details);
                    if ($displayedDeposit !== $flow->state['policy']['deposit']['amount_minor']) {
                        throw new BookingRuleViolation('DETAILS_REVIEW_REQUIRED', 'The booking policy changed. Review your appointment again.');
                    }
                }
                if ($flow->expires_at->isPast()) {
                    throw new BookingRuleViolation('BOOKING_FLOW_EXPIRED', 'This booking session expired. Choose a new time.');
                }
                if ($flow->policy_version !== max(1, (int) $business->public_booking_policy_version)) {
                    throw new BookingRuleViolation('POLICY_CHANGED', 'Booking policies changed. Review the current policy before confirming.');
                }
                if (($flow->state['policy']['deposit_status'] ?? 'not_required') === 'payment_required' && ! $paidDepositIntentId) {
                    throw new BookingRuleViolation('PAYMENT_REQUIRED', 'Complete the displayed deposit payment before confirming this booking.');
                }
                if (! $flow->capacity_hold_id || $flow->status !== 'held') {
                    throw new BookingRuleViolation('HOLD_REQUIRED', 'Choose and hold an available time before confirming.');
                }
                $hold = $flow->hold()->where('business_id', $business->id)->firstOrFail();
                $location = Location::query()->where('business_id', $business->id)->sharedLock()->findOrFail($hold->location_id);
                $shownLocation = $flow->state['policy']['location_details'] ?? null;
                // MySQL JSON storage sorts object keys. Compare keyed values,
                // preserving strict types without treating key order as a change.
                $shownAddress = Arr::except($shownLocation ?? [], ['directions_url', 'apple_maps_url', 'is_primary']);
                $currentAddress = Arr::except(LocationData::snapshot($location), ['is_primary']);
                ksort($shownAddress);
                ksort($currentAddress);
                if ($shownLocation && $shownAddress !== $currentAddress) {
                    throw new BookingRuleViolation('POLICY_CHANGED', 'This branch updated its location details. Choose your time again to review the current address.');
                }
                $appointment = $this->bookings->confirmHold($business->id, $hold->public_id, $idempotencyKey, null, [
                    ...$details,
                    'internal_notes' => $details['special_request'] ?? null,
                    'public_policy_snapshot' => $flow->state['policy'] ?? [],
                ]);
                $flow->forceFill(['appointment_id' => $appointment->id, 'status' => 'confirmed', 'last_step' => 5, 'confirmed_at' => now()])->save();
                $this->track($business, $flow, 'booking_completed', $flow->secret_hash, ['source' => 'online'], $appointment);
            }
            $view = $this->links->issue($appointment->loadMissing('business'), 'view');

            return [
                'appointment' => $appointment,
                'view_url' => route('public.appointment.view', $view['token']),
                'calendar_url' => route('public.appointment.calendar', $view['token']),
            ];
        }, 3);
    }

    /** @return array<string, mixed> */
    public function catalog(Business $business): array
    {
        $this->assertBookable($business);

        return $this->catalogData($business);
    }

    /** Read-only catalog for the separately authorized owner preview. */
    public function previewCatalog(Business $business): array
    {
        abort_unless($business->isActive(), 404);

        return $this->catalogData($business, privatePreview: true);
    }

    private function catalogData(Business $business, bool $privatePreview = false): array
    {
        $locations = Location::query()->where('business_id', $business->id)->where('is_active', true)->where('status', 'active')->with('hours')->orderByDesc('is_primary')->orderBy('name')->get();
        $services = Service::query()->where('business_id', $business->id)->where('is_active', true)->where('online_visible', true)->where(fn ($q) => $q->whereNull('effective_from')->orWhere('effective_from', '<=', now()))->where(fn ($q) => $q->whereNull('effective_until')->orWhere('effective_until', '>', now()))->with(['category', 'locations', 'addons'])->get()->sortBy(fn ($s) => sprintf('%05d', $s->category?->display_order ?? 65535).'|'.($s->category?->name ?? '').'|'.$s->kind.'|'.$s->name)->values();
        $staff = StaffProfile::query()->where('business_id', $business->id)->where('status', 'active')->where('online_visible', true)->with(['locations', 'serviceAssignments'])->orderBy('display_name')->get();

        $locations = $locations->filter(fn (Location $location) => $services->contains(fn (Service $service) => $service->kind === 'service'
            && $service->locations->contains(fn ($branch) => $branch->id === $location->id && $branch->pivot->is_eligible)
            && $staff->contains(fn (StaffProfile $profile) => $profile->locations->contains('id', $location->id)
                && $profile->serviceAssignments->contains(fn ($assignment) => $assignment->service_id === $service->id && $assignment->is_active && $assignment->is_qualified && $assignment->online_visible && (! $assignment->effective_from || $assignment->effective_from->lte(now())) && (! $assignment->effective_until || $assignment->effective_until->gt(now()))))))->values();

        return [
            'locations' => $locations->map(function (Location $location) {
                $today = CarbonImmutable::now($location->time_zone)->toDateString();

                return [...LocationData::present(LocationData::snapshot($location)), 'today' => $today,
                    'opening_hours' => $location->hours->filter(fn ($hour) => (! $hour->effective_from || $hour->effective_from->toDateString() <= $today)
                        && (! $hour->effective_until || $hour->effective_until->toDateString() >= $today))
                        ->sortBy(fn ($hour) => $hour->day_of_week.'|'.$hour->opens_at)
                        ->map(fn ($hour) => ['day' => (int) $hour->day_of_week % 7, 'opens_at' => substr($hour->opens_at, 0, 5), 'closes_at' => substr($hour->closes_at, 0, 5)])->values(),
                ];
            }),
            'services' => $services->map(fn (Service $service) => [
                'public_id' => $service->public_id, 'kind' => $service->kind, 'name' => $service->name,
                'category' => $service->category?->name ?: 'Services',
                'description' => $service->description, 'price_type' => match ($business->online_price_display) {
                    'exact' => 'fixed', 'from' => 'from', default => $service->price_type,
                },
                'price_minor' => $service->price_minor, 'currency_code' => $service->currency_code,
                'duration_minutes' => $service->duration_minutes + $service->processing_minutes + $service->cleanup_minutes,
                'visit_minutes' => $service->duration_minutes + $service->processing_minutes,
                'active_minutes' => $service->duration_minutes, 'processing_minutes' => $service->processing_minutes, 'cleanup_minutes' => $service->cleanup_minutes,
                'location_prices' => $service->locations->filter(fn ($l) => $l->pivot->is_eligible && $l->is_active && $l->status === 'active')->map(fn ($l) => ['location' => $l->public_id, 'price_minor' => $l->pivot->price_minor])->values(),
                'deposit_type' => $service->deposit_type, 'deposit_value' => $service->deposit_value,
                'client_eligibility' => $service->client_eligibility,
                'consultation_required' => $service->consultation_required,
                'maximum_advance_days' => $service->maximum_advance_days,
                'location_ids' => $service->locations->filter(fn ($location) => $location->pivot->is_eligible && $location->is_active && $location->status === 'active')->pluck('public_id')->all(),
                'addon_ids' => $service->addons->where('is_active', true)->where('online_visible', true)->pluck('public_id')->all(),
            ]),
            'staff' => $staff->map(fn (StaffProfile $profile) => [
                'public_id' => $profile->public_id, 'display_name' => $profile->display_name,
                'title' => $profile->title, 'biography' => $profile->biography,
                'photo_url' => app(StaffPhotoService::class)->url($business, $profile, $privatePreview),
                'location_ids' => $profile->locations->pluck('public_id')->all(),
                'service_ids' => $services->whereIn('id', $profile->serviceAssignments->filter(fn ($a) => $a->is_active && $a->is_qualified && $a->online_visible && (! $a->effective_from || $a->effective_from->lte(now())) && (! $a->effective_until || $a->effective_until->gt(now())))->sortByDesc('effective_from')->unique('service_id')->pluck('service_id'))->pluck('public_id')->all(),
                'service_variants' => $profile->serviceAssignments->filter(fn ($a) => $a->is_active && $a->is_qualified && $a->online_visible && (! $a->effective_from || $a->effective_from->lte(now())) && (! $a->effective_until || $a->effective_until->gt(now())))->sortByDesc('effective_from')->unique('service_id')->filter(fn ($a) => $services->contains('id', $a->service_id))->map(fn ($a) => ['service' => $services->firstWhere('id', $a->service_id)->public_id, ...$a->only(['price_minor', 'duration_minutes', 'processing_minutes', 'cleanup_minutes'])])->values(),
            ]),
            // The preview cannot proceed to client/payment review. Use the
            // saved selection rules without initializing commerce records.
            'policy' => $privatePreview
                ? $business->only(['online_staff_preference', 'online_price_display', 'online_new_client_rule', 'staff_gender_request_enabled'])
                : $this->policySnapshot($business, null, []),
        ];
    }

    /** @return array{0:Location,1:Collection<int,Service>,2:?StaffProfile,3:list<BookingLineRequest>} */
    private function resolveSelection(Business $business, string $locationPublicId, array $servicePublicIds, ?string $staffPublicId): array
    {
        $this->assertBookable($business);
        $location = Location::query()->where('business_id', $business->id)->where('public_id', $locationPublicId)->where('is_active', true)->where('status', 'active')->firstOrFail();
        $ids = array_values(array_unique($servicePublicIds));
        $services = Service::query()->where('business_id', $business->id)->whereIn('public_id', $ids)->where('is_active', true)->where('online_visible', true)->with('locations')->get()->keyBy('public_id');
        if ($services->count() !== count($ids) || ! $services->every(fn ($service) => $service->locations->contains(fn ($branch) => $branch->id === $location->id && $branch->pivot->is_eligible))) {
            throw ValidationException::withMessages(['services' => 'These services are unavailable at this branch. Choose current branch services.']);
        }
        $staff = $staffPublicId ? StaffProfile::query()->where('business_id', $business->id)->where('public_id', $staffPublicId)->whereHas('locations', fn ($query) => $query->where('locations.id', $location->id))->where('status', 'active')->where('online_visible', true)->firstOrFail() : null;
        if ($staff && $business->online_staff_preference === 'any_only') {
            abort(422, 'This business offers first-available booking only.');
        }
        if (! $staff && $business->online_staff_preference === 'preferred_required') {
            abort(422, 'Choose a staff member before searching.');
        }
        $lines = collect($ids)->map(fn (string $id) => new BookingLineRequest($services[$id]->id, $staff?->id, [], $staff === null))->all();

        return [$location, $services, $staff, $lines];
    }

    /** @param list<array<string, mixed>> $snapshots @return array<string, mixed> */
    private function policySnapshot(Business $business, ?Location $location, array $snapshots, string $clientEligibility = 'existing'): array
    {
        $deposit = $this->deposits->resolve($business, $snapshots, $clientEligibility);

        return [
            'version' => max(1, (int) $business->public_booking_policy_version),
            'online_staff_preference' => $business->online_staff_preference,
            'online_price_display' => $business->online_price_display,
            'online_new_client_rule' => $business->online_new_client_rule,
            'staff_gender_request_enabled' => $business->staff_gender_request_enabled,
            'cancellation_cutoff_minutes' => $deposit['cancellation_cutoff_minutes'],
            'cancellation_policy' => $business->default_cancellation_policy,
            'deposit' => $this->publicDeposit($deposit),
            'deposit_refundability' => $deposit['deposit_refundable_before_cutoff'],
            'cancellation_fee_minor' => $deposit['cancellation_fee_minor'],
            'no_show_fee_minor' => $deposit['no_show_fee_minor'],
            'terms_url' => $business->terms_url,
            'privacy_url' => $business->privacy_url,
            'marketing_wording' => 'Optional: send me marketing updates. Booking messages do not depend on this choice.',
            'sms_wording' => 'Send essential appointment texts, including confirmations, important changes, cancellations, and one reminder. Message and data rates may apply. Reply STOP to opt out.',
            'location_time_zone' => $location?->time_zone,
            'location_details' => $location ? LocationData::present(LocationData::snapshot($location)) : null,
            'services' => collect($snapshots)->map(fn (array $snapshot) => [
                'name' => $snapshot['name'], 'price_type' => match ($business->online_price_display) {
                    'exact' => 'fixed', 'from' => 'from', default => $snapshot['priceType'],
                }, 'price_minor' => $snapshot['priceMinor'],
                'currency_code' => $snapshot['currencyCode'], 'bookable_minutes' => $snapshot['bookableMinutes'],
                'visit_minutes' => $snapshot['durationMinutes'] + $snapshot['processingMinutes'],
                'deposit_type' => $snapshot['depositType'], 'deposit_minor' => $snapshot['depositMinor'],
            ])->all(),
            'deposit_status' => $deposit['amount_minor'] > 0 ? 'payment_required' : 'not_required',
        ];
    }

    private function assertBookable(Business $business): void
    {
        abort_unless($business->configuration_published_at && $business->online_booking_enabled && $business->isActive(), 404);
    }

    private function assertClientRule(Business $business, $services, string $eligibility): void
    {
        if ($services->contains(fn (Service $service) => $service->client_eligibility !== 'all' && $service->client_eligibility !== $eligibility)) {
            throw ValidationException::withMessages(['services' => 'First-visit and returning-customer services need separate appointments.']);
        }
        if ($eligibility === 'new' && $business->online_new_client_rule === 'existing_only') {
            abort(422, 'Online booking is currently available to returning clients only.');
        }
        if ($eligibility === 'new' && $business->online_new_client_rule === 'consultation_only' && $services->contains(fn (Service $service) => ! $service->consultation_required)) {
            abort(422, 'New clients must choose a consultation service first.');
        }
    }

    private function track(Business $business, ?PublicBookingFlow $flow, string $event, string $sessionHash, array $metadata = [], ?Appointment $appointment = null): void
    {
        PublicBookingEvent::query()->create([
            'business_id' => $business->id, 'public_booking_flow_id' => $flow?->id, 'appointment_id' => $appointment?->id,
            'event_name' => $event, 'session_hash' => $sessionHash, 'metadata' => $metadata, 'occurred_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $metadata */
    public function record(Business $business, PublicBookingFlow $flow, string $event, string $secret, array $metadata = []): void
    {
        abort_unless(hash_equals($flow->secret_hash, hash('sha256', $secret)), 404);
        $this->track($business, $flow, $event, $flow->secret_hash, $metadata);
    }
}
