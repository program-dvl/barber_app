<?php

namespace App\Http\Controllers\PublicBooking;

use App\Domain\BusinessConfiguration\Services\BookingSlugManager;
use App\Domain\PublicBooking\Services\PublicBookingService;
use App\Domain\SchedulingOperations\Exceptions\BookingRuleViolation;
use App\Http\Controllers\Controller;
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
            'staff' => ['nullable', 'string'], 'from_date' => ['required', 'date'], 'until_date' => ['required', 'date', 'after_or_equal:from_date'],
            'client_eligibility' => ['required', 'in:new,existing'],
        ]);
        $business = $this->business($slug, $slugs);
        $flow = $booking->resolve($business, $data['flow'], $data['secret']);
        $slots = $booking->search($business, $data['location'], $data['services'], $data['staff'] ?? null, $data['from_date'], $data['until_date'], $data['client_eligibility']);
        $booking->record($business, $flow, 'time_selection_viewed', $data['secret'], ['slot_count' => count($slots)]);

        return response()->json(['slots' => $slots]);
    }

    public function hold(Request $request, string $slug, BookingSlugManager $slugs, PublicBookingService $booking): JsonResponse
    {
        $data = $request->validate([
            'flow' => ['required', 'string'], 'secret' => ['required', 'string', 'size:64'],
            'location' => ['required', 'string'], 'services' => ['required', 'array', 'min:1', 'max:12'], 'services.*' => ['string'],
            'staff' => ['nullable', 'string'], 'starts_at' => ['required', 'date'], 'client_eligibility' => ['required', 'in:new,existing'],
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

    public function confirm(Request $request, string $slug, BookingSlugManager $slugs, PublicBookingService $booking): JsonResponse
    {
        $data = $request->validate([
            'flow' => ['required', 'string'], 'secret' => ['required', 'string', 'size:64'], 'idempotency_key' => ['required', 'string', 'max:128'],
            'client_name' => ['required', 'string', 'max:255'], 'client_mobile' => ['required', 'string', 'max:32'],
            'client_email' => ['required', 'email', 'max:255'], 'client_date_of_birth' => ['nullable', 'date', 'before:today'],
            'referral_source' => ['nullable', 'string', 'max:255'], 'special_request' => ['nullable', 'string', 'max:2000'],
            'communication_preferences' => ['array'], 'communication_preferences.*' => ['in:email,whatsapp'],
            'marketing_opt_in' => ['boolean'], 'policy_accepted' => ['accepted'],
        ]);
        $business = $this->business($slug, $slugs);
        try {
            $flow = $booking->resolve($business, $data['flow'], $data['secret']);
            $result = $booking->confirm($business, $flow, $data, $data['idempotency_key']);
        } catch (BookingRuleViolation $error) {
            return response()->json($error->toDomainError(), 409);
        }

        return response()->json([
            'reference' => $result['appointment']->booking_reference,
            'starts_at' => $result['appointment']->starts_at_utc->setTimezone($result['appointment']->time_zone)->toIso8601String(),
            'view_url' => $result['view_url'], 'calendar_url' => $result['calendar_url'],
        ]);
    }

    private function business(string $slug, BookingSlugManager $slugs)
    {
        $business = $slugs->resolve($slug);
        abort_unless($business && $business->booking_slug === $slug, 404);

        return $business;
    }
}
