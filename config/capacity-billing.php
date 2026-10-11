<?php

return [
    // Approved USD software prices. Purchases still require verified Stripe mappings.
    'enabled' => (bool) env('CAPACITY_BILLING_ENABLED', false),
    'pricing_mode' => env('CAPACITY_PRICING_MODE', 'usd'),
    'default_market' => 'US',
    // Enable separately only after top-up refund/dispute operations are certified.
    'sms_purchases_enabled' => (bool) env('CAPACITY_SMS_PURCHASES_ENABLED', false),
    'automatic_tax' => (bool) env('CAPACITY_AUTOMATIC_TAX', false),
    'max_locations' => 1000,
    'max_staff' => 10000,
    'quote_minutes' => 10,
    'markets' => [
        'US' => [
            'name' => 'USD', 'currency' => 'USD', 'revision' => '2026-10-11-usd-1',
            'approved' => (bool) env('CAPACITY_US_APPROVED', true),
            'sms_per_staff' => 100,
            // Destination routes, not the account's currency, determine credit use.
            // The +1 route must cover the actual US/Canada carrier cost ceiling.
            'sms_routes' => ['+1' => 1],
            'monthly' => [
                'base_minor' => 2900, 'location_minor' => 1900, 'staff_minor' => 900,
                'base_price_id' => env('CAPACITY_US_MONTHLY_BASE_PRICE_ID'),
                'location_price_id' => env('CAPACITY_US_MONTHLY_LOCATION_PRICE_ID'),
                'staff_price_id' => env('CAPACITY_US_MONTHLY_STAFF_PRICE_ID'),
            ],
            'annual' => [
                'base_minor' => 29000, 'location_minor' => 19000, 'staff_minor' => 9000,
                'base_price_id' => env('CAPACITY_US_ANNUAL_BASE_PRICE_ID'),
                'location_price_id' => env('CAPACITY_US_ANNUAL_LOCATION_PRICE_ID'),
                'staff_price_id' => env('CAPACITY_US_ANNUAL_STAFF_PRICE_ID'),
            ],
            // Paid packs need their own approved costs and reversal operations.
            'sms_packs' => [],
        ],
    ],
    // These are already implemented core capabilities. Deferred channels and
    // enterprise services are not enabled by buying additional capacity.
    'features' => [
        'deposits.enabled' => true, 'inventory.enabled' => true,
        'reporting.advanced' => true, 'branding.custom' => true,
        'exports.enabled' => true, 'billing.manage' => true,
        'messaging.branded_sender' => false, 'messaging.two_way' => false,
        'support.priority' => false,
    ],
];
