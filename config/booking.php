<?php

return [
    // Hold timeout in minutes
    'hold_timeout' => env('BOOKING_HOLD_TIMEOUT', 10),

    // Maximum days in advance to book
    'max_days' => env('BOOKING_MAX_DAYS', 30),

    // Featured courts period in days
    'featured_period_days' => env('BOOKING_FEATURED_PERIOD_DAYS', 30),
];
