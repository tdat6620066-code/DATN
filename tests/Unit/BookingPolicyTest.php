<?php

namespace Tests\Unit;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use App\Policies\BookingPolicy;
use PHPUnit\Framework\TestCase;

class BookingPolicyTest extends TestCase
{
    public function test_paid_booking_cannot_be_cancelled(): void
    {
        $user = new User(['role' => 'CUSTOMER']);
        $user->id = 10;
        $booking = new Booking(['user_id' => 10, 'status' => 'CONFIRMED', 'payment_status' => 'PAID']);
        $booking->setRelation('payment', new Payment(['status' => 'PAID']));

        $this->assertFalse((new BookingPolicy)->cancel($user, $booking));
    }

    public function test_unpaid_pending_booking_can_be_cancelled_by_its_owner(): void
    {
        $user = new User(['role' => 'CUSTOMER']);
        $user->id = 10;
        $booking = new Booking(['user_id' => 10, 'status' => 'PENDING_PAYMENT', 'payment_status' => 'PENDING']);
        $booking->setRelation('payment', new Payment(['status' => 'PENDING']));

        $this->assertTrue((new BookingPolicy)->cancel($user, $booking));
    }
}
