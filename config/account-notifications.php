<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Business account email
    |--------------------------------------------------------------------------
    |
    | These messages are for ClipperDesk account holders: owners and team
    | members. Client appointment communication remains in the Communications
    | domain so consent, tenant branding and delivery policy stay independent.
    |
    */
    'queue' => env('ACCOUNT_EMAIL_QUEUE', 'emails'),

    // off, new_device, or always. New-device alerts are the safe default.
    'login_alerts' => env('ACCOUNT_LOGIN_ALERTS', 'new_device'),
    'login_history_days' => (int) env('ACCOUNT_LOGIN_HISTORY_DAYS', 180),

    'senders' => [
        'account' => [
            'address' => env('ACCOUNT_MAIL_FROM_ADDRESS', env('MAIL_FROM_ADDRESS', 'hello@example.com')),
            'name' => env('ACCOUNT_MAIL_FROM_NAME', env('MAIL_FROM_NAME', 'ClipperDesk')),
        ],
        'security' => [
            'address' => env('SECURITY_MAIL_FROM_ADDRESS', env('MAIL_FROM_ADDRESS', 'hello@example.com')),
            'name' => env('SECURITY_MAIL_FROM_NAME', 'ClipperDesk Security'),
        ],
        'billing' => [
            'address' => env('BILLING_MAIL_FROM_ADDRESS', env('MAIL_FROM_ADDRESS', 'hello@example.com')),
            'name' => env('BILLING_MAIL_FROM_NAME', 'ClipperDesk Billing'),
        ],
    ],

    'reply_to' => [
        'address' => env('ACCOUNT_MAIL_REPLY_TO_ADDRESS', env('BRAND_SUPPORT_EMAIL')),
        'name' => env('ACCOUNT_MAIL_REPLY_TO_NAME', 'ClipperDesk Support'),
    ],
];
