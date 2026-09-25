<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Court;
use App\Models\CourtType;
use App\Models\TimeSlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicBookingGuideTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_follow_detailed_booking_steps_without_creating_a_booking(): void
    {
        $type = CourtType::create(['name' => 'Tiêu chuẩn', 'status' => 'ACTIVE']);
        $court = Court::create([
            'code' => 'GUIDE-1', 'name' => 'Sân hướng dẫn', 'court_type_id' => $type->id,
            'address' => 'Đường Nguyễn Huệ', 'opening_time' => '06:00', 'closing_time' => '22:00',
            'status' => 'ACTIVE', 'operational_status' => 'AVAILABLE',
        ]);
        $slot = TimeSlot::create([
            'name' => '19:00 - 20:00', 'start_time' => '19:00', 'end_time' => '20:00',
            'duration' => 60, 'status' => 'ACTIVE',
        ]);
        $court->prices()->create([
            'time_slot_id' => $slot->id, 'price' => 200000, 'day_type' => 'WEEKDAY',
            'status' => 'ACTIVE', 'effective_from' => today()->subMonth(),
        ]);

        $this->postJson(route('api.ai.chat'), ['message' => 'Tôi muốn đặt sân'])
            ->assertOk()
            ->assertJsonPath('data.intent', 'PUBLIC_BOOKING_date')
            ->assertJsonPath('data.awaiting', 'date')
            ->assertJsonPath('data.suggestions.0', 'Hôm nay');

        $this->postJson(route('api.ai.chat'), ['message' => 'Ngày mai 19h'])
            ->assertOk()
            ->assertJsonPath('data.intent', 'PUBLIC_BOOKING_preferences')
            ->assertJsonPath('data.awaiting', 'preferences');

        $options = $this->postJson(route('api.ai.chat'), ['message' => 'Không'])
            ->assertOk()
            ->assertJsonPath('data.intent', 'PUBLIC_BOOKING_OPTIONS')
            ->assertJsonPath('data.buttons.0.action', 'public_select_booking_slot');

        $this->assertSame(0, Booking::count(), 'Guest guidance must not create a booking.');
        $button = $options->json('data.buttons.0');

        $ready = $this->postJson(route('api.ai.chat'), [
            'action' => $button['action'],
            'choice_id' => $button['id'],
        ])->assertOk()
            ->assertJsonPath('data.intent', 'PUBLIC_BOOKING_READY_TO_LOGIN')
            ->assertJsonPath('data.awaiting', 'login');

        $this->assertSame(0, Booking::count());
        $this->assertStringContainsString('booking_date='.today()->addDay()->toDateString(), $ready->json('data.redirect_url'));
        $this->assertSame(today()->addDay()->toDateString(), session('chatbot.public_booking_guide.date'));
        $this->assertNotNull(session('chatbot.public_booking_guide.expires_at'));
    }
}
