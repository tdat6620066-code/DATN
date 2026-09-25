<?php

namespace Tests\Feature;

use App\Models\{Booking, Court, CourtType, ServiceItem, TimeSlot, User};
use App\Services\{BookingOperationsService, ServiceOrderService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingOperationsFlowTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $this->travelTo(today()->setTime(18, 29));
        $staff = User::factory()->create(['role' => 'EMPLOYEE', 'permissions' => ['bookings.view', 'bookings.checkin', 'bookings.checkout', 'payments.counter', 'services.manage']]);
        $type = CourtType::create(['name' => 'Standard']);
        $court = Court::create(['code' => 'OPS', 'name' => 'Sân 2', 'court_type_id' => $type->id, 'status' => 'ACTIVE']);
        $slot = TimeSlot::create(['name' => '19:00–20:00', 'start_time' => '19:00', 'end_time' => '20:00', 'duration' => 60, 'status' => 'ACTIVE']);
        $booking = Booking::create(['booking_code' => 'OPS001', 'user_id' => User::factory()->create()->id, 'status' => 'CONFIRMED', 'payment_status' => 'PAID', 'subtotal' => 100000, 'total_amount' => 100000]);
        $booking->bookingDetails()->create(['court_id' => $court->id, 'time_slot_id' => $slot->id, 'booking_date' => today(), 'price' => 100000, 'subtotal' => 100000, 'status' => 'CONFIRMED']);
        $booking->payment()->create(['amount' => 100000, 'status' => 'PAID']);
        $this->actingAs($staff);
        return [$staff, $booking];
    }

    public function test_checkin_window_duplicate_and_original_schedule(): void
    {
        [$staff, $booking] = $this->fixture();
        $snapshot = $booking->bookingDetails()->first()->only(['booking_date', 'time_slot_id']);
        $this->travelTo(today()->setTime(17, 59, 59));
        $this->post(route('operations.check-in', $booking))->assertSessionHas('error');
        $this->travelTo(today()->setTime(18, 0));
        $this->post(route('operations.check-in', $booking))->assertSessionHas('success');
        $this->assertSame($staff->id, $booking->fresh()->checked_in_by);
        $this->post(route('operations.check-in', $booking))->assertSessionHas('error');
        $this->assertEquals($snapshot, $booking->bookingDetails()->first()->only(['booking_date', 'time_slot_id']));
    }

    public function test_no_show_waits_thirty_minutes_and_keeps_payment(): void
    {
        [, $booking] = $this->fixture();
        $this->travelTo(today()->setTime(19, 29));
        $this->post(route('operations.no-show', $booking))->assertSessionHas('error');
        $this->travelTo(today()->setTime(19, 30));
        $this->post(route('operations.no-show', $booking))->assertSessionHas('success');
        $this->assertSame('NO_SHOW', $booking->fresh()->status);
        $this->assertSame('PAID', $booking->fresh()->payment->status);
        $this->post(route('operations.check-in', $booking))->assertSessionHas('error');
    }

    public function test_checkout_collects_multiple_service_orders_without_changing_court_payment(): void
    {
        [$staff, $booking] = $this->fixture();
        $this->travelTo(today()->setTime(19, 0));
        app(BookingOperationsService::class)->checkIn($booking, $staff);
        $original = $booking->fresh()->payment->getAttributes();
        $water = ServiceItem::create(['code' => 'OPS-WATER', 'name' => 'Nước', 'price' => 10000, 'stock' => 5, 'is_active' => true]);
        foreach ([1, 2] as $quantity) {
            $order = app(ServiceOrderService::class)->create($booking, $staff, [['service_item_id' => $water->id, 'quantity' => $quantity]], (string) str()->uuid());
            app(ServiceOrderService::class)->deliver($order, $staff);
        }
        $this->travel(40)->minutes();
        $this->artisan('bookings:expire-holds')->assertSuccessful();
        $this->assertEquals(30000, app(BookingOperationsService::class)->amountDue($booking));
        $this->post(route('operations.checkout', $booking))->assertSessionHas('error');
        $this->post(route('operations.checkout', $booking), ['amount' => 30000])->assertSessionHas('success');
        $this->assertSame('COMPLETED', $booking->fresh()->status);
        $this->assertSame($original, $booking->fresh()->payment->getAttributes());
        $this->assertSame($staff->id, $booking->fresh()->checked_out_by);
    }

    public function test_admin_exception_cannot_skip_unpaid_delivered_services(): void
    {
        [$staff, $booking] = $this->fixture();
        $this->travelTo(today()->setTime(19, 0));
        app(BookingOperationsService::class)->checkIn($booking, $staff);
        $item = ServiceItem::create(['code' => 'DUE', 'name' => 'Water', 'price' => 10000, 'stock' => 5, 'is_active' => true]);
        $order = app(ServiceOrderService::class)->create($booking, $staff, [['service_item_id' => $item->id, 'quantity' => 1]], (string) str()->uuid());
        app(ServiceOrderService::class)->deliver($order, $staff);
        $admin = User::factory()->create(['role' => 'ADMIN']);
        try {
            app(BookingOperationsService::class)->checkout($booking, $admin, exception: 'Ngoại lệ trả sân có ghi chú');
            $this->fail('Unpaid services must block checkout.');
        } catch (\DomainException $exception) {
            $this->assertStringContainsString('thanh toán', $exception->getMessage());
        }
        $this->assertSame('CHECKED_IN', $booking->fresh()->status);
        $this->assertSame('PENDING', $order->payment->fresh()->status);
    }

    public function test_customer_can_pay_staff_added_services_but_other_customers_cannot(): void
    {
        [$staff, $booking] = $this->fixture();
        $this->travelTo(today()->setTime(19, 0));
        app(BookingOperationsService::class)->checkIn($booking, $staff);
        $item = ServiceItem::create(['code' => 'ONLINE', 'name' => 'Water', 'price' => 10000, 'stock' => 5, 'is_active' => true]);
        $order = app(ServiceOrderService::class)->create($booking, $staff, [['service_item_id' => $item->id, 'quantity' => 2]], (string) str()->uuid());
        $this->actingAs(User::factory()->create(['role' => 'CUSTOMER']))->post(route('service-orders.pay', $order))->assertForbidden();
        $this->mock(\App\Services\VnPayService::class)->shouldReceive('createPaymentUrl')->once()->andReturn('https://sandbox.vnpayment.vn/paymentv2/vpcpay.html');
        $this->actingAs($booking->user)->get(route('bookings.show', $booking))->assertOk()->assertSee('Dịch vụ chưa thanh toán:')->assertSee(route('service-orders.pay', $order), false);
        $this->post(route('service-orders.pay', $order))->assertRedirect('https://sandbox.vnpayment.vn/paymentv2/vpcpay.html');
        $this->assertSame('PENDING', $order->payment->fresh()->status);
    }
}
