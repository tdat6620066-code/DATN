<?php

namespace Tests\Feature;

use App\Models\{Booking, Court, CourtType, TimeSlot, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourtUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_availability_uses_route_court_and_accepts_today(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-09-19 07:00:00'));
        [$courts] = $this->setupCourts(2);
        $url = route('courts.availability', ['court' => $courts[0], 'booking_date' => today()->toDateString()]);
        $this->getJson($url)->assertOk()->assertJsonPath('court_id', $courts[0]->id);
        $this->getJson($url.'&court_id='.$courts[1]->id)->assertOk()->assertJsonPath('court_id', $courts[0]->id);
        $this->getJson(route('courts.availability', ['court' => $courts[0], 'booking_date' => today()->addDays(31)->toDateString()]))->assertStatus(400);
    }

    public function test_detail_uses_booking_weekend_price_in_both_schedules(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-09-19 07:00:00'));
        [$courts, $slots] = $this->setupCourts();
        $court = $courts->first();
        $slot = $slots->first();
        $court->prices()->create(['time_slot_id' => $slot->id, 'price' => 180000, 'day_type' => 'WEEKEND', 'status' => 'ACTIVE', 'effective_from' => today()->subDay()->toDateString()]);
        $this->assertEquals(180000, app(\App\Services\BookingService::class)->getCurrentPrice($court->id, $slot->id, \Carbon\Carbon::today()));
        $this->get(route('courts.show', $court))->assertOk()
            ->assertViewHas('availability', fn ($data) => (float) $data[$slot->id]['price'] === 180000.0)
            ->assertViewHas('scheduleAvailability', fn ($data) => (float) $data[$court->id][$slot->id]['price'] === 180000.0);
    }

    private function setupCourts(int $count = 1): array
    {
        $type = CourtType::create(['name' => 'Trong nhà', 'status' => 'ACTIVE']);
        $slots = collect([8, 9, 10])->map(fn ($hour) => TimeSlot::create([
            'name' => "$hour:00", 'start_time' => sprintf('%02d:00', $hour),
            'end_time' => sprintf('%02d:00', $hour + 1), 'duration' => 60, 'status' => 'ACTIVE',
        ]));
        $courts = collect(range(1, $count))->map(function ($index) use ($type, $slots) {
            $court = Court::create(['code' => 'UI-'.$index, 'name' => sprintf('Court %02d', $index), 'court_type_id' => $type->id, 'status' => 'ACTIVE', 'operational_status' => 'AVAILABLE']);
            foreach ($slots as $slot) $court->prices()->create(['time_slot_id' => $slot->id, 'price' => 120000, 'status' => 'ACTIVE', 'effective_from' => today()]);
            return $court;
        });
        return [$courts, $slots];
    }

    private function reserve(Court $court, TimeSlot $slot, string $status): void
    {
        $booking = Booking::create(['booking_code' => 'UI-'.str()->random(10), 'user_id' => User::factory()->create()->id, 'status' => $status, 'payment_status' => 'PENDING', 'total_amount' => 120000, 'hold_expires_at' => now()->addMinutes(5)]);
        $booking->bookingDetails()->create(['court_id' => $court->id, 'time_slot_id' => $slot->id, 'booking_date' => today()->addDay(), 'price' => 120000, 'subtotal' => 120000, 'status' => $status === 'PENDING_PAYMENT' ? 'PENDING' : 'CONFIRMED']);
    }

    public function test_status_filter_runs_before_pagination_and_retains_query(): void
    {
        [$courts, $slots] = $this->setupCourts(14);
        $this->reserve($courts->first(), $slots->first(), 'CONFIRMED');
        $filters = ['booking_date' => today()->addDay()->toDateString(), 'time_slot_id' => $slots->first()->id, 'availability_status' => 'AVAILABLE'];
        $this->get(route('courts.index', $filters))->assertOk()
            ->assertViewHas('courts', fn ($page) => $page->total() === 13 && $page->count() === 12)
            ->assertDontSee('Court 01')->assertSee('availability_status=AVAILABLE');
        $this->get(route('courts.index', $filters + ['page' => 2]))->assertOk()
            ->assertViewHas('courts', fn ($page) => $page->count() === 1)->assertSee('Court 14');
    }

    public function test_status_requires_date_and_slot_and_invalid_status_is_rejected(): void
    {
        $this->getJson(route('courts.index', ['availability_status' => 'AVAILABLE']))
            ->assertUnprocessable()->assertJsonValidationErrors(['booking_date', 'time_slot_id']);
        $this->getJson(route('courts.index', ['availability_status' => 'UNKNOWN']))
            ->assertUnprocessable()->assertJsonValidationErrors('availability_status');
    }

    public function test_detail_exposes_text_states_and_preserves_booking_contract(): void
    {
        [$courts, $slots] = $this->setupCourts();
        $court = $courts->first();
        $this->reserve($court, $slots[1], 'CONFIRMED');
        $this->reserve($court, $slots[2], 'PENDING_PAYMENT');
        $response = $this->get(route('courts.show', ['court' => $court, 'booking_date' => today()->addDay()->toDateString()]));
        $response->assertOk()->assertSee('08:00 – 09:00')->assertSee('Còn trống')
            ->assertSee('09:00 – 10:00')->assertSee('Đã đặt')->assertSee('Đang giữ')
            ->assertSee('name="_token"', false)->assertSee('name="voucher_code"', false)
            ->assertSee('id="bookingForm"', false)->assertSee(route('bookings.store'));
        $this->assertMatchesRegularExpression('/<button[^>]*booked[^>]*disabled[^>]*>.*?Đã đặt/s', $response->getContent());
        $this->assertMatchesRegularExpression('/<button[^>]*held[^>]*disabled[^>]*>.*?Đang giữ/s', $response->getContent());
    }

    public function test_empty_results_are_explicit_and_unfiltered_cards_do_not_claim_availability(): void
    {
        $this->get(route('courts.index'))->assertOk()->assertSee('Chưa tìm thấy sân phù hợp');
        $this->setupCourts();
        $this->get(route('courts.index'))->assertOk()->assertSee('Chọn giờ để xem lịch');
    }
}
