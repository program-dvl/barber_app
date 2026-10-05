<?php

namespace App\Domain\Communications\Services;

final class TemplateVariableCatalog
{
    /** @var list<string> */
    public const ALLOWED = [
        'client_name', 'staff_name', 'service_name', 'location_name', 'appointment_date',
        'appointment_time', 'time_zone', 'amount', 'currency', 'booking_reference',
        'action_link', 'unsubscribe_link', 'queue_estimate', 'feedback_link', 'business_name', 'reply_body',
    ];

    /** @var array<string,string> */
    public const SAFE_FALLBACKS = [
        'client_name' => 'there', 'staff_name' => 'your professional', 'service_name' => 'your service',
        'location_name' => 'the shop', 'time_zone' => 'local time', 'amount' => 'the stated amount',
        'currency' => '', 'queue_estimate' => 'soon', 'business_name' => 'the business',
    ];

    /** @return array{subject:string,body:string,category:string,action_purpose:?string} */
    public static function defaults(string $intent): array
    {
        return match ($intent) {
            'booking_confirmation' => ['subject' => 'Booking confirmed · {{booking_reference}}', 'body' => '{{business_name}}: Hi {{client_name}}, your {{service_name}} with {{staff_name}} at {{location_name}} is confirmed for {{appointment_date}} at {{appointment_time}} ({{time_zone}}). Manage it: {{action_link}}', 'category' => 'transactional', 'action_purpose' => 'appointment_view'],
            'booking_pending' => ['subject' => 'Booking pending', 'body' => '{{business_name}}: Hi {{client_name}}, we received your request for {{service_name}} at {{location_name}} on {{appointment_date}} at {{appointment_time}} ({{time_zone}}). We will confirm once the team reviews it.', 'category' => 'transactional', 'action_purpose' => null],
            'booking_approved' => ['subject' => 'Booking approved', 'body' => '{{business_name}}: Hi {{client_name}}, your {{service_name}} with {{staff_name}} at {{location_name}} is approved for {{appointment_date}} at {{appointment_time}} ({{time_zone}}). Manage it: {{action_link}}', 'category' => 'transactional', 'action_purpose' => 'appointment_view'],
            'booking_rejected' => ['subject' => 'Booking request update', 'body' => '{{business_name}}: Hi {{client_name}}, we could not confirm your requested appointment. You can choose another time: {{action_link}}', 'category' => 'transactional', 'action_purpose' => 'appointment_rebook'],
            'appointment_reminder' => ['subject' => 'Appointment reminder', 'body' => '{{business_name}}: Hi {{client_name}}, a reminder for your {{service_name}} with {{staff_name}} at {{location_name}} on {{appointment_date}} at {{appointment_time}} ({{time_zone}}). {{action_link}}', 'category' => 'transactional', 'action_purpose' => 'appointment_view'],
            'appointment_changed' => ['subject' => 'Appointment changed', 'body' => '{{business_name}}: Hi {{client_name}}, your updated appointment is {{service_name}} with {{staff_name}} at {{location_name}} on {{appointment_date}} at {{appointment_time}} ({{time_zone}}). Review: {{action_link}}', 'category' => 'transactional', 'action_purpose' => 'appointment_view'],
            'appointment_cancelled' => ['subject' => 'Appointment cancelled', 'body' => '{{business_name}}: Hi {{client_name}}, your {{service_name}} appointment at {{location_name}} on {{appointment_date}} at {{appointment_time}} ({{time_zone}}) has been cancelled. Choose another time: {{action_link}}', 'category' => 'transactional', 'action_purpose' => 'appointment_rebook'],
            'deposit_request' => ['subject' => 'Deposit required', 'body' => '{{business_name}}: Hi {{client_name}}, a deposit of {{currency}} {{amount}} is required for your booking. View deposit information: {{action_link}}', 'category' => 'transactional', 'action_purpose' => 'appointment_payment'],
            'deposit_received' => ['subject' => 'Deposit received', 'body' => '{{business_name}}: Hi {{client_name}}, we received your deposit of {{currency}} {{amount}} for booking {{booking_reference}}.', 'category' => 'transactional', 'action_purpose' => null],
            'payment_receipt' => ['subject' => 'Your receipt', 'body' => '{{business_name}}: Hi {{client_name}}, your receipt for {{currency}} {{amount}} is ready: {{action_link}}', 'category' => 'transactional', 'action_purpose' => 'receipt_view'],
            'waitlist_opening' => ['subject' => 'A waitlist opening is available', 'body' => '{{business_name}}: Hi {{client_name}}, an opening for {{service_name}} at {{location_name}} is available on {{appointment_date}} at {{appointment_time}} ({{time_zone}}). Claim it: {{action_link}}', 'category' => 'transactional', 'action_purpose' => 'waitlist_claim'],
            'queue_update' => ['subject' => 'Queue update', 'body' => '{{business_name}}: Hi {{client_name}}, your estimated turn is {{queue_estimate}} at {{location_name}}.', 'category' => 'transactional', 'action_purpose' => null],
            'conversation_reply' => ['subject' => '', 'body' => '{{reply_body}}', 'category' => 'transactional', 'action_purpose' => null],
            'feedback_request' => ['subject' => 'How was your visit?', 'body' => 'Hi {{client_name}}, tell {{business_name}} about your visit: {{feedback_link}}. Unsubscribe: {{unsubscribe_link}}', 'category' => 'marketing', 'action_purpose' => 'feedback'],
            'rebooking_reminder' => ['subject' => 'Ready to book again?', 'body' => 'Hi {{client_name}}, it may be time to book {{service_name}} again: {{action_link}}. Unsubscribe: {{unsubscribe_link}}', 'category' => 'marketing', 'action_purpose' => 'appointment_rebook'],
            default => throw new \InvalidArgumentException('Unsupported communication intent type.'),
        };
    }

    /** @return list<string> */
    public static function intents(): array
    {
        return ['booking_confirmation', 'booking_pending', 'booking_approved', 'booking_rejected', 'appointment_reminder', 'appointment_changed', 'appointment_cancelled', 'deposit_request', 'deposit_received', 'payment_receipt', 'waitlist_opening', 'queue_update', 'conversation_reply', 'feedback_request', 'rebooking_reminder'];
    }
}
