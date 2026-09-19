<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminBookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_search_booking_by_code_and_customer(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $customer = User::factory()->create(['name' => 'Khách UC48']);
        Booking::create(['booking_code' => 'UC48-SEARCH', 'user_id' => $customer->id]);
        $this->actingAs($admin)->get(route('admin.bookings.index', ['search' => 'UC48-SEARCH']))->assertOk()->assertSee('Khách UC48');
    }

    public function test_status_change_creates_audit_log(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $booking = $this->booking('CONFIRMED');
        $this->travelTo(now()->setTime(9, 0));
        $type = \App\Models\CourtType::create(['name' => 'Audit']);
        $court = \App\Models\Court::create(['code' => 'AUDIT', 'name' => 'Audit court', 'court_type_id' => $type->id]);
        $slot = \App\Models\TimeSlot::create(['name' => '09-10', 'start_time' => '09:00', 'end_time' => '10:00', 'duration' => 60]);
        $booking->bookingDetails()->create(['court_id' => $court->id, 'time_slot_id' => $slot->id, 'booking_date' => today(), 'price' => 100000, 'subtotal' => 100000]);
        $booking->update(['payment_status' => 'PAID']);
        $booking->payment()->create(['amount' => 100000, 'status' => 'PAID', 'paid_at' => now()]);
        $this->actingAs($admin)->put(route('admin.bookings.update', $booking), ['status' => 'CHECKED_IN', 'note' => 'Đã xác minh', 'reason' => 'Khách đã đến sân.'])->assertRedirect();
        $this->assertDatabaseHas('booking_audit_logs', ['booking_id' => $booking->id, 'actor_id' => $admin->id, 'action' => 'UPDATED', 'reason' => 'Khách đã đến sân.']);
        $this->assertSame('CHECKED_IN', $booking->fresh()->status);
    }

    public function test_invalid_terminal_booking_change_is_blocked(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $booking = $this->booking('COMPLETED');
        $this->actingAs($admin)->put(route('admin.bookings.update', $booking), ['status' => 'CONFIRMED', 'reason' => 'Thử sửa.'])->assertSessionHas('error');
        $this->assertDatabaseCount('booking_audit_logs', 0);
    }

    public function test_cancellation_creates_audit_log(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $booking = $this->booking('PENDING_PAYMENT');
        $this->actingAs($admin)->put(route('admin.bookings.cancel', $booking), ['reason' => 'Khách yêu cầu hủy.'])->assertRedirect();
        $this->assertSame('CANCELLED', $booking->fresh()->status);
        $this->assertDatabaseHas('booking_audit_logs', ['booking_id' => $booking->id, 'action' => 'CANCELLED']);
    }

    private function booking(string $status): Booking
    {
        $customer = User::factory()->create(['role' => 'CUSTOMER']);

        return Booking::create(['booking_code' => 'UC48-'.uniqid(), 'user_id' => $customer->id, 'status' => $status]);
    }
}
