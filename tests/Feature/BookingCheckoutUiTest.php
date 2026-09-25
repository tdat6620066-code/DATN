<?php

namespace Tests\Feature;

use App\Models\{Booking, Court, CourtType, ServiceItem, TimeSlot, User, Voucher};
use App\Services\{BookingService, PaymentService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingCheckoutUiTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;
    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setTime(12, 0));
        $this->customer = User::factory()->create(['role' => 'CUSTOMER']);
        $type = CourtType::create(['name' => 'Standard', 'status' => 'ACTIVE']);
        $court = Court::create(['code' => 'CHECKOUT', 'name' => 'Sân Checkout', 'court_type_id' => $type->id, 'status' => 'ACTIVE', 'operational_status' => 'AVAILABLE']);
        $slot = TimeSlot::create(['name' => '19:00 - 20:00', 'start_time' => '19:00', 'end_time' => '20:00', 'duration' => 60, 'status' => 'ACTIVE']);
        $court->prices()->create(['time_slot_id' => $slot->id, 'price' => 150000, 'effective_from' => today(), 'status' => 'ACTIVE']);
        $service = ServiceItem::create(['code' => 'WATER', 'name' => 'Nước uống', 'category' => 'SALE', 'price' => 10000, 'stock' => 10, 'is_active' => true]);
        Voucher::create(['code' => 'SAVE20', 'name' => 'Giảm 20%', 'discount_type' => 'PERCENTAGE', 'discount_value' => 20, 'min_order_amount' => 0, 'start_at' => now()->subDay(), 'end_at' => now()->addDay(), 'status' => 'ACTIVE']);
        $this->booking = app(BookingService::class)->createBooking($this->customer->id, [[
            'court_id' => $court->id, 'booking_date' => today()->addDay()->toDateString(), 'time_slot_id' => $slot->id,
        ]], 'SAVE20', services: [['service_item_id' => $service->id, 'quantity' => 2]]);
    }

    public function test_checkout_displays_authoritative_amounts_services_and_confirmation(): void
    {
        $this->actingAs($this->customer)->get(route('bookings.show', $this->booking))
            ->assertOk()->assertSee('Kiểm tra &amp; thanh toán', false)
            ->assertSee('Chọn sân')->assertSee('Chọn lịch')->assertSee('Dịch vụ')->assertSee('Hoàn tất')
            ->assertSee('150.000đ')->assertSee('20.000đ')->assertSee('170.000đ')->assertSee('30.000đ')->assertSee('140.000đ')
            ->assertSee('Nước uống')->assertSee('60 phút')->assertDontSee('data-confirm-booking', false)
            ->assertDontSee('để bật nút thanh toán.')
            ->assertSee('name="_token"', false)->assertSee(route('bookings.update-note', $this->booking), false);
        $this->assertSame('140000.00', $this->booking->fresh()->total_amount);
    }

    public function test_invalid_note_is_visible_and_cannot_change_backend_total(): void
    {
        $url = route('bookings.show', $this->booking);
        $this->actingAs($this->customer)->from($url)->post(route('bookings.update-note', $this->booking), [
            'note' => str_repeat('a', 1001), 'total_amount' => 1, 'discount' => 999999,
        ])->assertRedirect($url)->assertSessionHasErrors('note');
        $this->get($url)->assertOk()->assertSee('is-invalid', false)->assertSee('note-error', false);
        $this->assertSame('140000.00', $this->booking->fresh()->total_amount);
        $this->post(route('bookings.update-note', $this->booking), ['note' => 'Đến đúng giờ', 'total_amount' => 1])
            ->assertRedirect(route('bookings.vnpay', $this->booking));
        $this->assertSame('140000.00', $this->booking->fresh()->total_amount);
    }

    public function test_confirmation_and_expired_states_follow_backend_and_ownership(): void
    {
        $this->actingAs(User::factory()->create())->get(route('bookings.show', $this->booking))->assertForbidden();
        app(PaymentService::class)->markAsPaid($this->booking->payment);
        $this->actingAs($this->customer)->get(route('bookings.show', $this->booking))
            ->assertOk()->assertSee('Đã xác nhận')->assertSee('Đã thanh toán')
            ->assertSee('Hoàn tất')->assertDontSee('data-payment-submit', false);
    }

    public function test_expired_hold_does_not_offer_payment(): void
    {
        $this->travelTo($this->booking->hold_expires_at);
        $url = route('bookings.show', $this->booking);
        $this->actingAs($this->customer)->get($url)->assertRedirect($url);
        $this->get($url)->assertOk()->assertSee('Đã hết hạn')->assertDontSee('data-payment-submit', false);
        $this->assertSame('EXPIRED', $this->booking->fresh()->status);
    }
}
