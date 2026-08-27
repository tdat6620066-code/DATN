<?php

return [
    // Hold timeout in minutes
    'hold_timeout' => env('BOOKING_HOLD_TIMEOUT', 5),

    // Maximum days in advance to book
    'max_days' => env('BOOKING_MAX_DAYS', 30),

    // Recurring reservations deliberately have a wider planning window than
    // one-off bookings. This supports weekly/monthly memberships without
    // loosening the daily booking limit shown on the court page.
    'max_recurring_days' => env('BOOKING_MAX_RECURRING_DAYS', 365),

    // Featured courts period in days
    'featured_period_days' => env('BOOKING_FEATURED_PERIOD_DAYS', 30),
];
