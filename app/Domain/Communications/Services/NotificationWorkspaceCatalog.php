<?php

namespace App\Domain\Communications\Services;

use App\Domain\PlatformAccess\Models\Business;

/** Presentation of existing FR-13 events; does not create new sending rules. */
final class NotificationWorkspaceCatalog
{
    public static function automations(): array
    {
        return [
            'booking_confirmation' => ['name' => 'Booking confirmation', 'group' => 'Appointments', 'trigger' => 'Appointment confirmed', 'timing' => 'Immediately', 'description' => 'Reassure clients with their service, location and appointment time.'],
            'appointment_reminder' => ['name' => 'Appointment reminder', 'group' => 'Appointments', 'trigger' => 'Appointment approaching', 'timing' => '24 hours before', 'description' => 'One reminder when there is enough notice, adjusted around local quiet hours.'],
            'appointment_changed' => ['name' => 'Appointment changed', 'group' => 'Appointments', 'trigger' => 'Time, staff or service changes', 'timing' => 'Immediately', 'description' => 'Share the latest appointment details and a secure link to review them.'],
            'appointment_cancelled' => ['name' => 'Cancellation', 'group' => 'Appointments', 'trigger' => 'Appointment cancelled', 'timing' => 'Immediately', 'description' => 'Confirm the cancellation and offer a secure way to book again.'],
            'booking_pending' => ['name' => 'Booking request received', 'group' => 'Booking requests', 'trigger' => 'Approval required', 'timing' => 'Immediately', 'description' => 'Explain that the requested appointment is awaiting approval.'],
            'booking_approved' => ['name' => 'Booking request approved', 'group' => 'Booking requests', 'trigger' => 'Request approved', 'timing' => 'Immediately', 'description' => 'Confirm the approved appointment details.'],
            'booking_rejected' => ['name' => 'Booking request declined', 'group' => 'Booking requests', 'trigger' => 'Request declined', 'timing' => 'Immediately', 'description' => 'Let clients know to choose another appointment time.'],
            'deposit_request' => ['prepared' => true, 'name' => 'Deposit request', 'group' => 'Payments', 'trigger' => 'Deposit requested', 'timing' => 'Immediately', 'description' => 'Prepared template. The current payment flow does not trigger this message automatically; its link opens appointment payment information.'],
            'deposit_received' => ['prepared' => true, 'name' => 'Deposit received', 'group' => 'Payments', 'trigger' => 'Deposit confirmed', 'timing' => 'Immediately', 'description' => 'Prepared template. The current payment flow does not trigger this message automatically.'],
            'payment_receipt' => ['name' => 'Payment receipt', 'group' => 'Payments', 'trigger' => 'Receipt issued', 'timing' => 'Immediately', 'description' => 'Share the recorded amount and a secure receipt link.'],
            'waitlist_opening' => ['name' => 'Waitlist opening', 'group' => 'Queue & waitlist', 'trigger' => 'Requested opening available', 'timing' => 'When matched', 'description' => 'Use only the channel the client requested for their waitlist offer.'],
            'queue_update' => ['name' => 'Walk-in turn update', 'group' => 'Queue & waitlist', 'trigger' => 'Team sends a turn alert', 'timing' => 'When triggered by your team', 'description' => 'Send a deliberate queue estimate; minor queue changes do not send texts.'],
        ];
    }

    public static function variables(string $intent): array
    {
        $common = ['client_name', 'business_name'];
        $appointment = ['staff_name', 'service_name', 'location_name', 'appointment_date', 'appointment_time', 'time_zone', 'booking_reference', 'amount', 'currency'];
        $specific = match ($intent) {
            'waitlist_opening' => ['service_name', 'location_name', 'appointment_date', 'appointment_time', 'time_zone'],
            'queue_update' => ['queue_estimate', 'location_name'],
            default => $appointment,
        };
        if (TemplateVariableCatalog::defaults($intent)['action_purpose']) {
            $specific[] = 'action_link';
        }

        return [...$common, ...$specific];
    }

    public static function samples(Business $business): array
    {
        return ['client_name' => 'Sarah', 'staff_name' => 'Emma', 'service_name' => 'Haircut & style',
            'location_name' => 'Main studio', 'appointment_date' => '10 October 2026', 'appointment_time' => '14:30',
            'time_zone' => $business->time_zone ?: 'UTC', 'amount' => '45.00', 'currency' => $business->currency_code ?: 'USD',
            'booking_reference' => 'BK-1042', 'action_link' => 'https://example.test/your-secure-link',
            'queue_estimate' => '15 minutes', 'business_name' => $business->name];
    }

    public static function issue(?string $code): ?string
    {
        return match ($code) {
            null, '' => null,
            'twilio_http_401', 'twilio_http_403', 'resend_http_401', 'resend_http_403', 'provider_not_configured' => 'The sending connection needs attention. Ask support to check the setup before retrying.',
            'invalid_destination' => 'The contact address or mobile number is invalid. Review the client’s contact details.',
            'sms_opt_in_missing', 'whatsapp_opt_in_missing', 'channel_preference_withdrawn' => 'The client has not selected or has withdrawn permission for this channel.',
            'destination_suppressed', 'channel_opt_out' => 'Sending is blocked by an opt-out or contact suppression. This cannot be bypassed.',
            'email.bounced', 'undelivered' => 'The provider could not deliver this message. Check the contact details; automatic sending may be blocked.',
            'email.complained' => 'The recipient reported this email. Further messages are blocked.',
            'source_cancelled_or_rescheduled', 'appointment_changed', 'appointment_cancelled' => 'This reminder was stopped because the appointment changed or was cancelled.',
            'plan_limit' => 'The available text allowance and credits have been used.',
            'destination_not_included' => 'Text delivery is not available for this destination.',
            'provider_changed_requires_review' => 'The sending connection changed after an earlier attempt. Ask support to reconcile the original outcome before sending again.',
            'ses_outcome_unknown' => 'The email sending result is uncertain. Ask support to reconcile it before another message is sent.',
            'ses_throttled' => 'Email is temporarily rate limited. A safe retry will be scheduled when attempts remain.',
            'ses_authentication_failed', 'ses_sender_rejected', 'ses_configuration_set_missing', 'ses_sending_paused' => 'The email sending connection needs attention. Ask support to check its authorization and approved sender.',
            'sender_not_ready', 'branded_sender_not_ready' => 'The selected text sender is awaiting setup or approval.',
            'template_render_failed', 'template_not_published' => 'The message template needs attention before it can be sent.',
            'channel_paused', 'mobile_marketing_paused', 'marketing_consent_missing' => 'This communication is unavailable or lacks the required consent.',
            'retry_limit_reached' => 'The retry limit has been reached. Contact support to investigate.',
            default => 'The message could not be sent. Contact support to investigate safely.',
        };
    }
}
