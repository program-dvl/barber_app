<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Public brand identity
    |--------------------------------------------------------------------------
    |
    | Keep customer-visible identity in one server-owned configuration. Only
    | the explicitly shared public subset is exposed to the Inertia client.
    |
    */
    'product_name' => env('BRAND_PRODUCT_NAME', 'ClipperDesk'),
    'company_name' => env('BRAND_COMPANY_NAME', 'ClipperDesk'),
    'tagline' => env('BRAND_TAGLINE', 'Run the day. Grow the business.'),
    'description' => env(
        'BRAND_DESCRIPTION',
        'ClipperDesk is the daily operating system for salons and barbershops—from booking and scheduling to clients, staff, checkout, and reporting.'
    ),

    'logo' => '/images/brand/clipperdesk-logo.svg',
    'logo_inverse' => '/images/brand/clipperdesk-logo-inverse.svg',
    'logo_mark' => '/images/brand/clipperdesk-mark.svg',
    'logo_mark_inverse' => '/images/brand/clipperdesk-mark-inverse.svg',
    'favicon' => '/favicon.svg',
    'social_image' => '/images/brand/clipperdesk-social.png',

    'website_url' => env('BRAND_WEBSITE_URL', env('APP_URL', 'http://localhost')),
    'booking_host' => env('BRAND_BOOKING_HOST', 'book.clipperdesk.com'),
    'support_email' => env('BRAND_SUPPORT_EMAIL'),

    // Server-rendered and framework-owned surfaces that cannot consume the
    // application CSS variables use this small public palette projection.
    'colors' => [
        'brand_primary' => '#172554',
        'action_primary' => '#4338ca',
        'accent' => '#0e7490',
        'text' => '#0f172a',
        'muted' => '#64748b',
        'border' => '#cbd5e1',
        'background' => '#f5f7fb',
    ],
];
