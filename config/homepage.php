<?php

// Public homepage copy shared by Vue/SSR and the SSR-unavailable HTML fallback.
return [
    'title' => 'Appointment Scheduling & Business Software | ClipperDesk',
    'description' => 'Online booking, staff schedules, client records and business management for beauty, wellness, fitness, health and pet-care teams. Explore ClipperDesk.',
    'lead' => 'Online bookings, client records and your team. One connected working day.',
    'audience' => 'For appointment-led businesses across beauty, wellness, fitness, health and pet care.',
    'businesses' => [
        ['label' => 'Barbershops', 'slug' => 'barbershops'],
        ['label' => 'Salons', 'slug' => 'salons'],
        ['label' => 'Independent stylists', 'slug' => 'independent-stylists'],
        ['label' => 'Nail salons', 'slug' => 'nail-salons'],
        ['label' => 'Spas', 'slug' => 'spas'],
        ['label' => 'Massage', 'slug' => 'massage'],
        ['label' => 'Fitness & recovery', 'slug' => 'fitness-recovery'],
        ['label' => 'Pet grooming', 'slug' => 'pet-grooming'],
        ['label' => 'Medspas', 'slug' => 'medspas'],
        ['label' => 'Physical therapy', 'slug' => 'physical-therapy'],
        ['label' => 'Health practices', 'slug' => 'health-practices'],
        ['label' => 'Tattoo & piercing', 'slug' => 'tattoo-piercing'],
        ['label' => 'Tanning studios', 'slug' => 'tanning-studios'],
    ],
    'use_cases' => [
        ['title' => 'Avoid overlapping appointments', 'body' => 'Keep service duration, staff hours and required chairs, rooms or equipment aligned before confirming a visit.', 'route' => 'marketing.use-cases.show', 'slug' => 'reduce-scheduling-conflicts', 'label' => 'Explore scheduling conflict controls'],
        ['title' => 'Balance walk-ins and booked visits', 'body' => 'See the waiting queue alongside reserved appointments and assign available staff without displacing booked clients.', 'route' => 'marketing.use-cases.show', 'slug' => 'manage-walk-ins-and-appointments', 'label' => 'Explore the walk-in workflow'],
        ['title' => 'Switch from paper or spreadsheets', 'body' => 'Prepare client records, review supported imports and agree a changeover plan before sharing your new booking link.', 'route' => 'marketing.guides.show', 'slug' => 'switch-from-paper-bookings', 'label' => 'Plan your booking-system changeover'],
    ],
    'faqs' => [
        ['question' => 'What is ClipperDesk?', 'answer' => 'ClipperDesk is appointment scheduling and service business management software for beauty, wellness, fitness, recovery, health and pet-care teams. It connects online booking, staff scheduling, walk-in queues, client records, checkout and reporting.'],
        ['question' => 'Who is ClipperDesk built for?', 'answer' => 'ClipperDesk supports independent professionals and appointment-led businesses with one or more locations, including teams with a shared front desk. Dedicated pages explain the fit and limits for each supported business type.'],
        ['question' => 'How is this different from a booking-only tool?', 'answer' => 'Appointments connect to your calendar, client history, checkout and reports. You can also manage walk-ins alongside scheduled visits.'],
        ['question' => 'Can I manage online bookings and walk-ins together?', 'answer' => 'Yes. Online bookings and front-desk appointments use your service durations, staff availability and working hours. The walk-in queue helps your team see who is waiting and assign staff without overriding appointment conflicts.'],
        ['question' => 'How do payments and client notifications work?', 'answer' => 'Checkout records payments received outside ClipperDesk; it does not charge cards or send provider refunds. Online deposit collection is not currently available. Email and SMS notifications require enabled delivery providers, and SMS also requires client consent and available credits.'],
        ['question' => 'Can I bring existing data with me?', 'answer' => 'ClipperDesk includes CSV imports for clients, staff, services and products. Imports preview validation errors and duplicate candidates before valid rows are added to your business.'],
        ['question' => 'Is ClipperDesk suitable for an independent professional or a growing team?', 'answer' => 'Independent professionals can manage their own appointments and client records. Teams can coordinate staff schedules and permissions, while businesses with multiple locations can keep each location’s working hours and appointments in context. Review pricing and the industry-specific workflow before choosing.'],
        ['question' => 'Does ClipperDesk replace specialist industry software?', 'answer' => 'ClipperDesk supports shared booking, staff, client and business workflows. Industry pages explain the fit and limits. It does not replace clinical records, insurance systems, prescribing, fitness class memberships, pet medical records or tanning device controls.'],
        ['question' => 'Can clients book services that need a room or equipment?', 'answer' => 'Booking availability can account for configured chairs, rooms and equipment alongside eligible staff and service duration. Keep your resource quantities and working hours up to date, and test your service setup before publishing your booking page.'],
    ],
];
