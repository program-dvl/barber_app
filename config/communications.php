<?php

return [
    /*
     * Client-facing mobile channels enabled for the current product release.
     * Keep provider implementations behind this gate so a deferred channel
     * cannot be selected by a client or delivered from an old queued message.
     */
    'client_mobile_channels' => ['sms'],
    'mobile_marketing_enabled' => false,
    'default_reminder_offsets_minutes' => [1440],

    /*
     * fake: no network; twilio_test: zero-charge SMS simulation; live: approved
     * production SMS traffic. Live sends require an explicit enable flag so a
     * copied credential cannot send.
     */
    'transport_mode' => env('COMMUNICATIONS_TRANSPORT_MODE', 'fake'),
    'dispatch_operational_events_immediately' => (bool) env('COMMUNICATIONS_IMMEDIATE_EVENT_DISPATCH', true),
    'live_send_enabled' => (bool) env('COMMUNICATIONS_LIVE_SEND_ENABLED', false),
    'whatsapp_sandbox_send_enabled' => (bool) env('TWILIO_WHATSAPP_SANDBOX_SEND_ENABLED', false),
    'email_transport_mode' => env('COMMUNICATIONS_EMAIL_TRANSPORT_MODE', 'ses'),
    'ses' => [
        'from_address' => env('SES_CLIENT_FROM_ADDRESS', env('MAIL_FROM_ADDRESS')),
        'from_name' => env('SES_CLIENT_FROM_NAME', env('MAIL_FROM_NAME', 'ClipperDesk')),
        'configuration_set' => env('AWS_SES_CONFIGURATION_SET'),
        'sns_topic_arn' => env('AWS_SES_CLIENT_SNS_TOPIC_ARN'),
    ],
    // Legacy callbacks remain available for historical Resend messages.
    'resend' => [
        'api_url' => env('RESEND_API_URL', 'https://api.resend.com'),
        'api_key' => env('RESEND_API_KEY'),
        'from' => env('COMMUNICATION_EMAIL_FROM', 'ClipperDesk <notifications@clipperdesk.local>'),
        'webhook_secret' => env('RESEND_WEBHOOK_SECRET'),
    ],
    'twilio' => [
        'account_sid' => env('TWILIO_ACCOUNT_SID'),
        'auth_token' => env('TWILIO_AUTH_TOKEN'),
        'api_key_sid' => env('TWILIO_API_KEY_SID'),
        'api_key_secret' => env('TWILIO_API_KEY_SECRET'),
        'webhook_auth_token' => env('TWILIO_WEBHOOK_AUTH_TOKEN', env('TWILIO_AUTH_TOKEN')),
        'test_account_sid' => env('TWILIO_TEST_ACCOUNT_SID'),
        'test_auth_token' => env('TWILIO_TEST_AUTH_TOKEN'),
        'test_sms_from' => env('TWILIO_TEST_SMS_FROM', '+15005550006'),
        'sms_from' => env('TWILIO_SMS_FROM'),
        'whatsapp_from' => env('TWILIO_WHATSAPP_FROM'),
        'whatsapp_sandbox_from' => env('TWILIO_WHATSAPP_SANDBOX_FROM', 'whatsapp:+14155238886'),
        'sandbox_business_public_id' => env('TWILIO_SANDBOX_BUSINESS_PUBLIC_ID'),
        'content_sids' => [],
    ],
];
