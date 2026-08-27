<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingDetail;
use App\Models\Court;
use App\Models\CourtType;
use App\Models\Promotion;
use App\Models\TimeSlot;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_endpoints_require_authentication(): void
    {
        $this->getJson(route('api.ai.courts'))->assertUnauthorized();
        $this->postJson(route('api.ai.chat'), ['message' => 'Giá sân?'])->assertUnauthorized();
    }

    public function test_new_customer_receives_personalized_promotion(): void
    {
        $customer = User::factory()->create(['role' => 'CUSTOMER']);

        $this->actingAs($customer)->getJson(route('api.ai.promotions.me'))
            ->assertOk()
            ->assertJsonPath('data.segment', 'NEW')
            ->assertJsonPath('data.discount_percent', 15);

        $this->assertDatabaseHas('ai_promotion_recommendations', ['user_id' => $customer->id, 'segment' => 'NEW']);
    }

    public function test_chatbot_answers_and_logs_price_question(): void
    {
        $customer = User::factory()->create(['role' => 'CUSTOMER']);

        $this->actingAs($customer)->postJson(route('api.ai.chat'), ['message' => 'Giá thuê sân bao nhiêu?'])
            ->assertOk()
            ->assertJsonPath('data.understood', true)
            ->assertJsonStructure(['data' => ['answer', 'suggestions']]);

        $this->assertDatabaseHas('ai_interactions', ['user_id' => $customer->id, 'type' => 'CHATBOT', 'status' => 'SUCCESS']);
        $this->assertDatabaseHas('chatbot_logs', [
            'user_id' => $customer->id,
            'question' => 'GiÃ¡ thuÃª sÃ¢n bao nhiÃªu?',
            'status' => 'SUCCESS',
        ]);
    }

    public function test_chatbot_lists_active_promotions(): void
    {
        $customer = User::factory()->create(['role' => 'CUSTOMER']);
        Promotion::create(['title' => 'Giảm 20% giờ thấp điểm', 'status' => 'ACTIVE', 'start_at' => now()->subDay(), 'end_at' => now()->addDay()]);

        $this->actingAs($customer)->postJson(route('api.ai.chat'), ['message' => 'Có khuyến mãi nào?'])
            ->assertOk()->assertJsonFragment(['understood' => true]);
    }

    public function test_ai_booking_copilot_reads_recent_history_and_builds_preview(): void
    {
        $customer = User::factory()->create(['role' => 'CUSTOMER']);
        $courtType = CourtType::create(['name' => 'VIP', 'status' => 'ACTIVE']);
        $court = Court::create([
            'code' => 'CT-001',
            'name' => 'Sân VIP 1',
            'court_type_id' => $courtType->id,
            'address' => '123 Đường ABC',
            'opening_time' => '06:00',
            'closing_time' => '22:00',
            'status' => 'ACTIVE',
            'operational_status' => 'AVAILABLE',
        ]);
        $slot = TimeSlot::create([
            'name' => '19:00 - 20:00',
            'start_time' => '19:00',
            'end_time' => '20:00',
            'duration' => 60,
            'status' => 'ACTIVE',
        ]);
        $court->prices()->create([
            'time_slot_id' => $slot->id,
            'price' => 180000,
            'day_type' => 'WEEKDAY',
            'status' => 'ACTIVE',
            'effective_from' => now()->subDay()->toDateString(),
        ]);
        $lastBooking = Booking::create([
            'booking_code' => 'BK-OLD-001',
            'user_id' => $customer->id,
            'subtotal' => 180000,
            'discount' => 0,
            'total_amount' => 180000,
            'status' => 'CONFIRMED',
            'payment_status' => 'PAID',
        ]);
        BookingDetail::create([
            'booking_id' => $lastBooking->id,
            'court_id' => $court->id,
            'booking_date' => now()->subWeek()->toDateString(),
            'time_slot_id' => $slot->id,
            'price' => 180000,
            'subtotal' => 180000,
            'status' => 'CONFIRMED',
        ]);
        Voucher::create([
            'code' => 'SAVE10',
            'name' => 'Giảm 10%',
            'discount_type' => 'PERCENTAGE',
            'discount_value' => 10,
            'min_order_amount' => 100000,
            'max_discount' => 50000,
            'start_at' => now()->subDay(),
            'end_at' => now()->addMonth(),
            'usage_limit' => 100,
            'used_count' => 0,
            'status' => 'ACTIVE',
        ]);

        $response = $this->actingAs($customer)->postJson(route('api.ai.chat'), ['message' => 'đặt cho tôi sân như tuần trước vào tối mai']);

        $response->assertOk()->assertJsonPath('data.understood', true)
            ->assertJsonPath('data.intent', 'BOOKING_PREVIEW')
            ->assertJsonPath('data.preview.available', true)
            ->assertJsonPath('data.preview.voucher.code', 'SAVE10');
    }

    public function test_customer_cannot_run_admin_analytics(): void
    {
        $customer = User::factory()->create(['role' => 'CUSTOMER']);

        $this->actingAs($customer)->getJson(route('api.ai.forecast'))->assertForbidden();
        $this->actingAs($customer)->postJson(route('api.ai.reviews.analyze'))->assertForbidden();
    }

    public function test_recommendations_expose_data_sufficiency_and_match_score(): void
    {
        $customer = User::factory()->create(['role' => 'CUSTOMER']);

        $this->actingAs($customer)->getJson(route('api.ai.courts', ['area' => 'Quận 1']))
            ->assertOk()
            ->assertJsonPath('data.data_sufficient', true)
            ->assertJsonStructure(['data' => ['data_sufficient', 'recommendations']]);
    }
}
