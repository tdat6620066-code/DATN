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

class AdminMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_maintenance_range_and_court_becomes_unavailable(): void
    {
        [$admin,$court,$slot] = $this->data();
        $date = today()->addDays(3);
        $this->actingAs($admin)->post(route('admin.maintenance.store'), ['court_id' => $court->id, 'start_date' => $date->toDateString(), 'end_date' => $date->copy()->addDay()->toDateString(), 'reason' => 'Sơn lại mặt sân.'])->assertRedirect();
        $this->assertDatabaseHas('maintenance_schedules', ['court_id' => $court->id, 'reason' => 'Sơn lại mặt sân.']);
        $this->assertSame(CourtAvailabilityService::STATUS_MAINTENANCE, app(CourtAvailabilityService::class)->checkAvailability($court->id, $date, $slot->id));
    }

    public function test_maintenance_is_blocked_when_confirmed_booking_exists(): void
    {
        [$admin,$court,$slot] = $this->data();
        $customer = User::factory()->create();
        $date = today()->addDays(2);
        $booking = Booking::create(['booking_code' => 'MAINT-BK', 'user_id' => $customer->id, 'status' => 'CONFIRMED']);
        $booking->bookingDetails()->create(['court_id' => $court->id, 'booking_date' => $date, 'time_slot_id' => $slot->id, 'price' => 100000, 'subtotal' => 100000, 'status' => 'CONFIRMED']);
        $this->actingAs($admin)->post(route('admin.maintenance.store'), ['court_id' => $court->id, 'start_date' => $date->toDateString(), 'end_date' => $date->toDateString(), 'reason' => 'Bảo trì.'])->assertSessionHas('affected_bookings');
        $this->assertDatabaseCount('maintenance_schedules', 0);
    }

    public function test_invalid_date_range_is_rejected(): void
    {
        [$admin,$court] = $this->data();
        $this->actingAs($admin)->post(route('admin.maintenance.store'), ['court_id' => $court->id, 'start_date' => today()->addDays(3)->toDateString(), 'end_date' => today()->addDay()->toDateString(), 'reason' => 'Bảo trì.'])->assertSessionHasErrors('end_date');
    }

    private function data(): array
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $type = CourtType::create(['name' => 'Maintenance Type']);
        $court = Court::create(['code' => 'MAINT-COURT', 'name' => 'Maintenance Court', 'court_type_id' => $type->id]);
        $slot = TimeSlot::create(['name' => '10:00 - 11:00', 'start_time' => '10:00', 'end_time' => '11:00', 'duration' => 60, 'status' => 'ACTIVE']);

        return [$admin, $court, $slot];
    }
}
