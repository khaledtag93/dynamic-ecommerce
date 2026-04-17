<?php

return [
    'email_provider' => env('GROWTH_EMAIL_PROVIDER', 'smtp'),
    'supported_email_providers' => ['smtp'],
    'conversion_window_hours' => (int) env('GROWTH_CONVERSION_WINDOW_HOURS', 168),
    'smart_timing_default' => (bool) env('GROWTH_SMART_TIMING', true),
    'dynamic_coupons_default' => (bool) env('GROWTH_DYNAMIC_COUPONS', true),
    'ai_offer_selection_default' => (bool) env('GROWTH_AI_SELECTION', true),
    'predictive_default' => (bool) env('GROWTH_PREDICTIVE', true),
    'winback_default' => (bool) env('GROWTH_WINBACK', true),
    'adaptive_learning_default' => (bool) env('GROWTH_ADAPTIVE_LEARNING', true),
    'smarter_winback_default' => (bool) env('GROWTH_SMARTER_WINBACK', true),
    'cohort_months' => (int) env('GROWTH_COHORT_MONTHS', 6),
    'vip_threshold' => (float) env('GROWTH_VIP_THRESHOLD', 75),
    'at_risk_threshold' => (float) env('GROWTH_AT_RISK_THRESHOLD', 70),
];
