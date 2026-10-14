<?php

return [
    // UTC remains the canonical storage timezone for workforce timestamps.
    // This only controls local display, date filters and datetime-local inputs.
    'timezone' => env('WORKFORCE_TIMEZONE', 'Africa/Cairo'),

    'leave_approval' => [
        // Current permission + hierarchy scope behavior remains the default.
        // 'direct_manager' is an explicit opt-in only after policy approval:
        // exact active direct manager + workforce.manage, with NO fallback.
        'routing_mode' => env('WORKFORCE_LEAVE_APPROVAL_ROUTING_MODE', 'scoped'),
    ],
];
