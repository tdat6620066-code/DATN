<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;

class BookingPolicy
{
    /**
     * Determine if user can view the booking
     */
    public function view(User $user, Booking $booking): bool
    {
        return ($user->role ?: 'CUSTOMER') === 'CUSTOMER' && $user->id === $booking->user_id;
    }

    /**
     * Determine if user can update the booking
     */
    public function update(User $user, Booking $booking): bool
    {
        return ($user->role ?: 'CUSTOMER') === 'CUSTOMER' && $user->id === $booking->user_id && $booking->status === 'PENDING_PAYMENT';
    }

    /**
     * Determine if user can cancel the booking
     */
    public function cancel(User $user, Booking $booking): bool
    {
        return ($user->role ?: 'CUSTOMER') === 'CUSTOMER' && $user->id === $booking->user_id
            && in_array($booking->status, ['PENDING_PAYMENT', 'CONFIRMED'], true);
    }

    /**
     * Determine if user can confirm payment
     */
    public function confirmPayment(User $user, Booking $booking): bool
    {
        return ($user->role ?: 'CUSTOMER') === 'CUSTOMER' && $user->id === $booking->user_id
            && $booking->status === 'PENDING_PAYMENT';
    }
}
