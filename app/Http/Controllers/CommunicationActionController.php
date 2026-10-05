<?php

namespace App\Http\Controllers;

use App\Domain\ClientRecords\Models\Client;
use App\Domain\Communications\Models\CommunicationActionLink;
use App\Domain\Communications\Services\CommunicationActionLinkService;
use App\Domain\Communications\Services\CommunicationConsentService;
use App\Domain\MoneyCommerce\Models\Sale;
use App\Domain\MoneyCommerce\Models\SaleReceipt;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PublicBooking\Models\WaitlistMatch;
use App\Domain\PublicBooking\Services\SecureAppointmentLinkService;
use App\Domain\PublicBooking\Services\WaitlistService;
use App\Domain\SchedulingOperations\Models\Appointment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class CommunicationActionController extends Controller
{
    public function __invoke(Request $request, CommunicationActionLink $link, CommunicationActionLinkService $links, CommunicationConsentService $consent, SecureAppointmentLinkService $appointments, WaitlistService $waitlist): Response
    {
        abort_unless($request->hasValidSignature(), 403);
        $links->assertUsable($link);
        if ($link->purpose === 'receipt_view') {
            $receipt = SaleReceipt::query()->where('business_id', $link->business_id);
            if ($link->target_type === SaleReceipt::class && $link->target_id) {
                $receipt->whereKey($link->target_id);
            } elseif ($link->target_type === Appointment::class && $link->target_id) {
                // Existing links targeted the appointment. Each appointment has
                // one sale and one immutable issued receipt, so recovery is exact.
                $receipt->whereIn('sale_id', Sale::query()->select('id')->where('business_id', $link->business_id)->where('appointment_id', $link->target_id));
            } else {
                abort(410, 'This receipt is no longer available.');
            }
            $receipt = $receipt->firstOrFail();
            $sale = Sale::query()->where('business_id', $link->business_id)->with('appointment')->findOrFail($receipt->sale_id);
            $business = Business::query()->findOrFail($link->business_id);

            return response()->view('receipts.sale', ['receipt' => $receipt, 'receiptTimeZone' => $sale->appointment?->time_zone ?: $business->time_zone ?: 'UTC', 'receiptLocale' => $business->locale ?: 'en'], 200, [
                'Cache-Control' => 'private, no-store', 'X-Robots-Tag' => 'noindex, nofollow', 'Referrer-Policy' => 'no-referrer',
            ]);
        }
        if (str_starts_with($link->purpose, 'marketing_unsubscribe_')) {
            $client = Client::query()->where('business_id', $link->business_id)->findOrFail($link->client_id);
            $consent->unsubscribe($client, str($link->purpose)->afterLast('_')->toString());
            $link->update(['used_at' => now()]);

            return response('You have been unsubscribed from marketing messages. Transactional appointment and receipt messages are unaffected.');
        }
        if ($link->target_type === Appointment::class && $link->target_id) {
            $appointment = Appointment::query()->where('business_id', $link->business_id)->findOrFail($link->target_id);
            $purpose = match ($link->purpose) {
                'appointment_rebook' => 'rebook', 'appointment_payment' => 'payment_status', default => 'view',
            };
            $issued = $appointments->issue($appointment->loadMissing('business'), $purpose);
            $link->update(['used_at' => now()]);

            return new RedirectResponse(route($purpose === 'view' ? 'public.appointment.view' : 'public.appointment.action', $purpose === 'view' ? [$issued['token']] : [$issued['token'], $purpose]));
        }
        if ($link->target_type === WaitlistMatch::class && $link->target_id && $link->purpose === 'waitlist_claim') {
            $match = WaitlistMatch::query()->where('business_id', $link->business_id)->with(['request.business', 'request.location', 'request.service'])->findOrFail($link->target_id);
            if ($request->isMethod('post')) {
                $appointment = $waitlist->claimResolved($match);
                $issued = $appointments->issue($appointment->loadMissing('business'), 'view');
                $link->update(['used_at' => now()]);

                return new RedirectResponse(route('public.appointment.view', $issued['token']));
            }

            return Inertia::render('Booking/WaitlistOffer', [
                'token' => null, 'action_url' => $links->url($link), 'status' => $match->status,
                'expires_at' => $match->expires_at->toIso8601String(), 'business' => $match->request->business->name,
                'location' => $match->request->location->name, 'service' => $match->request->service->name,
                'starts_at' => $match->slot_starts_at_utc->setTimezone($match->request->location->time_zone)->toIso8601String(),
            ]);
        }

        abort(410, 'This action is no longer available.');
    }
}
