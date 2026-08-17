<?php

namespace App\Http\Controllers\PublicBooking;

use App\Domain\BusinessConfiguration\Services\BookingSlugManager;
use App\Domain\MoneyCommerce\Services\PaymentIntentService;
use App\Domain\PublicBooking\Services\PublicBookingService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicBookingPaymentController extends Controller
{
    public function store(Request $request, string $slug, BookingSlugManager $slugs, PublicBookingService $booking, PaymentIntentService $payments): JsonResponse
    {
        $data = $request->validate([
            'flow' => ['required', 'string'], 'secret' => ['required', 'string', 'size:64'], 'idempotency_key' => ['required', 'string', 'max:128'],
            'client_name' => ['required', 'string', 'max:255'], 'client_mobile' => ['required', 'string', 'max:32'], 'client_email' => ['required', 'email', 'max:255'],
            'client_date_of_birth' => ['nullable', 'date', 'before:today'], 'referral_source' => ['nullable', 'string', 'max:255'], 'special_request' => ['nullable', 'string', 'max:2000'],
            'communication_preferences' => ['array'], 'communication_preferences.*' => ['in:email,whatsapp'], 'marketing_opt_in' => ['boolean'], 'policy_accepted' => ['accepted'],
        ]);
        $business = $slugs->resolve($slug);
        abort_unless($business && $business->booking_slug === $slug, 404);
        $flow = $booking->resolve($business, $data['flow'], $data['secret']);
        abort_unless(($flow->state['policy']['deposit_status'] ?? null) === 'payment_required' && $flow->capacity_hold_id, 422);
        $started = $payments->startDeposit($flow->hold, $flow->state['policy']['deposit'], collect($data)->except(['flow', 'secret', 'idempotency_key'])->all(), $data['idempotency_key'], route('booking.business', $business->booking_slug));

        return response()->json(['payment_intent' => $started['intent']->public_id, 'amount_minor' => $started['intent']->amount_minor, 'currency_code' => $started['intent']->currency_code, 'provider' => $started['client_payload']]);
    }
}
