<?php

return [
    // Appointment payments use a separate Stripe webhook secret and domain boundary.
    // They must never be projected into the SaaS subscription aggregate.
    'stripe_webhook_secret' => env('STRIPE_APPOINTMENT_WEBHOOK_SECRET'),
];
