<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingDetail;
use App\Models\Court;
use App\Models\CourtPrice;
use App\Models\CourtType;
use App\Models\TimeSlot;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_overlapping_time_slot_is_rejected(): void
    {
        [$admin,$court,$slot] = $this->data();
        $this->actingAs($admin)->post(route('admin.pricing.slots.store'), ['name' => 'Trùng', 'start_time' => '09:30', 'end_time' => '10:30', 'duration' => 60])->assertSessionHas('error');
        $this->assertDatabaseMissing('time_slots', ['name' => 'Trùng']);
    }

    public function test_weekend_price_is_used_for_new_booking(): void
    {
        [$admin,$court,$slot] = $this->data();
        $date = now()->next('Saturday')->toDateString();
        CourtPrice::create(['court_id' => $court->id, 'time_slot_id' => $slot->id, 'price' => 100000, 'day_type' => 'WEEKDAY', 'effective_from' => today(), 'status' => 'ACTIVE']);
        CourtPrice::create(['court_id' => $court->id, 'time_slot_id' => $slot->id, 'price' => 180000, 'day_type' => 'WEEKEND', 'effective_from' => today(), 'status' => 'ACTIVE']);
        $customer = User::factory()->create();
        $booking = app(BookingService::class)->createBooking($customer->id, [['court_id' => $court->id, 'booking_date' => $date, 'time_slot_id' => $slot->id]]);
        $this->assertSame('180000.00', $booking->bookingDetails()->first()->price);
    }

    public function test_new_price_does_not_change_existing_booking_price(): void
    {
        [$admin,$court,$slot] = $this->data();
        $customer = User::factory()->create();
        $booking = Booking::create(['booking_code' => 'PRICE-SNAPSHOT', 'user_id' => $customer->id, 'status' => 'CONFIRMED']);
        BookingDetail::create(['booking_id' => $booking->id, 'court_id' => $court->id, 'booking_date' => today(), 'time_slot_id' => $slot->id, 'price' => 120000, 'subtotal' => 120000, 'status' => 'CONFIRMED']);
        $this->actingAs($admin)->put(route('admin.pricing.prices.update'), ['court_id' => $court->id, 'prices' => [$slot->id => ['WEEKDAY' => 250000]]])->assertRedirect();
        $this->assertSame('120000.00', $booking->bookingDetails()->first()->price);
        $this->assertDatabaseHas('court_prices', ['court_id' => $court->id, 'price' => 250000, 'day_type' => 'WEEKDAY']);
    }

    private function data(): array
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $type = CourtType::create(['name' => 'Pricing Type']);
        $court = Court::create(['code' => 'PRICE-COURT', 'name' => 'Pricing Court', 'court_type_id' => $type->id]);
        $slot = TimeSlot::create(['name' => '09:00 - 10:00', 'start_time' => '09:00', 'end_time' => '10:00', 'duration' => 60, 'status' => 'ACTIVE']);

        return [$admin, $court, $slot];
    }
}
