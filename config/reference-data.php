<?php

return [
    'business_types' => [
        'hair_salon' => 'Hair salon',
        'barber_shop' => 'Barbershop',
        'nail_studio' => 'Nail studio',
        'beauty_studio' => 'Beauty studio',
        'spa' => 'Spa & sauna',
        'massage' => 'Massage practice',
        'wellness' => 'Wellness & recovery',
        'skin_aesthetics' => 'Skin & aesthetics',
        'makeup_bridal' => 'Makeup & bridal',
        'independent_professional' => 'Independent professional',
        'multi_service' => 'Multi-service business',
        'other' => 'Another appointment business',
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
    'appointment_cancellation_reasons' => [
        'business' => [
            ['value' => 'client_requested', 'label' => 'Client requested cancellation', 'status' => 'cancelled_by_client', 'reason' => 'Client requested cancellation.'],
            ['value' => 'client_unavailable', 'label' => 'Client is unavailable or has a schedule conflict', 'status' => 'cancelled_by_client', 'reason' => 'Client is unavailable or has a schedule conflict.'],
            ['value' => 'staff_unavailable', 'label' => 'Team member is unavailable', 'status' => 'cancelled_by_shop', 'reason' => 'Team member is unavailable.'],
            ['value' => 'business_closure', 'label' => 'Business closure or emergency', 'status' => 'cancelled_by_shop', 'reason' => 'Business closure or emergency.'],
            ['value' => 'capacity_conflict', 'label' => 'Scheduling, room or equipment conflict', 'status' => 'cancelled_by_shop', 'reason' => 'Scheduling, room or equipment conflict.'],
            ['value' => 'service_unavailable', 'label' => 'Requested service is unavailable', 'status' => 'cancelled_by_shop', 'reason' => 'Requested service is unavailable.'],
            ['value' => 'payment_missing', 'label' => 'Required deposit or payment was not received', 'status' => 'cancelled_by_shop', 'reason' => 'Required deposit or payment was not received.'],
            ['value' => 'duplicate_booking', 'label' => 'Duplicate or incorrect booking', 'status' => 'cancelled_by_shop', 'reason' => 'Duplicate or incorrect booking.'],
            ['value' => 'other', 'label' => 'Other', 'status' => 'cancelled_by_shop', 'reason' => null],
        ],
        'client' => [
            ['value' => 'plans_changed', 'label' => 'My plans changed', 'reason' => 'Client cancelled because their plans changed.'],
            ['value' => 'schedule_conflict', 'label' => 'I have a schedule conflict', 'reason' => 'Client cancelled because of a schedule conflict.'],
            ['value' => 'unwell', 'label' => 'I am unwell', 'reason' => 'Client cancelled because they are unwell.'],
            ['value' => 'different_time', 'label' => 'I need a different time', 'reason' => 'Client cancelled because they need a different time.'],
            ['value' => 'cost', 'label' => 'Price or budget', 'reason' => 'Client cancelled because of price or budget.'],
            ['value' => 'no_longer_needed', 'label' => 'I no longer need the service', 'reason' => 'Client no longer needs the service.'],
            ['value' => 'duplicate_booking', 'label' => 'I booked twice or by mistake', 'reason' => 'Client made a duplicate or incorrect booking.'],
            ['value' => 'prefer_not_to_say', 'label' => 'Prefer not to say', 'reason' => 'Client preferred not to provide a cancellation reason.'],
            ['value' => 'other', 'label' => 'Other', 'reason' => null],
        ],
    ],
];
