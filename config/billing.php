<?php

return [
    'provider' => 'stripe',
    'trial_days' => (int) env('BILLING_TRIAL_DAYS', 14),
    'trial_notice_days' => [7, 3, 1],
    'grace_days' => (int) env('BILLING_GRACE_DAYS', 7),
    'export_days_after_termination' => (int) env('BILLING_EXPORT_DAYS_AFTER_TERMINATION', 30),
    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'currency' => env('STRIPE_BILLING_CURRENCY', 'USD'),
    ],
    'plans' => [
        'starter' => [
            'name' => 'ClipperDesk Starter',
            'description' => 'For solo and small shops ready to run their day with confidence.',
            'rank' => 10,
            'prices' => [
                'monthly' => [
                    'amount_minor' => (int) env('STRIPE_STARTER_MONTHLY_AMOUNT', 5000),
                    'price_id' => env('STRIPE_STARTER_MONTHLY_PRICE_ID'),
                ],
                'annual' => [
                    'amount_minor' => (int) env('STRIPE_STARTER_ANNUAL_AMOUNT', 50000),
                    'price_id' => env('STRIPE_STARTER_ANNUAL_PRICE_ID'),
                ],
            ],
            'entitlements' => [
                'locations.max' => 1,
                'staff.max' => 2,
                'messaging.monthly_allowance' => 0,
                'deposits.enabled' => false,
                'inventory.enabled' => false,
                'reporting.advanced' => false,
                'branding.custom' => false,
                'support.priority' => false,
                'exports.enabled' => true,
                'billing.manage' => true,
            ],
        ],
        'pro' => [
            'name' => 'ClipperDesk Pro',
            'description' => 'For growing salons that need deeper operational control and insight.',
            'rank' => 20,
            'prices' => [
                'monthly' => [
                    'amount_minor' => (int) env('STRIPE_PRO_MONTHLY_AMOUNT', 10000),
                    'price_id' => env('STRIPE_PRO_MONTHLY_PRICE_ID'),
                ],
                'annual' => [
                    'amount_minor' => (int) env('STRIPE_PRO_ANNUAL_AMOUNT', 100000),
                    'price_id' => env('STRIPE_PRO_ANNUAL_PRICE_ID'),
                ],
            ],
            'entitlements' => [
                'locations.max' => 3,
                'staff.max' => 20,
                'messaging.monthly_allowance' => 1000,
                'deposits.enabled' => true,
                'inventory.enabled' => true,
                'reporting.advanced' => true,
                'branding.custom' => true,
                'support.priority' => true,
                'exports.enabled' => true,
                'billing.manage' => true,
            ],
        ],
    ],
];
