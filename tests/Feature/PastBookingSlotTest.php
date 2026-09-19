<?php

namespace Tests\Feature;

use App\Models\{Court, CourtType, TimeSlot, User};
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PastBookingSlotTest extends TestCase
{
    use RefreshDatabase;

    public function test_calendar_disables_started_slots_but_keeps_tomorrow_available(): void
    {
        $this->travelTo(today()->setTime(17, 0));
        $user = User::factory()->create();
        $type = CourtType::create(['name' => 'Standard', 'status' => 'ACTIVE']);
        $court = Court::create(['code' => 'PAST', 'name' => 'Test court', 'court_type_id' => $type->id, 'status' => 'ACTIVE']);
        $slots = collect([16, 17, 18])->map(fn ($hour) => TimeSlot::create([
            'name' => "$hour:00", 'start_time' => "$hour:00", 'end_time' => ($hour + 1).':00', 'duration' => 60, 'status' => 'ACTIVE',
        ]));
        $this->actingAs($user)->get('/booking/create')->assertOk()
            ->assertViewHas('availabilityData', fn ($data) =>
                $data[$court->id][$slots[0]->id]['status'] === 'PAST'
                && $data[$court->id][$slots[1]->id]['status'] === 'PAST'
                && $data[$court->id][$slots[2]->id]['status'] === 'AVAILABLE')
            ->assertSee('Đã qua');
        $this->get('/booking/create?booking_date='.today()->addDay()->toDateString())->assertOk()
            ->assertViewHas('availabilityData', fn ($data) => collect($data[$court->id])->every(fn ($slot) => $slot['status'] === 'AVAILABLE'));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Ngày đặt không hợp lệ');
        try {
            app(BookingService::class)->createBooking($user->id, [[
                'court_id' => $court->id, 'booking_date' => today()->toDateString(), 'time_slot_id' => $slots[1]->id,
            ]]);
        } catch (\Exception $e) {
            throw new \Exception(json_decode($e->getMessage(), true)[0]['message'] ?? $e->getMessage());
        }
    }
}
