<?php

return [
    'scheme' => env('BOOKING_SCHEME', 'https'),
    'host' => env('BOOKING_HOST', env('BRAND_BOOKING_HOST', 'book.clipperdesk.com')),
    'reserved_slugs' => ['book', 'booking', 'appointments', 'manage-appointment', 'client-forms', 'client-files', 'waitlist-offers', 'login', 'logout', 'register', 'password', 'forgot-password', 'reset-password', 'confirm-password', 'two-factor-challenge', 'email', 'user', 'dashboard', 'admin', 'api', 'auth', 'billing', 'webhooks', 'build', 'storage', 'images', 'assets', 'health', 'up', 'robots', 'sitemap', 'terms', 'privacy', 'terms-of-service', 'privacy-policy', 'refund-policy', 'support', 'settings', 'features', 'pricing', 'company', 'security', 'resources', 'solutions', 'use-cases', 'blog', 'roadmap'],
];
