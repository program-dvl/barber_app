<?php

// Reviewed exact aliases; no provider credentials or clinical claims are inferred.
return [
    'version' => 'taxonomy-v1',
    'aliases_locale' => 'en',
    'audiences' => [
        'barbershops' => ['barbering', 'hair'],
        'salons' => ['hair', 'beauty', 'nails'],
        'independent-stylists' => [],
        'spas' => ['spa', 'massage', 'beauty'],
        'nail-salons' => ['nails'],
        'medspas' => ['aesthetics'],
        'massage' => ['massage'],
        'fitness-recovery' => ['fitness', 'recovery'],
        'physical-therapy' => ['physical-therapy'],
        'health-practices' => ['practice'],
        'tattoo-piercing' => ['tattoo', 'piercing'],
        'pet-grooming' => ['pet'],
        'tanning-studios' => ['tanning'],
    ],
    'operational_contexts' => [
        'hair_salon' => ['hair', 'beauty'],
        'barber_shop' => ['barbering', 'hair'],
        'nail_studio' => ['nails'],
        'nail_salon' => ['nails'],
        'beauty_studio' => ['beauty', 'nails'],
        'spa' => ['spa', 'massage', 'beauty'],
        'massage' => ['massage'],
        'wellness' => ['fitness', 'recovery'],
        'skin_aesthetics' => ['aesthetics', 'beauty'],
        'makeup_bridal' => ['beauty'],
        'independent_professional' => [],
        'multi_service' => [],
        'other' => [],
    ],
    'concepts' => [
        'haircut' => [
            'label' => 'Haircut',
            'families' => ['hair'],
            'aliases' => ['haircut', 'cut & finish', 'signature cut', 'signature haircut'],
        ],
        'skin-fade' => [
            'label' => 'Skin fade',
            'families' => ['hair', 'barbering'],
            'aliases' => ['skin fade'],
        ],
        'beard-shaping' => [
            'label' => 'Beard shaping',
            'families' => ['barbering'],
            'aliases' => ['beard shape & finish', 'beard trim', 'beard shaping'],
        ],
        'haircut-and-beard' => [
            'label' => 'Haircut & beard',
            'families' => ['hair', 'barbering'],
            'aliases' => ['haircut & beard'],
        ],
        'hair-colour' => [
            'label' => 'Hair colour',
            'families' => ['hair'],
            'aliases' => ['hair colour', 'hair color', 'colour refresh'],
        ],
        'blow-dry' => [
            'label' => 'Blow-dry & style',
            'families' => ['hair'],
            'aliases' => ['blow-dry & style', 'blow dry'],
        ],
        'hair-treatment' => [
            'label' => 'Hair treatment',
            'families' => ['hair'],
            'aliases' => ['repair treatment', 'hair treatment'],
        ],
        'gel-manicure' => [
            'label' => 'Gel manicure',
            'families' => ['nails'],
            'aliases' => ['gel manicure'],
        ],
        'manicure' => [
            'label' => 'Manicure',
            'families' => ['nails'],
            'aliases' => ['classic manicure', 'manicure'],
        ],
        'pedicure' => [
            'label' => 'Pedicure',
            'families' => ['nails'],
            'aliases' => ['spa pedicure', 'pedicure'],
        ],
        'nail-art' => [
            'label' => 'Nail art',
            'families' => ['nails'],
            'aliases' => ['custom nail art', 'nail art'],
        ],
        'brow-shaping' => [
            'label' => 'Brow shaping',
            'families' => ['beauty'],
            'aliases' => ['brow shape', 'brow shaping'],
        ],
        'lash-lift' => [
            'label' => 'Lash lift',
            'families' => ['beauty'],
            'aliases' => ['lash lift'],
        ],
        'facial' => [
            'label' => 'Facial',
            'families' => ['beauty'],
            'aliases' => ['signature facial', 'radiance facial'],
        ],
        'makeup' => [
            'label' => 'Makeup',
            'families' => ['beauty'],
            'aliases' => ['occasion makeup', 'bridal makeup'],
        ],
        'sauna' => [
            'label' => 'Sauna session',
            'families' => ['spa'],
            'aliases' => ['sauna session'],
        ],
        'spa-appointment' => [
            'label' => 'Spa appointment',
            'families' => ['spa'],
            'aliases' => ['relaxation ritual', 'spa appointment'],
        ],
        'relaxation-massage' => [
            'label' => 'Relaxation massage',
            'families' => ['massage'],
            'aliases' => ['relaxation massage', 'relaxation massage · 60 min'],
        ],
        'deep-tissue-massage' => [
            'label' => 'Deep tissue massage',
            'families' => ['massage'],
            'aliases' => ['deep tissue massage', 'deep tissue massage · 60 min'],
        ],
        'sports-massage' => [
            'label' => 'Sports massage',
            'families' => ['massage', 'recovery'],
            'aliases' => ['sports recovery massage', 'sports massage'],
        ],
        'personal-training' => [
            'label' => 'Personal training appointment',
            'families' => ['fitness'],
            'aliases' => ['personal training', 'personal training appointment'],
        ],
        'recovery-appointment' => [
            'label' => 'Recovery appointment',
            'families' => ['recovery'],
            'aliases' => ['recovery session', 'recovery appointment'],
        ],
        'physiotherapy-appointment' => [
            'label' => 'Physical therapy appointment',
            'families' => ['physical-therapy'],
            'aliases' => ['physical therapy appointment', 'physiotherapy appointment'],
        ],
        'practice-appointment' => [
            'label' => 'Practice appointment',
            'families' => ['practice'],
            'aliases' => ['practice appointment', 'health practice appointment'],
        ],
        'aesthetics-consultation' => [
            'label' => 'Aesthetics consultation',
            'families' => ['aesthetics'],
            'aliases' => ['aesthetics consultation'],
        ],
        'tattoo-consultation' => [
            'label' => 'Tattoo consultation',
            'families' => ['tattoo'],
            'aliases' => ['tattoo consultation'],
        ],
        'piercing-appointment' => [
            'label' => 'Piercing appointment',
            'families' => ['piercing'],
            'aliases' => ['piercing appointment'],
        ],
        'dog-grooming' => [
            'label' => 'Dog grooming',
            'families' => ['pet'],
            'aliases' => ['dog grooming', 'dog grooming appointment'],
        ],
        'pet-grooming' => [
            'label' => 'Pet grooming',
            'families' => ['pet'],
            'aliases' => ['pet grooming', 'pet grooming appointment'],
        ],
        'tanning-session' => [
            'label' => 'Tanning session',
            'families' => ['tanning'],
            'aliases' => ['tanning session'],
        ],
    ],
];
