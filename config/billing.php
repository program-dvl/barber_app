<?php

return [
    'provider' => env('BILLING_PROVIDER', env('PADDLE_API_KEY') ? 'paddle' : 'stripe'),
    'trial_days' => (int) env('BILLING_TRIAL_DAYS', 14),
    'grace_days' => (int) env('BILLING_GRACE_DAYS', 7),
    'export_days_after_termination' => (int) env('BILLING_EXPORT_DAYS_AFTER_TERMINATION', 30),
    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],
    'paddle' => [
        'api_key' => env('PADDLE_API_KEY'),
        'webhook_secret' => env('PADDLE_WEBHOOK_SECRET'),
        'sandbox' => (bool) env('PADDLE_SANDBOX', true),
        'api_url' => env('PADDLE_API_URL', env('PADDLE_SANDBOX', true) ? 'https://sandbox-api.paddle.com' : 'https://api.paddle.com'),
        'client_side_token' => env('PADDLE_CLIENT_SIDE_TOKEN'),
    ],
];
