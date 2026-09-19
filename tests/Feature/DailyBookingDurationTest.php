<?php

namespace Tests\Feature;

use App\Models\{Booking, Court, CourtType, TimeSlot, User};
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyBookingDurationTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $user = User::factory()->create(['role' => 'CUSTOMER']);
        $type = CourtType::create(['name' => 'Standard', 'status' => 'ACTIVE']);
        $court = Court::create(['code' => 'LONG', 'name' => 'Court', 'court_type_id' => $type->id, 'status' => 'ACTIVE']);
        $slots = collect(range(8, 11))->map(fn ($hour) => TimeSlot::create(['name' => "$hour:00", 'start_time' => sprintf('%02d:00', $hour), 'end_time' => sprintf('%02d:00', $hour + 1), 'duration' => 60, 'status' => 'ACTIVE']));
        $this->actingAs($user);
        return [$user, $court, $slots, ['court_id' => $court->id, 'booking_date' => today()->addDay()->toDateString(), 'time_slot_ids' => $slots->pluck('id')->all()]];
    }

    public function test_four_hours_requires_confirmation_before_creating_booking(): void
    {
        [, , , $data] = $this->fixture();
        $this->mock(BookingService::class)->shouldNotReceive('createBooking');
        $this->from(route('bookings.create'))->post(route('bookings.store'), $data)
            ->assertSessionHasErrors('daily_duration_confirmed');
        $this->assertDatabaseCount('bookings', 0);
        $this->get(route('bookings.create'))->assertOk()->assertSee('Tôi đã cân nhắc thời lượng');
    }

    public function test_recurring_booking_requires_confirmation_for_long_days(): void
    {
        [, , , $data] = $this->fixture();
        $date = today()->addDay();
        $data += ['booking_type' => 'weekly', 'start_date' => $date->toDateString(), 'end_date' => $date->toDateString(), 'days_of_week' => [$date->dayOfWeek]];
        $this->postJson(route('bookings.recurring.preview'), $data)->assertUnprocessable()->assertJsonValidationErrors('daily_duration_confirmed');
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_confirmed_long_booking_and_short_booking_reach_existing_service(): void
    {
        [$user, , , $data] = $this->fixture();
        $booking = Booking::create(['booking_code' => 'CONFIRM-LONG', 'user_id' => $user->id, 'status' => 'PENDING_PAYMENT', 'payment_status' => 'PENDING', 'total_amount' => 0]);
        $this->mock(BookingService::class)->shouldReceive('createBooking')->twice()->andReturn($booking);
        $this->post(route('bookings.store'), $data + ['daily_duration_confirmed' => '1'])->assertSessionHasNoErrors()->assertRedirect(route('bookings.show', $booking));
        $data['time_slot_ids'] = array_slice($data['time_slot_ids'], 0, 3);
        $this->post(route('bookings.store'), $data)->assertSessionHasNoErrors()->assertRedirect(route('bookings.show', $booking));
    }

    public function test_existing_bookings_count_but_cancelled_and_expired_holds_do_not(): void
    {
        [$user, $court, $slots, $data] = $this->fixture();
        foreach (['CONFIRMED', 'CANCELLED', 'PENDING_PAYMENT'] as $status) {
            $booking = Booking::create(['booking_code' => $status, 'user_id' => $user->id, 'status' => $status, 'payment_status' => 'PENDING', 'total_amount' => 0, 'hold_expires_at' => now()->subMinute()]);
            $booking->bookingDetails()->create(['court_id' => $court->id, 'time_slot_id' => $slots[0]->id, 'booking_date' => $data['booking_date'], 'price' => 0, 'subtotal' => 0, 'status' => 'CONFIRMED']);
        }
        $data['time_slot_ids'] = $slots->slice(1)->pluck('id')->all();
        $this->assertSame(240, app(\App\Services\DailyBookingDurationService::class)->totalMinutes($user->id, $data['booking_date'], $data['time_slot_ids']));
        $this->postJson(route('bookings.store'), $data)->assertUnprocessable()->assertJsonValidationErrors('daily_duration_confirmed');
    }
}
