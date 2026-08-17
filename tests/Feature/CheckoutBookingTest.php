<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Court;
use App\Models\CourtType;
use App\Models\TimeSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutBookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_can_checkout_a_checked_in_booking(): void
    {
        [$employee, $booking, $court] = $this->makeBooking('CHECKED_IN');

        $response = $this->actingAs($employee)->postJson(route('bookings.checkout', $booking));

        $response->assertOk()
            ->assertJsonPath('data.booking_id', $booking->id)
            ->assertJsonPath('data.status', 'COMPLETED');
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'COMPLETED',
        ]);
        $this->assertNotNull($booking->fresh()->checked_out_at);
        $this->assertDatabaseHas('booking_details', [
            'booking_id' => $booking->id,
            'status' => 'COMPLETED',
        ]);
        $this->assertSame('AVAILABLE', $court->fresh()->availability_status);
    }

    public function test_booking_that_has_not_checked_in_cannot_be_checked_out(): void
    {
        [$employee, $booking] = $this->makeBooking('CONFIRMED');

        $this->actingAs($employee)
            ->postJson(route('bookings.checkout', $booking))
            ->assertStatus(422);

        $this->assertSame('CONFIRMED', $booking->fresh()->status);
        $this->assertNull($booking->fresh()->checked_out_at);
    }

    public function test_customer_cannot_checkout_a_booking(): void
    {
        [, $booking] = $this->makeBooking('CHECKED_IN');
        $customer = User::factory()->create(['role' => 'CUSTOMER']);

        $this->actingAs($customer)
            ->postJson(route('bookings.checkout', $booking))
            ->assertForbidden();

        $this->assertSame('CHECKED_IN', $booking->fresh()->status);
    }

    private function makeBooking(string $status): array
    {
        $employee = User::factory()->create(['role' => 'EMPLOYEE']);
        $customer = User::factory()->create();
        $courtType = CourtType::create([
            'name' => 'Sân tiêu chuẩn',
            'status' => 'ACTIVE',
        ]);
        $court = Court::create([
            'code' => 'COURT-UC38',
            'name' => 'Sân UC38',
            'court_type_id' => $courtType->id,
            'status' => 'ACTIVE',
            'availability_status' => 'OCCUPIED',
        ]);
        $slot = TimeSlot::create([
            'name' => '18:00 - 19:00',
            'start_time' => '18:00',
            'end_time' => '19:00',
            'duration' => 60,
            'status' => 'ACTIVE',
        ]);
        $booking = Booking::create([
            'booking_code' => 'UC38-'.uniqid(),
            'user_id' => $customer->id,
            'status' => $status,
            'payment_status' => 'PAID',
            'checked_in_at' => $status === 'CHECKED_IN' ? now()->subHour() : null,
        ]);
        $booking->bookingDetails()->create([
            'court_id' => $court->id,
            'booking_date' => today(),
            'time_slot_id' => $slot->id,
            'price' => 100000,
            'subtotal' => 100000,
            'status' => 'CONFIRMED',
        ]);

        return [$employee, $booking, $court];
    }
}
