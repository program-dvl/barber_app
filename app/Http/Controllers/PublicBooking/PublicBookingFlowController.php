<?php

namespace App\Http\Controllers\PublicBooking;

use App\Domain\BusinessConfiguration\Services\BookingSlugManager;
use App\Domain\PublicBooking\Services\PublicBookingService;
use App\Domain\SchedulingOperations\Exceptions\BookingRuleViolation;
use App\Http\Controllers\Controller;
use App\Rules\E164Phone;
use App\Support\Locations\LocationData;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicBookingFlowController extends Controller
{
    public function start(string $slug, BookingSlugManager $slugs, PublicBookingService $booking): JsonResponse
    {
        $business = $this->business($slug, $slugs);
        $started = $booking->start($business);

        return response()->json(['flow' => $started['flow']->public_id, 'secret' => $started['secret'], 'expires_at' => $started['flow']->expires_at->toIso8601String()]);
    }

    public function search(Request $request, string $slug, BookingSlugManager $slugs, PublicBookingService $booking): JsonResponse
    {
        $data = $request->validate([
            'flow' => ['required', 'string'], 'secret' => ['required', 'string', 'size:64'],
            'location' => ['required', 'string'], 'services' => ['required', 'array', 'min:1', 'max:12'], 'services.*' => ['string'],
            'staff' => ['nullable', 'string'], 'from_date' => ['required', 'date_format:Y-m-d'], 'until_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:from_date'],
            'client_eligibility' => ['required', 'in:new,existing,auto'],
        ]);
        $business = $this->business($slug, $slugs);
        if (CarbonImmutable::parse($data['from_date'])->diffInDays(CarbonImmutable::parse($data['until_date'])) > 7) {
            return response()->json(['message' => 'Search up to one week at a time.'], 422);
        }
        try {
            $flow = $booking->resolve($business, $data['flow'], $data['secret']);
            $slots = $booking->search($business, $data['location'], $data['services'], $data['staff'] ?? null, $data['from_date'], $data['until_date'], $data['client_eligibility']);
            $booking->record($business, $flow, 'time_selection_viewed', $data['secret'], ['slot_count' => count($slots)]);
        } catch (BookingRuleViolation $error) {
            return response()->json($error->toDomainError(), 409);
        }

        return response()->json(['slots' => $slots]);
    }

    public function hold(Request $request, string $slug, BookingSlugManager $slugs, PublicBookingService $booking): JsonResponse
    {
        $data = $request->validate([
            'flow' => ['required', 'string'], 'secret' => ['required', 'string', 'size:64'],
            'location' => ['required', 'string'], 'services' => ['required', 'array', 'min:1', 'max:12'], 'services.*' => ['string'],
            'staff' => ['nullable', 'string'], 'starts_at' => ['required', 'date'], 'client_eligibility' => ['required', 'in:new,existing,auto'],
            'idempotency_key' => ['required', 'string', 'max:128'],
        ]);
        $business = $this->business($slug, $slugs);
        try {
            $flow = $booking->resolve($business, $data['flow'], $data['secret']);
            $held = $booking->hold($business, $flow, $data, $data['idempotency_key']);
        } catch (BookingRuleViolation $error) {
            return response()->json($error->toDomainError(), 409);
        }

        return response()->json(['hold_expires_at' => $held->expires_at->toIso8601String(), 'policy' => $held->state['policy'], 'state' => $held->state]);
    }

    public function resume(Request $request, string $slug, BookingSlugManager $slugs, PublicBookingService $booking): JsonResponse
    {
        $data = $request->validate(['flow' => ['required', 'string'], 'secret' => ['required', 'string', 'size:64']]);
        $business = $this->business($slug, $slugs);
        try {
            $flow = $booking->resolve($business, $data['flow'], $data['secret']);
            if ($flow->status === 'confirmed') {
                $result = $booking->confirm($business, $flow, [], 'resume-'.$flow->public_id);

                return response()->json(['status' => 'confirmed', 'confirmation' => $this->confirmationPayload($result)]);
            }

            return response()->json(['status' => $flow->status, 'expires_at' => $flow->expires_at->toIso8601String(), 'state' => collect($flow->state)->except(['details_fingerprint', 'client_eligibility'])->all()]);
        } catch (BookingRuleViolation $error) {
            return response()->json($error->toDomainError(), 409);
        }
    }

    public function release(Request $request, string $slug, BookingSlugManager $slugs, PublicBookingService $booking): JsonResponse
    {
        $data = $request->validate(['flow' => ['required', 'string'], 'secret' => ['required', 'string', 'size:64']]);
        $business = $this->business($slug, $slugs);
        try {
            $flow = $booking->release($business, $booking->resolve($business, $data['flow'], $data['secret']));

            return response()->json(['expires_at' => $flow->expires_at->toIso8601String()]);
        } catch (BookingRuleViolation $error) {
            return response()->json($error->toDomainError(), 409);
        }
    }

    public function details(Request $request, string $slug, BookingSlugManager $slugs, PublicBookingService $booking): JsonResponse
    {
        $data = $request->validate([
            'flow' => ['required', 'string'], 'secret' => ['required', 'string', 'size:64'],
            'client_name' => ['required', 'string', 'max:255'], 'client_mobile' => ['required', 'string', 'max:32', new E164Phone], 'client_email' => ['required', 'email', 'max:255'],
        ]);
        $business = $this->business($slug, $slugs);
        try {
            $flow = $booking->reviewDetails($business, $booking->resolve($business, $data['flow'], $data['secret']), $data);

            return response()->json(['policy' => $flow->state['policy']]);
        } catch (BookingRuleViolation $error) {
            return response()->json($error->toDomainError(), 409);
        }
    }

    public function confirm(Request $request, string $slug, BookingSlugManager $slugs, PublicBookingService $booking): JsonResponse
    {
        $data = $request->validate([
            'flow' => ['required', 'string'], 'secret' => ['required', 'string', 'size:64'], 'idempotency_key' => ['required', 'string', 'max:128'],
            'client_name' => ['required', 'string', 'max:255'], 'client_mobile' => ['required', 'string', 'max:32', new E164Phone],
            'client_email' => ['required', 'email', 'max:255'], 'client_date_of_birth' => ['nullable', 'date', 'before:today'],
            'referral_source' => ['nullable', 'string', 'max:255'], 'special_request' => ['nullable', 'string', 'max:2000'],
            'communication_preferences' => ['array'], 'communication_preferences.*' => ['in:email,sms'],
            'marketing_opt_in' => ['boolean'], 'policy_accepted' => ['accepted'],
        ]);
        $business = $this->business($slug, $slugs);
        try {
            $flow = $booking->resolve($business, $data['flow'], $data['secret']);
            if ($flow->status !== 'confirmed') {
                if (! isset($flow->state['details_fingerprint'])) {
                    return response()->json(['code' => 'DETAILS_REVIEW_REQUIRED', 'message' => 'Review your appointment details before confirming.'], 409);
                }
            }
            $result = $booking->confirm($business, $flow, $data, $data['idempotency_key']);
        } catch (BookingRuleViolation $error) {
            return response()->json($error->toDomainError(), 409);
        }

        return response()->json($this->confirmationPayload($result));
    }

    private function confirmationPayload(array $result): array
    {
        $appointment = $result['appointment']->loadMissing(['location', 'serviceLines.primaryStaff']);

        return [
            'reference' => $result['appointment']->booking_reference,
            'starts_at' => $result['appointment']->starts_at_utc->setTimezone($result['appointment']->time_zone)->toIso8601String(),
            'view_url' => $result['view_url'], 'calendar_url' => $result['calendar_url'],
            'location' => LocationData::forAppointment($appointment)['name'],
            'address' => LocationData::forAppointment($appointment)['address'],
            'location_details' => LocationData::forAppointment($appointment),
            'time_zone' => $appointment->time_zone, 'currency_code' => $appointment->currency_code,
            'price_minor' => $appointment->price_minor,
            'services' => $appointment->serviceLines->pluck('name'),
            'staff' => $appointment->serviceLines->map(fn ($line) => $line->primaryStaff?->display_name)->filter()->unique()->values(),
        ];
    }

    private function business(string $slug, BookingSlugManager $slugs)
    {
        $business = $slugs->resolve($slug);
        abort_unless($business && $business->booking_slug === $slug && $business->isActive() && $business->configuration_published_at && $business->online_booking_enabled, 404);

        return $business;
    }
}
