<?php

namespace App\Domain\PublicBooking\Services;

use App\Domain\BusinessConfiguration\Models\Service;
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
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

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
        $this->assertClientRule($business, $services, $clientEligibility);

        return $this->availability->search(new AvailabilitySearch(
            $business->id,
            $location->id,
            CarbonImmutable::parse($fromDate, $location->time_zone),
            CarbonImmutable::parse($untilDate, $location->time_zone),
            $lines,
            'online',
            $clientEligibility,
            80,
        ));
    }

    /** @param array<string, mixed> $selection */
    public function hold(Business $business, PublicBookingFlow $flow, array $selection, string $idempotencyKey): PublicBookingFlow
    {
        [$location, $services, $staff, $lines] = $this->resolveSelection($business, $selection['location'], $selection['services'], $selection['staff'] ?? null);
        $this->assertClientRule($business, $services, $selection['client_eligibility']);
        $startsAt = CarbonImmutable::parse($selection['starts_at'])->utc();
        $request = new BookingRequest(
            $business->id, $location->id, $startsAt, $lines, 'online', $selection['client_eligibility'], null,
            'public-flow:'.$flow->public_id,
        );
        $hold = $this->holds->hold($request, $idempotencyKey, 600);
        $hold->load('lines');
        $policy = $this->policySnapshot($business, $location, $hold->lines->pluck('configuration_snapshot')->all(), $selection['client_eligibility']);
        $flow->forceFill([
            'capacity_hold_id' => $hold->id,
            'status' => 'held',
            'last_step' => 3,
            'state' => [...$selection, 'policy' => $policy, 'hold_expires_at' => $hold->expires_at->toIso8601String()],
            'expires_at' => $hold->expires_at,
        ])->save();
        $this->track($business, $flow, 'slot_held', $flow->secret_hash, ['service_count' => count($services)]);

        return $flow->fresh('hold.lines');
    }

    /** @param array<string, mixed> $details @return array{appointment:Appointment,view_url:string,calendar_url:string} */
    public function confirm(Business $business, PublicBookingFlow $flow, array $details, string $idempotencyKey, ?int $paidDepositIntentId = null): array
    {
        if ($flow->status === 'confirmed' && $flow->appointment_id) {
            $appointment = Appointment::query()->where('business_id', $business->id)->findOrFail($flow->appointment_id);
        } else {
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
    }

    /** @return array<string, mixed> */
    public function catalog(Business $business): array
    {
        $this->assertBookable($business);
        $locations = Location::query()->where('business_id', $business->id)->where('is_active', true)->orderBy('name')->get();
        $services = Service::query()->where('business_id', $business->id)->where('is_active', true)->where('online_visible', true)->with(['locations', 'addons'])->orderBy('kind')->orderBy('name')->get();
        $staff = StaffProfile::query()->where('business_id', $business->id)->where('status', 'active')->where('online_visible', true)->with(['locations', 'serviceAssignments'])->orderBy('display_name')->get();

        return [
            'locations' => $locations->map->only(['public_id', 'name', 'address', 'time_zone']),
            'services' => $services->map(fn (Service $service) => [
                'public_id' => $service->public_id, 'kind' => $service->kind, 'name' => $service->name,
                'description' => $service->description, 'price_type' => match ($business->online_price_display) {
                    'exact' => 'fixed', 'from' => 'from', default => $service->price_type,
                },
                'price_minor' => $service->price_minor, 'currency_code' => $service->currency_code,
                'duration_minutes' => $service->duration_minutes + $service->processing_minutes + $service->cleanup_minutes,
                'deposit_type' => $service->deposit_type, 'deposit_value' => $service->deposit_value,
                'client_eligibility' => $service->client_eligibility,
                'location_ids' => $service->locations->pluck('public_id')->all(),
            ]),
            'staff' => $staff->map(fn (StaffProfile $profile) => [
                'public_id' => $profile->public_id, 'display_name' => $profile->display_name,
                'title' => $profile->title, 'biography' => $profile->biography,
                'location_ids' => $profile->locations->pluck('public_id')->all(),
                'service_ids' => $services->whereIn('id', $profile->serviceAssignments->where('is_active', true)->where('is_qualified', true)->where('online_visible', true)->pluck('service_id'))->pluck('public_id')->all(),
            ]),
            'policy' => $this->policySnapshot($business, null, []),
        ];
    }

    /** @return array{0:Location,1:Collection<int,Service>,2:?StaffProfile,3:list<BookingLineRequest>} */
    private function resolveSelection(Business $business, string $locationPublicId, array $servicePublicIds, ?string $staffPublicId): array
    {
        $this->assertBookable($business);
        $location = Location::query()->where('business_id', $business->id)->where('public_id', $locationPublicId)->where('is_active', true)->firstOrFail();
        $ids = array_values(array_unique($servicePublicIds));
        $services = Service::query()->where('business_id', $business->id)->whereIn('public_id', $ids)->where('is_active', true)->where('online_visible', true)->get()->keyBy('public_id');
        abort_unless($services->count() === count($ids), 422);
        $staff = $staffPublicId ? StaffProfile::query()->where('business_id', $business->id)->where('public_id', $staffPublicId)->where('status', 'active')->where('online_visible', true)->firstOrFail() : null;
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
            'deposit' => $deposit,
            'deposit_refundability' => $deposit['deposit_refundable_before_cutoff'],
            'cancellation_fee_minor' => $deposit['cancellation_fee_minor'],
            'no_show_fee_minor' => $deposit['no_show_fee_minor'],
            'terms_url' => $business->terms_url,
            'privacy_url' => $business->privacy_url,
            'marketing_wording' => 'Optional: send me marketing updates. Booking messages do not depend on this choice.',
            'whatsapp_wording' => 'Send appointment and service updates to this mobile number on WhatsApp. I can opt out at any time.',
            'location_time_zone' => $location?->time_zone,
            'services' => collect($snapshots)->map(fn (array $snapshot) => [
                'name' => $snapshot['name'], 'price_type' => match ($business->online_price_display) {
                    'exact' => 'fixed', 'from' => 'from', default => $snapshot['priceType'],
                }, 'price_minor' => $snapshot['priceMinor'],
                'currency_code' => $snapshot['currencyCode'], 'bookable_minutes' => $snapshot['bookableMinutes'],
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
