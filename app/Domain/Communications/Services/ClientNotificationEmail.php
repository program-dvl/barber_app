<?php

namespace App\Domain\Communications\Services;

/** Platform-owned client email presentation. Values always remain escaped. */
final class ClientNotificationEmail
{
    public function render(?string $type, array $variables, string $text, string $subject): string
    {
        $presentation = self::presentation($type);
        $details = [];
        $fields = match ($type) {
            'queue_update' => ['queue_estimate' => 'Estimated turn', 'location_name' => 'Location'],
            'payment_receipt', 'deposit_request', 'deposit_received' => ['booking_reference' => 'Booking reference'],
            'booking_rejected' => ['booking_reference' => 'Booking reference'],
            default => ['service_name' => 'Service', 'staff_name' => 'Professional', 'location_name' => 'Location', 'booking_reference' => 'Booking reference'],
        };
        foreach ($fields as $key => $label) {
            if (filled($variables[$key] ?? null)) {
                $details[$label] = (string) $variables[$key];
            }
        }
        $appointment = in_array($type, ['booking_confirmation', 'booking_pending', 'booking_approved', 'appointment_reminder', 'appointment_changed', 'appointment_cancelled', 'waitlist_opening'], true);

        return view('emails.client-notification', [
            'presentation' => $presentation, 'structured' => $type !== null,
            'subject' => $subject, 'text' => $text, 'values' => $variables, 'details' => $details,
            'appointment' => $appointment,
            'action' => $presentation['action'] ? self::safeUrl($variables['action_link'] ?? null) : null,
            'directions' => $appointment ? self::safeUrl($variables['directions_link'] ?? null) : null,
            'logo' => self::safeUrl(url(config('brand.logo'))),
        ])->render();
    }

    public static function presentation(?string $type): array
    {
        [$status, $title, $intro, $action] = match ($type) {
            'booking_confirmation' => ['Confirmed', 'You’re booked in.', 'Your appointment is confirmed. Here’s everything you need for your visit.', 'View appointment'],
            'booking_pending' => ['Awaiting approval', 'Your request is in.', 'The team has received your booking request. Your appointment is not confirmed yet; we’ll let you know when it has been reviewed.', null],
            'booking_approved' => ['Approved', 'Your appointment is confirmed.', 'Good news—the team has approved your booking request. We look forward to your visit.', 'View appointment'],
            'booking_rejected' => ['Request declined', 'Let’s find another time.', 'The team couldn’t confirm your booking request. You can review the booking options for another visit.', 'Explore booking options'],
            'appointment_reminder' => ['Reminder', 'Your visit is coming up.', 'A little reminder of your upcoming appointment. Your visit details are below.', 'View appointment'],
            'appointment_changed' => ['Updated', 'Your appointment has changed.', 'Please review the latest appointment details below. These replace the previous details.', 'Review appointment'],
            'appointment_cancelled' => ['Cancelled', 'Your appointment is cancelled.', 'The appointment below has been cancelled. You can review the booking options whenever you’re ready.', 'Explore booking options'],
            'deposit_request' => ['Deposit information', 'Your booking deposit.', 'Review the requested deposit and appointment payment information.', 'View deposit information'],
            'deposit_received' => ['Deposit received', 'Your deposit is received.', 'The deposit below has been recorded for your booking.', null],
            'payment_receipt' => ['Receipt ready', 'Thank you for your visit.', 'Your receipt is ready. Open it to review the recorded payment and full breakdown.', 'View receipt'],
            'waitlist_opening' => ['Opening available', 'A space has opened up.', 'An opening matching your waitlist request is available. Review it before it expires; it isn’t booked until you claim it.', 'Review opening'],
            'queue_update' => ['Turn update', 'Your turn is getting closer.', 'The team has shared an updated estimate for your walk-in visit. Timing may change as the day progresses.', null],
            default => ['Service update', 'An update for you.', '', null],
        };

        return ['status' => $status, 'title' => $title, 'intro' => $intro, 'action' => $action];
    }

    private static function safeUrl(mixed $value): ?string
    {
        if (! is_string($value) || ! filter_var($value, FILTER_VALIDATE_URL)) {
            return null;
        }
        $parts = parse_url($value);

        return in_array($parts['scheme'] ?? '', ['http', 'https'], true)
            && ! isset($parts['user']) && ! isset($parts['pass']) ? $value : null;
    }
}
