<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Court;
use App\Models\CourtType;
use App\Models\TimeSlot;
use App\Models\User;
use App\Services\CourtAvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourtStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_can_put_unused_court_into_maintenance(): void
    {
        [$employee, $court] = $this->makeCourt();

        $this->actingAs($employee)->putJson(route('employee.courts.update', $court), [
            'operational_status' => 'MAINTENANCE',
            'status_reason' => 'Thay lưới và bảo dưỡng mặt sân.',
        ])->assertOk()->assertJsonPath('data.operational_status', 'MAINTENANCE');

        $this->assertDatabaseHas('courts', ['id' => $court->id, 'operational_status' => 'MAINTENANCE']);
        $this->assertNull(app(CourtAvailabilityService::class)->checkAvailability($court->id, today(), TimeSlot::first()->id));
    }

    public function test_reason_is_required_when_court_is_locked(): void
    {
        [$employee, $court] = $this->makeCourt();

        $this->actingAs($employee)->putJson(route('employee.courts.update', $court), [
            'operational_status' => 'LOCKED',
        ])->assertUnprocessable();

        $this->assertSame('AVAILABLE', $court->fresh()->operational_status);
    }

    public function test_court_with_active_booking_cannot_enter_maintenance(): void
    {
        [$employee, $court] = $this->makeCourt();
        $customer = User::factory()->create();
        $booking = Booking::create([
            'booking_code' => 'UC40-BOOKED', 'user_id' => $customer->id,
            'status' => 'CONFIRMED', 'payment_status' => 'PAID',
        ]);
        $booking->bookingDetails()->create([
            'court_id' => $court->id, 'booking_date' => today(),
            'time_slot_id' => TimeSlot::first()->id, 'price' => 100000,
            'subtotal' => 100000, 'status' => 'CONFIRMED',
        ]);

        $this->actingAs($employee)->putJson(route('employee.courts.update', $court), [
            'operational_status' => 'MAINTENANCE', 'status_reason' => 'Bảo trì.',
        ])->assertUnprocessable();

        $this->assertSame('AVAILABLE', $court->fresh()->operational_status);
    }

    public function test_customer_cannot_manage_court_status(): void
    {
        [, $court] = $this->makeCourt();
        $customer = User::factory()->create(['role' => 'CUSTOMER']);

        $this->actingAs($customer)->putJson(route('employee.courts.update', $court), [
            'operational_status' => 'AVAILABLE',
        ])->assertForbidden();
    }

    private function makeCourt(): array
    {
        $employee = User::factory()->create(['role' => 'EMPLOYEE']);
        $type = CourtType::create(['name' => 'Sân tiêu chuẩn', 'status' => 'ACTIVE']);
        $court = Court::create([
            'code' => 'UC40-COURT', 'name' => 'Sân UC40',
            'court_type_id' => $type->id, 'status' => 'ACTIVE',
        ]);
        TimeSlot::create([
            'name' => '08:00 - 09:00', 'start_time' => '08:00',
            'end_time' => '09:00', 'duration' => 60, 'status' => 'ACTIVE',
        ]);

        return [$employee, $court];
    }
}
