<?php

return [
    'enabled' => true,
    'indexation_enabled' => (bool) env('MARKETPLACE_GROWTH_INDEXATION', false),
    'hub_minimum_businesses' => 3,
    'hub_review_days' => 7,
    'candidate_limit' => 200,
    'date_business_limit' => 12,
    'projection_minutes' => 5,
    'ranking_version' => 'local-v1',
    'attribution_version' => 'mpg-v1',
    'attribution_days' => 30,
    'retention_days' => 60,
];
