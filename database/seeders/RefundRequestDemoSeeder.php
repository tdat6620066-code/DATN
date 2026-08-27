<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Court;
use App\Models\Payment;
use App\Models\RefundRequest;
use App\Models\TimeSlot;
use App\Models\User;
use Illuminate\Database\Seeder;

class RefundRequestDemoSeeder extends Seeder
{
    public function run(): void
    {
        $employee = User::updateOrCreate(['email' => 'employee@smashzone.test'], [
            'name' => 'Nhân viên SmashZone', 'password' => bcrypt('password'),
            'role' => 'EMPLOYEE', 'phone' => '0900000002', 'refund_approval_limit' => 1000000,
            'permissions' => ['employee.dashboard', 'bookings.view', 'bookings.checkin', 'bookings.checkout', 'payments.counter', 'services.manage', 'incidents.manage', 'refunds.manage', 'courts.status.manage'],
        ]);
        $customer = User::updateOrCreate(['email' => 'customer@smashzone.test'], [
            'name' => 'Khách hàng Demo', 'password' => bcrypt('password'), 'role' => 'CUSTOMER',
            'phone' => '0900000003',
        ]);
        $booking = Booking::updateOrCreate(['booking_code' => 'BK-UC39-DEMO'], [
            'user_id' => $customer->id, 'subtotal' => 300000, 'total_amount' => 300000,
            'status' => 'CONFIRMED', 'payment_status' => 'PAID', 'confirmed_at' => now(),
        ]);
        Payment::updateOrCreate(['booking_id' => $booking->id], [
            'amount' => 300000, 'status' => 'PAID', 'paid_at' => now(), 'payment_method' => 'BANK_TRANSFER',
        ]);
        RefundRequest::firstOrCreate(['booking_id' => $booking->id], [
            'requested_by' => $customer->id, 'amount' => 300000,
            'reason' => 'Khách hàng bị chấn thương và không thể đến sân.',
            'supporting_information' => 'Đã gửi giấy xác nhận qua bộ phận hỗ trợ.',
        ]);

        $court = Court::query()->first();
        $timeSlot = TimeSlot::query()->first();
        if ($court && $timeSlot) {
            $activeBooking = Booking::updateOrCreate(['booking_code' => 'BK-UC38-ACTIVE'], [
                'user_id' => $customer->id, 'subtotal' => 150000, 'total_amount' => 150000,
                'status' => 'CHECKED_IN', 'payment_status' => 'PAID',
                'confirmed_at' => now()->subHours(2), 'checked_in_at' => now()->subHour(),
            ]);
            Payment::updateOrCreate(['booking_id' => $activeBooking->id], [
                'amount' => 150000, 'status' => 'PAID', 'paid_at' => now()->subHours(2),
                'payment_method' => 'CASH',
            ]);
            $activeBooking->bookingDetails()->updateOrCreate([
                'court_id' => $court->id, 'booking_date' => today(), 'time_slot_id' => $timeSlot->id,
            ], ['price' => 150000, 'subtotal' => 150000, 'status' => 'CONFIRMED']);
            $court->update(['availability_status' => 'OCCUPIED']);
        }
    }
}
