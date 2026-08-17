<?php

return [
    'business_types' => [
        'barber_shop' => 'Barber shop',
        'hair_salon' => 'Hair salon',
        'beauty_salon' => 'Beauty salon',
        'spa' => 'Spa',
        'nail_studio' => 'Nail studio',
        'wellness_clinic' => 'Wellness clinic',
        'independent_professional' => 'Independent professional',
        'other' => 'Other service business',
    ],
    'countries' => [
        'AU' => 'Australia', 'CA' => 'Canada', 'DE' => 'Germany', 'FR' => 'France',
        'GB' => 'United Kingdom', 'IN' => 'India', 'NZ' => 'New Zealand', 'SG' => 'Singapore',
        'US' => 'United States', 'ZA' => 'South Africa', 'AE' => 'United Arab Emirates',
    ],
    'locales' => [
        'en-AU' => 'English (Australia)', 'en-CA' => 'English (Canada)', 'en-GB' => 'English (United Kingdom)',
        'en-IN' => 'English (India)', 'en-NZ' => 'English (New Zealand)', 'en-SG' => 'English (Singapore)',
        'en-US' => 'English (United States)', 'fr-FR' => 'French (France)', 'de-DE' => 'German (Germany)',
    ],
    'currencies' => [
        'AUD' => 'Australian dollar (A$)', 'CAD' => 'Canadian dollar (C$)', 'EUR' => 'Euro (€)',
        'GBP' => 'Pound sterling (£)', 'INR' => 'Indian rupee (₹)', 'NZD' => 'New Zealand dollar (NZ$)',
        'SGD' => 'Singapore dollar (S$)', 'USD' => 'US dollar ($)', 'ZAR' => 'South African rand (R)',
        'AED' => 'UAE dirham (د.إ)',
    ],
    'time_zones' => DateTimeZone::listIdentifiers(),
];
