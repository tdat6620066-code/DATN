<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingDetail;
use App\Models\Court;
use App\Models\CourtPrice;
use App\Models\CourtType;
use App\Models\TimeSlot;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingCopilotTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_previews_from_own_history_and_only_books_after_confirmation(): void
    {
        $user = User::factory()->create(['role' => 'CUSTOMER']);
        $otherUser = User::factory()->create(['role' => 'CUSTOMER']);
        $type = CourtType::create(['name' => 'Tiêu chuẩn', 'status' => 'ACTIVE']);
        $ownCourt = Court::create(['code' => 'OWN-1', 'name' => 'Sân quen', 'court_type_id' => $type->id, 'status' => 'ACTIVE', 'operational_status' => 'AVAILABLE']);
        $otherCourt = Court::create(['code' => 'OTHER-1', 'name' => 'Sân người khác', 'court_type_id' => $type->id, 'status' => 'ACTIVE', 'operational_status' => 'AVAILABLE']);
        $slot = TimeSlot::create(['name' => 'Tối', 'start_time' => '19:00', 'end_time' => '20:00', 'duration' => 60, 'status' => 'ACTIVE']);

        foreach ([$ownCourt, $otherCourt] as $court) {
            CourtPrice::create([
                'court_id' => $court->id, 'time_slot_id' => $slot->id, 'price' => 200000,
                'day_type' => 'WEEKDAY', 'effective_from' => today()->subYear(), 'status' => 'ACTIVE',
            ]);
        }
        Voucher::create([
            'code' => 'BEST20', 'name' => 'Giảm tốt nhất', 'discount_type' => 'PERCENTAGE',
            'discount_value' => 20, 'min_order_amount' => 0, 'start_at' => now()->subDay(),
            'end_at' => now()->addMonth(), 'status' => 'ACTIVE',
        ]);

        $this->historicalBooking($otherUser, $otherCourt, $slot, 'BK-OTHER');
        $this->historicalBooking($user, $ownCourt, $slot, 'BK-OWN');

        $previewResponse = $this->actingAs($user)->postJson(route('api.ai.chat'), [
            'message' => 'Đặt cho tôi sân như tuần trước vào tối mai',
        ])->assertOk()
            ->assertJsonPath('data.intent', 'BOOKING_PREVIEW')
            ->assertJsonPath('data.booking_preview.court_id', $ownCourt->id)
            ->assertJsonPath('data.booking_preview.voucher_code', 'BEST20')
            ->assertJsonPath('data.booking_preview.total', 160000);

        $this->assertSame(2, Booking::count(), 'Preview must not create a booking.');
        $token = $previewResponse->json('data.buttons.0.id');

        $this->actingAs($user)->postJson(route('api.ai.chat'), [
            'action' => 'confirm_copilot_booking',
            'choice_id' => $token,
        ])->assertOk()
            ->assertJsonPath('data.intent', 'COPILOT_BOOKING_CREATED')
            ->assertJsonPath('data.booking_total', 160000);

        $created = Booking::query()->where('user_id', $user->id)->where('status', 'PENDING_PAYMENT')->firstOrFail();
        $this->assertSame($ownCourt->id, $created->bookingDetails()->value('court_id'));
        $this->assertSame('40000.00', $created->discount);
    }

    private function historicalBooking(User $user, Court $court, TimeSlot $slot, string $code): Booking
    {
        $booking = Booking::create([
            'booking_code' => $code,
            'user_id' => $user->id,
            'subtotal' => 200000,
            'discount' => 0,
            'total_amount' => 200000,
            'status' => 'COMPLETED',
            'payment_status' => 'PAID',
        ]);
        BookingDetail::create([
            'booking_id' => $booking->id,
            'court_id' => $court->id,
            'booking_date' => today()->subWeek(),
            'time_slot_id' => $slot->id,
            'price' => 200000,
            'subtotal' => 200000,
            'status' => 'COMPLETED',
        ]);

        return $booking;
    }
}
