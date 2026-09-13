<?php
namespace Tests\Feature;

use App\Models\{Booking, Court, CourtType, Payment, ServiceItem, ServiceOrder, TimeSlot, User, FixedBooking};
use App\Services\{ServiceOrderService, RevenueReportService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceOrderTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(string $status = 'CHECKED_IN'): array
    {
        $customer = User::factory()->create(['role' => 'CUSTOMER']);
        $staff = User::factory()->create(['role' => 'EMPLOYEE', 'permissions' => ['bookings.view', 'services.manage', 'payments.counter', 'bookings.checkout']]);
        $type = CourtType::create(['name' => 'Standard']);
        $court = Court::create(['code' => 'SVC-'.str()->random(8), 'name' => 'Sân 2', 'court_type_id' => $type->id, 'status' => 'ACTIVE']);
        $slot = TimeSlot::create(['name' => '19:00 - 20:00', 'start_time' => '19:00', 'end_time' => '20:00', 'duration' => 60, 'status' => 'ACTIVE']);
        $booking = Booking::create(['booking_code' => 'SVC-'.str()->random(8), 'user_id' => $customer->id, 'status' => $status, 'payment_status' => 'PAID', 'subtotal' => 150000, 'total_amount' => 150000]);
        $booking->bookingDetails()->create(['court_id' => $court->id, 'time_slot_id' => $slot->id, 'booking_date' => today()->addDay(), 'price' => 150000, 'subtotal' => 150000, 'status' => 'CONFIRMED']);
        $payment = Payment::create(['booking_id' => $booking->id, 'amount' => 150000, 'status' => 'PAID', 'paid_at' => now()]);
        $water = ServiceItem::create(['code' => 'WATER', 'name' => 'Nước uống', 'price' => 10000, 'stock' => 2, 'is_active' => true]);
        $shuttle = ServiceItem::create(['code' => 'SHUTTLE', 'name' => 'Cầu', 'price' => 30000, 'stock' => 1, 'is_active' => true]);
        return [$customer, $staff, $booking, $payment, $water, $shuttle];
    }

    private function payload(ServiceItem $water, ServiceItem $shuttle): array
    {
        return ['request_key' => (string) str()->uuid(), 'items' => [['service_item_id' => $water->id, 'quantity' => 2], ['service_item_id' => $shuttle->id, 'quantity' => 1]]];
    }

    public function test_cash_addition_never_changes_original_receipt_and_delivery_requires_payment(): void
    {
        [$customer, $staff, $booking, $original, $water, $shuttle] = $this->fixture();
        $snapshot = $original->fresh()->getAttributes();
        $data = $this->payload($water, $shuttle);
        $this->actingAs($staff)->post(route('service-orders.store', $booking), $data)->assertSessionHasNoErrors();
        $this->post(route('service-orders.store', $booking), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('service_orders', 1);
        $this->assertDatabaseCount('payments', 2);
        $order = ServiceOrder::firstOrFail();
        $this->assertSame('at_court', $order->source);
        $this->assertSame($staff->id, $order->added_by);
        $this->assertEquals(50000, $order->payment->amount);
        $this->assertEquals(0, $water->fresh()->stock);
        $this->assertEquals($snapshot, $original->fresh()->getAttributes());
        $this->assertEquals(150000, $booking->fresh()->total_amount);
        $this->assertSame('PAID', $booking->fresh()->payment_status);
        $this->post(route('service-orders.deliver', $order))->assertSessionHas('error');
        $this->post(route('employee.bookings.complete', $booking))->assertSessionHas('error');
        $this->post(route('service-orders.cash', $order), ['amount' => 150000])->assertSessionHasErrors('amount');
        $this->post(route('service-orders.cash', $order), ['amount' => 50000])->assertSessionHasNoErrors();
        $this->post(route('service-orders.cash', $order), ['amount' => 50000])->assertSessionHasNoErrors();
        $this->assertSame('CHECKED_IN', $booking->fresh()->status);
        $this->assertEquals(200000, app(RevenueReportService::class)->report()['gross_revenue']);
        $this->post(route('service-orders.cancel', $order))->assertSessionHas('error');
        $this->post(route('service-orders.deliver', $order))->assertSessionHasNoErrors();
        $this->post(route('employee.bookings.complete', $booking))->assertSessionHasNoErrors();
        $this->post(route('service-orders.store', $booking), $this->payload($water, $shuttle))->assertSessionHas('error');
        $this->assertEquals($snapshot, $original->fresh()->getAttributes());
        $this->actingAs($customer)->get(route('bookings.show', $booking))->assertOk()->assertSee('Đã giao');
    }

    public function test_stock_and_staff_permissions_are_checked_atomically(): void
    {
        [$customer, $staff, $booking, , $water, $shuttle] = $this->fixture();
        $data = $this->payload($water, $shuttle);
        $this->actingAs($customer)->post(route('service-orders.store', $booking), $data)->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'EMPLOYEE', 'permissions' => []]))->post(route('service-orders.store', $booking), $data)->assertForbidden();
        $data['items'][1]['quantity'] = 2;
        $this->actingAs($staff)->post(route('service-orders.store', $booking), $data)->assertSessionHas('error');
        $this->assertDatabaseCount('service_orders', 0);
        $this->assertDatabaseCount('payments', 1);
        $this->assertSame(2, $water->fresh()->stock);
        $this->assertSame(1, $shuttle->fresh()->stock);
    }

    public function test_customer_cannot_create_separate_services_or_collect_at_court_payment(): void
    {
        [$customer, $staff, $booking, , $water, $shuttle] = $this->fixture();
        $this->actingAs($customer)->post(route('service-orders.store', $booking), $this->payload($water, $shuttle))->assertForbidden();
        $this->assertDatabaseCount('service_orders', 0);
        $this->actingAs($staff)->post(route('service-orders.store', $booking), $this->payload($water, $shuttle))->assertSessionHasNoErrors();
        $order = ServiceOrder::firstOrFail();
        $this->actingAs($customer)->post(route('service-orders.pay', $order))->assertForbidden();
        $this->post(route('service-orders.cash', $order), ['amount' => 50000])->assertForbidden();
        $this->post(route('service-orders.deliver', $order))->assertForbidden();
        $this->actingAs($staff)->post(route('service-orders.cancel', $order))->assertSessionHasNoErrors();
        $this->post(route('service-orders.cancel', $order))->assertSessionHasNoErrors();
        $this->assertSame(2, $water->fresh()->stock);
    }

    public function test_services_of_fixed_booking_have_separate_receipt_and_independent_total(): void
    {
        [, $staff, $booking, $original, $water, $shuttle] = $this->fixture();
        $group = FixedBooking::create(['code' => 'FIX-SVC', 'user_id' => $booking->user_id, 'confirmation_key' => (string) str()->uuid(), 'definition' => [], 'occurrences' => [], 'status' => 'ACTIVE', 'total_price' => 900000]);
        $booking->update(['fixed_booking_id' => $group->id]);
        $original->update(['booking_id' => null, 'fixed_booking_id' => $group->id, 'amount' => 900000]);
        $this->actingAs($staff)->post(route('service-orders.store', $booking), $this->payload($water, $shuttle))->assertSessionHasNoErrors();
        $order = ServiceOrder::firstOrFail();
        $this->post(route('service-orders.cash', $order), ['amount' => 50000])->assertSessionHasNoErrors();
        $this->assertEquals(900000, $original->fresh()->amount);
        $this->assertEquals($original->id, $booking->fresh()->payment->id);
        $this->assertEquals(950000, app(RevenueReportService::class)->report()['gross_revenue']);
        $this->assertEquals(200000, app(RevenueReportService::class)->report(null, null, collect([$booking->id]))['gross_revenue']);
    }

    public function test_services_cannot_be_paid_before_court_payment(): void
    {
        [$customer, $staff, $booking, $payment, $water, $shuttle] = $this->fixture('CHECKED_IN');
        $booking->update(['payment_status' => 'PENDING', 'hold_expires_at' => now()->addMinutes(15)]);
        $payment->update(['status' => 'PENDING', 'paid_at' => null]);
        $this->actingAs($staff)->post(route('service-orders.store', $booking), $this->payload($water, $shuttle))->assertSessionHasNoErrors();
        $order = ServiceOrder::firstOrFail();
        $this->post(route('service-orders.pay', $order))->assertSessionHas('error');
        $this->assertFalse(app(ServiceOrderService::class)->settle($order->payment, true, 'unpaid-court', 'CASH'));
        $this->assertSame('PENDING', $order->payment->fresh()->status);
        $booking->update(['status' => 'EXPIRED']);
        $this->assertSame('CANCELLED', $order->fresh()->status);
        $this->assertEquals(2, $water->fresh()->stock);
    }

    private function callbackData(ServiceOrder $order, array $overrides = []): array
    {
        config(['vnpay.tmn_code' => 'TEST1234', 'vnpay.hash_secret' => 'test-secret']);
        $data = array_replace(['vnp_TmnCode' => 'TEST1234', 'vnp_TxnRef' => 'SVC'.$order->id, 'vnp_Amount' => '5000000', 'vnp_ResponseCode' => '00', 'vnp_TransactionStatus' => '00', 'vnp_TransactionNo' => 'SVC-TRANSACTION'], $overrides);
        ksort($data); $data['vnp_SecureHash'] = hash_hmac('sha512', http_build_query($data), 'test-secret');
        return $data;
    }

    public function test_gateway_verification_duplicates_and_late_callback_do_not_change_court_payment(): void
    {
        [, $staff, $booking, $original, $water, $shuttle] = $this->fixture();
        $this->actingAs($staff)->post(route('service-orders.store', $booking), $this->payload($water, $shuttle));
        $order = ServiceOrder::firstOrFail();
        $this->get(route('bookings.vnpay.ipn', $this->callbackData($order, ['vnp_Amount' => '1'])))->assertJsonPath('RspCode', '04');
        $invalid = $this->callbackData($order); $invalid['vnp_SecureHash'] = 'fake';
        $this->get(route('bookings.vnpay.ipn', $invalid))->assertJsonPath('RspCode', '97');
        $this->get(route('bookings.vnpay.ipn', $this->callbackData($order)))->assertJsonPath('RspCode', '00');
        $this->get(route('bookings.vnpay.ipn', $this->callbackData($order)))->assertJsonPath('RspCode', '00');
        $this->assertSame('PAID', $order->fresh()->status);
        $this->assertSame('CHECKED_IN', $booking->fresh()->status);
        $this->assertEquals(150000, $original->fresh()->amount);
        $this->assertSame(0, $water->fresh()->stock);
    }

    public function test_expiring_unpaid_services_restores_inventory_and_late_success_requires_review(): void
    {
        [, $staff, $booking, , $water, $shuttle] = $this->fixture();
        $this->actingAs($staff)->post(route('service-orders.store', $booking), $this->payload($water, $shuttle));
        $order = ServiceOrder::firstOrFail();
        $this->travel(16)->minutes();
        $this->artisan('bookings:expire-holds')->assertSuccessful();
        $this->assertSame(2, $water->fresh()->stock);
        $this->get(route('bookings.vnpay.ipn', $this->callbackData($order)))->assertJsonPath('RspCode', '02');
        $this->assertDatabaseHas('payment_transaction_logs', ['payment_id' => $order->payment_id, 'action' => 'VNPAY_REQUIRES_REVIEW']);
        $this->assertSame('CHECKED_IN', $booking->fresh()->status);
    }
}
