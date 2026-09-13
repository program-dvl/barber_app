<?php

return [
    'schema_version' => 2,

    'business_types' => [
        'hair_salon' => [
            'label' => 'Hair salon', 'short' => 'Hair', 'image' => '/images/marketing/industries/home-salon-hero.webp',
            'description' => 'Cuts, colour, styling and treatments for individuals or a full team.', 'accent' => '#6D4AFF',
            'services' => [
                ['key' => 'cut_finish', 'category' => 'Hair', 'name' => 'Cut & finish', 'duration' => 60, 'price_factor' => 1.5, 'description' => 'Consultation, tailored cut and finished style.'],
                ['key' => 'blow_dry', 'category' => 'Styling', 'name' => 'Blow-dry & style', 'duration' => 45, 'price_factor' => 1.0, 'description' => 'Wash, blow-dry and polished finish.'],
                ['key' => 'colour_refresh', 'category' => 'Colour', 'name' => 'Colour refresh', 'duration' => 120, 'price_factor' => 2.5, 'description' => 'Personalised colour refresh with finish.'],
                ['key' => 'hair_treatment', 'category' => 'Treatments', 'name' => 'Repair treatment', 'duration' => 45, 'price_factor' => 1.2, 'description' => 'Restorative treatment selected for the client’s hair.'],
            ],
        ],
        'barber_shop' => [
            'label' => 'Barbershop', 'short' => 'Barbering', 'image' => '/images/marketing/industries/barbershop.webp',
            'description' => 'Cuts, beard services, grooming and walk-in friendly schedules.', 'accent' => '#EF6A4C',
            'services' => [
                ['key' => 'signature_cut', 'category' => 'Haircuts', 'name' => 'Signature haircut', 'duration' => 45, 'price_factor' => 1.0, 'description' => 'Consultation, cut and styled finish.'],
                ['key' => 'skin_fade', 'category' => 'Haircuts', 'name' => 'Skin fade', 'duration' => 45, 'price_factor' => 1.2, 'description' => 'Precision fade with a clean styled finish.'],
                ['key' => 'beard_shape', 'category' => 'Grooming', 'name' => 'Beard shape & finish', 'duration' => 30, 'price_factor' => 0.7, 'description' => 'Shape, line-up and finishing care.'],
                ['key' => 'cut_beard', 'category' => 'Packages', 'name' => 'Haircut & beard', 'duration' => 75, 'price_factor' => 1.6, 'description' => 'A complete cut and beard appointment.'],
            ],
        ],
        'nail_studio' => [
            'label' => 'Nail studio', 'short' => 'Nails', 'image' => '/images/marketing/industries/nail-salon.webp',
            'description' => 'Manicures, pedicures, gel, nail art and maintenance appointments.', 'accent' => '#D13C91',
            'services' => [
                ['key' => 'classic_manicure', 'category' => 'Manicure', 'name' => 'Classic manicure', 'duration' => 45, 'price_factor' => 0.8, 'description' => 'Nail shaping, cuticle care and polish.'],
                ['key' => 'gel_manicure', 'category' => 'Manicure', 'name' => 'Gel manicure', 'duration' => 60, 'price_factor' => 1.1, 'description' => 'Long-wear gel colour with full nail preparation.'],
                ['key' => 'spa_pedicure', 'category' => 'Pedicure', 'name' => 'Spa pedicure', 'duration' => 60, 'price_factor' => 1.2, 'description' => 'Restorative foot care and polished finish.'],
                ['key' => 'nail_art', 'category' => 'Nail art', 'name' => 'Custom nail art', 'duration' => 30, 'price_factor' => 0.7, 'description' => 'Creative detail added to a nail service.'],
            ],
        ],
        'beauty_studio' => [
            'label' => 'Beauty studio', 'short' => 'Beauty', 'image' => '/images/marketing/editorial/use-cases-front-desk.webp',
            'description' => 'Brows, lashes, waxing, makeup and mixed beauty services.', 'accent' => '#A53DFF',
            'services' => [
                ['key' => 'brow_shape', 'category' => 'Brows', 'name' => 'Brow shape', 'duration' => 30, 'price_factor' => 0.7, 'description' => 'Consultation, shaping and a clean finish.'],
                ['key' => 'lash_lift', 'category' => 'Lashes', 'name' => 'Lash lift', 'duration' => 60, 'price_factor' => 1.3, 'description' => 'Lift and definition tailored to the client.'],
                ['key' => 'signature_facial', 'category' => 'Skin', 'name' => 'Signature facial', 'duration' => 60, 'price_factor' => 1.5, 'description' => 'A personalised facial and aftercare guidance.'],
                ['key' => 'occasion_makeup', 'category' => 'Makeup', 'name' => 'Occasion makeup', 'duration' => 60, 'price_factor' => 1.5, 'description' => 'A complete look designed for the occasion.'],
            ],
        ],
        'spa' => [
            'label' => 'Spa & sauna', 'short' => 'Spa', 'image' => '/images/marketing/industries/spa-sauna.webp',
            'description' => 'Treatments, rituals, facilities and carefully managed room capacity.', 'accent' => '#147D75',
            'services' => [
                ['key' => 'relaxation_ritual', 'category' => 'Body treatments', 'name' => 'Relaxation ritual', 'duration' => 90, 'price_factor' => 2.2, 'description' => 'A restorative full-body treatment experience.'],
                ['key' => 'deep_tissue', 'category' => 'Massage', 'name' => 'Deep tissue massage', 'duration' => 60, 'price_factor' => 1.6, 'description' => 'Focused massage with pressure adapted to the client.'],
                ['key' => 'facial_ritual', 'category' => 'Facials', 'name' => 'Radiance facial', 'duration' => 60, 'price_factor' => 1.7, 'description' => 'Cleansing, treatment and hydration ritual.'],
                ['key' => 'sauna_session', 'category' => 'Facilities', 'name' => 'Sauna session', 'duration' => 45, 'price_factor' => 0.8, 'description' => 'Reserved time for a relaxed sauna experience.'],
            ],
        ],
        'massage' => [
            'label' => 'Massage practice', 'short' => 'Massage', 'image' => '/images/marketing/industries/massage.webp',
            'description' => 'Relaxation, sports and therapeutic massage for solo or team practices.', 'accent' => '#B05C3B',
            'services' => [
                ['key' => 'relaxation_60', 'category' => 'Massage', 'name' => 'Relaxation massage · 60 min', 'duration' => 60, 'price_factor' => 1.4, 'description' => 'A calming full-body massage with tailored pressure.'],
                ['key' => 'deep_tissue_60', 'category' => 'Massage', 'name' => 'Deep tissue massage · 60 min', 'duration' => 60, 'price_factor' => 1.6, 'description' => 'Focused work for areas of persistent tension.'],
                ['key' => 'sports_recovery', 'category' => 'Recovery', 'name' => 'Sports recovery massage', 'duration' => 60, 'price_factor' => 1.6, 'description' => 'Targeted recovery work around training needs.'],
                ['key' => 'express_30', 'category' => 'Massage', 'name' => 'Focused massage · 30 min', 'duration' => 30, 'price_factor' => 0.8, 'description' => 'Focused attention for one priority area.'],
            ],
        ],
        'wellness' => [
            'label' => 'Wellness & recovery', 'short' => 'Wellness', 'image' => '/images/marketing/industries/fitness-recovery.webp',
            'description' => 'Recovery sessions, coaching and appointment-led wellness services.', 'accent' => '#167A65',
            'services' => [
                ['key' => 'initial_consult', 'category' => 'Consultations', 'name' => 'Initial consultation', 'duration' => 45, 'price_factor' => 1.0, 'description' => 'Goals, context and a recommended plan.'],
                ['key' => 'recovery_session', 'category' => 'Recovery', 'name' => 'Recovery session', 'duration' => 60, 'price_factor' => 1.4, 'description' => 'A guided recovery appointment tailored to the client.'],
                ['key' => 'guided_stretch', 'category' => 'Mobility', 'name' => 'Guided stretch', 'duration' => 45, 'price_factor' => 1.0, 'description' => 'One-to-one mobility and assisted stretching.'],
                ['key' => 'follow_up', 'category' => 'Consultations', 'name' => 'Progress follow-up', 'duration' => 30, 'price_factor' => 0.7, 'description' => 'Review progress and adapt the next steps.'],
            ],
        ],
        'skin_aesthetics' => [
            'label' => 'Skin & aesthetics', 'short' => 'Aesthetics', 'image' => '/images/marketing/industries/medspa.webp',
            'description' => 'Consultation-led skin and non-clinical aesthetic treatment journeys.', 'accent' => '#5966D8',
            'services' => [
                ['key' => 'skin_consult', 'category' => 'Consultations', 'name' => 'Skin consultation', 'duration' => 30, 'price_factor' => 0.8, 'description' => 'Skin goals, history and a recommended treatment plan.'],
                ['key' => 'signature_facial', 'category' => 'Facials', 'name' => 'Signature facial', 'duration' => 60, 'price_factor' => 1.7, 'description' => 'A customised facial selected for the client’s skin.'],
                ['key' => 'advanced_facial', 'category' => 'Facials', 'name' => 'Advanced facial', 'duration' => 75, 'price_factor' => 2.4, 'description' => 'An extended, consultation-led skin treatment.'],
                ['key' => 'review', 'category' => 'Consultations', 'name' => 'Treatment review', 'duration' => 30, 'price_factor' => 0.6, 'description' => 'Review results and plan ongoing care.'],
            ],
        ],
        'makeup_bridal' => [
            'label' => 'Makeup & bridal', 'short' => 'Makeup', 'image' => '/images/marketing/industries/independent-stylist.webp',
            'description' => 'Trials, event makeup and longer bridal or group appointments.', 'accent' => '#C24775',
            'services' => [
                ['key' => 'occasion_makeup', 'category' => 'Makeup', 'name' => 'Occasion makeup', 'duration' => 60, 'price_factor' => 1.4, 'description' => 'A complete, camera-ready look for the occasion.'],
                ['key' => 'bridal_consult', 'category' => 'Bridal', 'name' => 'Bridal consultation', 'duration' => 45, 'price_factor' => 0.8, 'description' => 'Discuss the day, style and practical plan.'],
                ['key' => 'bridal_trial', 'category' => 'Bridal', 'name' => 'Bridal makeup trial', 'duration' => 120, 'price_factor' => 2.3, 'description' => 'A considered trial with look refinement.'],
                ['key' => 'bridal_makeup', 'category' => 'Bridal', 'name' => 'Wedding day makeup', 'duration' => 120, 'price_factor' => 3.0, 'description' => 'Wedding-day makeup with a calm, planned schedule.'],
            ],
        ],
        'independent_professional' => [
            'label' => 'Independent professional', 'short' => 'Independent', 'image' => '/images/marketing/industries/independent-stylist.webp',
            'description' => 'A flexible starter for one professional offering appointment-based services.', 'accent' => '#7654D8',
            'services' => [
                ['key' => 'consultation', 'category' => 'Appointments', 'name' => 'Initial consultation', 'duration' => 30, 'price_factor' => 0.6, 'description' => 'A focused introduction and plan for the client.'],
                ['key' => 'signature_60', 'category' => 'Appointments', 'name' => 'Signature appointment', 'duration' => 60, 'price_factor' => 1.2, 'description' => 'Your core one-hour client experience.'],
                ['key' => 'express_30', 'category' => 'Appointments', 'name' => 'Express appointment', 'duration' => 30, 'price_factor' => 0.7, 'description' => 'A shorter appointment for a focused need.'],
                ['key' => 'follow_up', 'category' => 'Appointments', 'name' => 'Follow-up appointment', 'duration' => 45, 'price_factor' => 0.9, 'description' => 'A continuing appointment for returning clients.'],
            ],
        ],
        'multi_service' => [
            'label' => 'Multi-service business', 'short' => 'Multi-service', 'image' => '/images/marketing/editorial/product-workday.webp',
            'description' => 'A mixed service menu, shared team and room-aware operation.', 'accent' => '#4C56D7',
            'services' => [
                ['key' => 'consultation', 'category' => 'Consultations', 'name' => 'Personal consultation', 'duration' => 30, 'price_factor' => 0.6, 'description' => 'Understand the client and recommend the right service.'],
                ['key' => 'signature_60', 'category' => 'Signature services', 'name' => 'Signature service', 'duration' => 60, 'price_factor' => 1.3, 'description' => 'A polished core service ready to rename.'],
                ['key' => 'premium_90', 'category' => 'Signature services', 'name' => 'Premium experience', 'duration' => 90, 'price_factor' => 2.0, 'description' => 'An extended premium appointment.'],
                ['key' => 'follow_up', 'category' => 'Appointments', 'name' => 'Follow-up appointment', 'duration' => 30, 'price_factor' => 0.7, 'description' => 'A focused follow-up for returning clients.'],
            ],
        ],
        'other' => [
            'label' => 'Another appointment business', 'short' => 'Other', 'image' => '/images/marketing/editorial/company-story.webp',
            'description' => 'Start with flexible appointments and tailor every detail later.', 'accent' => '#365E89',
            'services' => [
                ['key' => 'consultation', 'category' => 'Appointments', 'name' => 'Initial consultation', 'duration' => 30, 'price_factor' => 0.6, 'description' => 'An introduction and plan for a new client.'],
                ['key' => 'standard', 'category' => 'Appointments', 'name' => 'Standard appointment', 'duration' => 60, 'price_factor' => 1.0, 'description' => 'A flexible core service ready to customise.'],
                ['key' => 'extended', 'category' => 'Appointments', 'name' => 'Extended appointment', 'duration' => 90, 'price_factor' => 1.5, 'description' => 'More time for detailed or multi-part work.'],
                ['key' => 'follow_up', 'category' => 'Appointments', 'name' => 'Follow-up appointment', 'duration' => 30, 'price_factor' => 0.6, 'description' => 'A concise appointment for returning clients.'],
            ],
        ],
    ],

    'operation_models' => [
        'at_location' => ['label' => 'Clients visit us', 'description' => 'Appointments happen at your business location.'],
        'mobile' => ['label' => 'We travel to clients', 'description' => 'Use a service-area base and adjust travel details later.'],
        'hybrid' => ['label' => 'Both', 'description' => 'Offer appointments at your location and on the move.'],
    ],
    'team_sizes' => ['1' => 'Just me', '2-5' => '2–5 people', '6-10' => '6–10 people', '11-25' => '11–25 people', '26+' => '26+ people'],
    'team_roles' => [
        'Owner', 'Co-owner', 'Business manager', 'Location manager', 'Studio manager',
        'Front desk coordinator', 'Receptionist', 'Administrator', 'Senior professional',
        'Professional', 'Assistant', 'Apprentice', 'Hair stylist', 'Senior stylist',
        'Colourist', 'Barber', 'Senior barber', 'Nail technician', 'Beauty therapist',
        'Aesthetic practitioner', 'Skin therapist', 'Massage therapist', 'Spa therapist',
        'Wellness practitioner', 'Fitness coach', 'Recovery specialist', 'Physiotherapist',
        'Makeup artist', 'Bridal specialist', 'Tattoo artist', 'Piercer', 'Pet groomer',
        'Tanning specialist',
    ],
    'location_scales' => ['single' => 'One location', 'multiple' => 'Multiple locations'],
    'schedule_presets' => [
        'weekdays' => ['label' => 'Monday–Friday', 'days' => [1, 2, 3, 4, 5], 'opens_at' => '09:00', 'closes_at' => '18:00'],
        'tuesday_saturday' => ['label' => 'Tuesday–Saturday', 'days' => [2, 3, 4, 5, 6], 'opens_at' => '09:00', 'closes_at' => '18:00'],
        'every_day' => ['label' => 'Every day', 'days' => [1, 2, 3, 4, 5, 6, 7], 'opens_at' => '09:00', 'closes_at' => '18:00'],
    ],
    'starter_price_major' => [
        'AUD' => 45, 'CAD' => 40, 'EUR' => 30, 'GBP' => 25, 'INR' => 800,
        'NZD' => 50, 'SGD' => 40, 'USD' => 35, 'ZAR' => 450, 'AED' => 120,
    ],
];
