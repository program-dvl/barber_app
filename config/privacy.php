<?php

return [
    // These values represent approved business facts, never boilerplate defaults.
    'operator_name' => env('LEGAL_OPERATOR_NAME', 'Clipperdesk'),
    'operator_address' => env('LEGAL_OPERATOR_ADDRESS', '111 Galileo Dr, Stoney Creek, ON L8E 0B5, Canada'),
    'privacy_email' => env('LEGAL_PRIVACY_EMAIL', 'support@clipperdesk.com'),
    'canonical_url' => rtrim((string) env('BRAND_WEBSITE_URL', env('APP_URL', 'http://localhost')), '/').'/privacy-policy',
    'version' => '1.0',
    'updated_at' => '2026-10-12',
    'effective_at' => env('LEGAL_PRIVACY_EFFECTIVE_DATE'),
    // Set only after reviewing this exact version and completing the audit checklist.
    'approved' => (bool) env('LEGAL_PRIVACY_APPROVED', false),
    'approval_reference' => env('LEGAL_PRIVACY_APPROVAL_REFERENCE'),
    'operations_verified' => (bool) env('LEGAL_PRIVACY_OPERATIONS_VERIFIED', false),
    // Human-reviewed inventory; presence of an SDK or credentials proves no deployment fact.
    'processing_countries' => env('LEGAL_PROCESSING_COUNTRIES'),
    'providers' => [
        'hosting' => env('LEGAL_HOSTING_PROVIDER'),
        'storage' => env('LEGAL_STORAGE_PROVIDER'),
        'account_email' => env('LEGAL_ACCOUNT_EMAIL_PROVIDER'),
        'client_email' => env('LEGAL_CLIENT_EMAIL_PROVIDER'),
        'sms' => env('LEGAL_SMS_PROVIDER'),
        'monitoring' => env('LEGAL_MONITORING_PROVIDER'),
    ],
];
