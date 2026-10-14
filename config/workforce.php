<?php

return [
    // UTC remains the canonical storage timezone for workforce timestamps.
    // This only controls local display, date filters and datetime-local inputs.
    'timezone' => env('WORKFORCE_TIMEZONE', 'Africa/Cairo'),
];
