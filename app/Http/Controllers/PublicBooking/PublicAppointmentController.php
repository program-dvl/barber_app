<?php

namespace App\Http\Controllers\PublicBooking;

use App\Domain\PublicBooking\Models\WaitlistRequest;
use App\Domain\PublicBooking\Services\AppointmentSelfService;
use App\Domain\PublicBooking\Services\SecureAppointmentLinkService;
use App\Domain\PublicBooking\Services\WaitlistService;
use App\Domain\SchedulingOperations\Exceptions\BookingRuleViolation;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class PublicAppointmentController extends Controller
{
    public function view(string $token, SecureAppointmentLinkService $links): Response
    {
        $link = $links->resolve($token, 'view');

        return Inertia::render('Booking/Manage', $this->payload($link->appointment, $links->actionUrls($link->appointment)));
    }

    public function action(string $token, string $purpose, SecureAppointmentLinkService $links): Response
    {
        abort_unless(in_array($purpose, ['reschedule', 'cancel', 'rebook', 'contact', 'waitlist', 'payment_status'], true), 404);
        $link = $links->resolve($token, $purpose);

        return Inertia::render('Booking/Manage', [...$this->payload($link->appointment, []), 'purpose' => $purpose, 'actionToken' => $token]);
    }

    public function mutate(Request $request, string $token, string $purpose, SecureAppointmentLinkService $links, AppointmentSelfService $selfService, WaitlistService $waitlist): RedirectResponse
    {
        abort_unless(in_array($purpose, ['reschedule', 'cancel', 'rebook', 'contact', 'waitlist'], true), 404);
        $link = $links->resolve($token, $purpose);
        try {
            if ($purpose === 'cancel') {
                $data = $request->validate(['confirmed' => ['accepted'], 'idempotency_key' => ['required', 'string', 'max:128']]);
                $appointment = $selfService->cancel($link, $data['idempotency_key']);

                return $this->redirectToFreshView($appointment, $links, 'Your appointment has been cancelled.');
            }
            if ($purpose === 'contact') {
                $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'mobile' => ['required', 'string', 'max:32'], 'email' => ['required', 'email'], 'idempotency_key' => ['required', 'string', 'max:128']]);
                $appointment = $selfService->updateContact($link, $data, $data['idempotency_key']);

                return $this->redirectToFreshView($appointment, $links, 'Contact details updated without changing your booking history.');
            }
            if ($purpose === 'reschedule') {
                $data = $request->validate(['starts_at' => ['required', 'date'], 'confirmed' => ['accepted'], 'idempotency_key' => ['required', 'string', 'max:128']]);
                $appointment = $selfService->reschedule($link, CarbonImmutable::parse($data['starts_at'], $link->appointment->time_zone)->utc(), $data['idempotency_key']);

                return $this->redirectToFreshView($appointment, $links, 'Your appointment has been rescheduled.');
            }
            if ($purpose === 'waitlist') {
                if ($request->string('operation')->toString() === 'leave') {
                    $data = $request->validate([
                        'operation' => ['required', 'in:leave'], 'waitlist_request' => ['required', 'string', 'size:26'],
                        'version' => ['required', 'integer', 'min:1'],
                    ]);
                    $appointment = $link->appointment;
                    $entry = WaitlistRequest::query()
                        ->where('business_id', $appointment->business_id)
                        ->where('origin_appointment_id', $appointment->id)
                        ->where('public_id', $data['waitlist_request'])
                        ->firstOrFail();
                    $waitlist->leave($entry, $data['version']);
                    $link->forceFill(['used_at' => now()])->save();

                    return $this->redirectToFreshView($appointment, $links, 'You have left that waitlist request.');
                }
                $data = $request->validate([
                    'operation' => ['required', 'in:join'],
                    'acceptable_from' => ['required', 'date', 'after_or_equal:today'], 'acceptable_until' => ['required', 'date', 'after_or_equal:acceptable_from'],
                    'time_from' => ['required', 'date_format:H:i'], 'time_until' => ['required', 'date_format:H:i', 'after:time_from'],
                    'notification_method' => ['required', 'in:email,sms,whatsapp'], 'notes' => ['nullable', 'string', 'max:1000'],
                ]);
                $appointment = $link->appointment->loadMissing(['location', 'serviceLines']);
                $line = $appointment->serviceLines->firstOrFail();
                $waitlist->create($appointment->business, $appointment->location, $line->service, $line->primaryStaff, [
                    ...$data, 'origin_appointment_id' => $appointment->id,
                    'client_name' => $appointment->client_name, 'client_mobile' => $appointment->client_mobile, 'client_email' => $appointment->client_email,
                ]);
                $link->forceFill(['used_at' => now()])->save();

                return $this->redirectToFreshView($appointment, $links, 'You are on the waitlist.');
            }
            $link->forceFill(['used_at' => now()])->save();

            return redirect()->route('booking.business', ['slug' => $link->appointment->business->booking_slug, 'rebook' => $link->appointment->booking_reference]);
        } catch (BookingRuleViolation $error) {
            return back()->withErrors(['appointment' => $error->getMessage()]);
        }
    }

    public function calendar(string $token, SecureAppointmentLinkService $links): HttpResponse
    {
        $appointment = $links->resolve($token, 'view')->appointment->loadMissing(['business', 'location', 'serviceLines']);
        $escape = fn (string $value) => str_replace(['\\', ';', ',', "\n"], ['\\\\', '\\;', '\\,', '\\n'], $value);
        $body = implode("\r\n", [
            'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Good Hours//Booking//EN', 'BEGIN:VEVENT',
            'UID:'.$appointment->public_id.'@good-hours.local',
            'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z'),
            'DTSTART:'.$appointment->starts_at_utc->utc()->format('Ymd\THis\Z'),
            'DTEND:'.$appointment->ends_at_utc->utc()->format('Ymd\THis\Z'),
            'SUMMARY:'.$escape($appointment->business->name.' appointment'),
            'LOCATION:'.$escape((string) $appointment->location->address),
            'DESCRIPTION:'.$escape($appointment->serviceLines->pluck('name')->join(', ').' · Reference '.$appointment->booking_reference),
            'END:VEVENT', 'END:VCALENDAR', '',
        ]);

        return response($body, 200, ['Content-Type' => 'text/calendar; charset=utf-8', 'Content-Disposition' => 'attachment; filename="appointment-'.$appointment->booking_reference.'.ics"']);
    }

    /** @param array<string, string> $actions @return array<string, mixed> */
    private function payload($appointment, array $actions): array
    {
        $appointment->loadMissing(['business', 'location', 'serviceLines']);
        $activeWaitlists = WaitlistRequest::query()
            ->with('service:id,name')
            ->where('business_id', $appointment->business_id)
            ->where('origin_appointment_id', $appointment->id)
            ->whereIn('status', ['active', 'offered'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (WaitlistRequest $request) => [
                'public_id' => $request->public_id,
                'service' => $request->service->name,
                'from' => $request->acceptable_from->toDateString(),
                'until' => $request->acceptable_until->toDateString(),
                'time_from' => substr($request->time_from, 0, 5),
                'time_until' => substr($request->time_until, 0, 5),
                'status' => $request->status,
                'version' => $request->version,
            ])->all();

        return [
            'appointment' => [
                'reference' => $appointment->booking_reference, 'status' => $appointment->status,
                'business' => $appointment->business->name, 'location' => $appointment->location->name,
                'starts_at' => $appointment->starts_at_utc->setTimezone($appointment->time_zone)->toIso8601String(),
                'ends_at' => $appointment->ends_at_utc->setTimezone($appointment->time_zone)->toIso8601String(),
                'time_zone' => $appointment->time_zone, 'services' => $appointment->serviceLines->pluck('name')->all(),
                'price_minor' => $appointment->price_minor, 'currency_code' => $appointment->currency_code,
                'client_name' => $appointment->client_name, 'client_mobile' => $appointment->client_mobile,
                'client_email' => $appointment->client_email, 'version' => $appointment->version,
                'deposit_status' => $appointment->public_policy_snapshot['deposit_status'] ?? 'not_required',
                'cancellation_policy' => $appointment->public_policy_snapshot['cancellation_policy'] ?? $appointment->business->default_cancellation_policy,
            ],
            'actions' => $actions,
            'activeWaitlists' => $activeWaitlists,
        ];
    }

    private function redirectToFreshView($appointment, SecureAppointmentLinkService $links, string $status): RedirectResponse
    {
        $view = $links->issue($appointment->loadMissing('business'), 'view');

        return redirect()->route('public.appointment.view', ['token' => $view['token']])->with('status', $status);
    }
}
