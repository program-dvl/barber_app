<?php

return [
    // Paddle is deliberately not a salon-payment adapter: its published MoR product is for SaaS/digital products.
    // Stripe is the appointment card gateway; subscription billing remains on its own bounded contract.
    'stripe_webhook_secret' => env('STRIPE_APPOINTMENT_WEBHOOK_SECRET'),
];
