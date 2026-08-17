<?php

return [
    'resend' => [
        'api_url' => env('RESEND_API_URL', 'https://api.resend.com'),
        'api_key' => env('RESEND_API_KEY'),
        'from' => env('COMMUNICATION_EMAIL_FROM', 'Good Hours <notifications@getgoodhours.com>'),
        'webhook_secret' => env('RESEND_WEBHOOK_SECRET'),
    ],
    'twilio' => [
        'account_sid' => env('TWILIO_ACCOUNT_SID'),
        'auth_token' => env('TWILIO_AUTH_TOKEN'),
        'whatsapp_from' => env('TWILIO_WHATSAPP_FROM'),
        'content_sids' => [],
    ],
];
