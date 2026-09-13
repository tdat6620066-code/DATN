<?php

namespace Tests\Feature;

use App\Models\{Booking, Court, CourtType, TimeSlot, User};
use App\Services\{BookingService, CourtAvailabilityService, PaymentService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingHoldTest extends TestCase
{
    use RefreshDatabase;

    private Court $court;
    private TimeSlot $slot;
    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->startOfSecond());
        $this->customer = User::factory()->create();
        $type = CourtType::create(['name' => 'Standard', 'status' => 'ACTIVE']);
        $this->court = Court::create(['code' => 'HOLD-1', 'name' => 'Sân 1', 'court_type_id' => $type->id, 'status' => 'ACTIVE', 'operational_status' => 'AVAILABLE']);
        $this->slot = TimeSlot::create(['name' => '19:00 - 20:00', 'start_time' => '19:00', 'end_time' => '20:00', 'duration' => 60, 'status' => 'ACTIVE']);
        $this->court->prices()->create(['time_slot_id' => $this->slot->id, 'price' => 150000, 'effective_from' => today(), 'status' => 'ACTIVE']);
    }

    private function payload(): array
    {
        return ['court_id' => $this->court->id, 'booking_date' => today()->addDay()->toDateString(), 'time_slot_ids' => [$this->slot->id]];
    }

    private function hold(): Booking
    {
        return app(BookingService::class)->createBooking($this->customer->id, [[
            'court_id' => $this->court->id, 'booking_date' => $this->payload()['booking_date'], 'time_slot_id' => $this->slot->id,
        ]]);
    }

    private function availability(): string
    {
        return app(CourtAvailabilityService::class)->checkAvailability($this->court->id, today()->addDay(), $this->slot->id);
    }

    public function test_daily_hold_lasts_five_minutes_and_blocks_another_customers_request(): void
    {
        $booking = $this->hold();
        $this->assertTrue($booking->hold_expires_at->equalTo(now()->addMinutes(5)));
        $this->assertSame('HOLD', $this->availability());
        $this->actingAs(User::factory()->create())->getJson(route('courts.availability', [
            'court' => $this->court->id, 'court_id' => $this->court->id, 'booking_date' => $this->payload()['booking_date'],
        ]))->assertOk()->assertJsonPath('time_slots.0.status', 'HOLD');
        $url = route('courts.show', $this->court).'?booking_date='.$this->payload()['booking_date'];
        $this->actingAs(User::factory()->create())->get($url)->assertOk()
            ->assertSee('⏳ Đang được giữ chỗ')->assertSee('class="cell held"', false)
            ->assertSee('disabled>⏳ Đang được giữ chỗ', false);
        $this->from($url)->post(route('bookings.store'), $this->payload())
            ->assertRedirect($url)->assertSessionHas('booking_errors');
        $this->assertDatabaseCount('bookings', 1);
        $this->get($url)->assertSee('Khung giờ này đang được giữ');
    }

    public function test_hold_expires_at_exact_deadline_without_waiting_for_scheduler(): void
    {
        $booking = $this->hold();
        $this->travelTo($booking->hold_expires_at->copy()->subSecond());
        $this->assertSame('HOLD', $this->availability());
        $this->travelTo($booking->hold_expires_at);
        $this->assertTrue($booking->isHoldExpired());
        $this->assertSame('AVAILABLE', $this->availability());
        $this->actingAs(User::factory()->create())->post(route('bookings.store'), $this->payload())
            ->assertSessionMissing('booking_errors')->assertSessionMissing('error');
        $this->assertDatabaseCount('bookings', 2);
        $this->artisan('bookings:expire-holds')->assertSuccessful();
        $this->assertSame('EXPIRED', $booking->fresh()->status);
        $this->assertSame('HOLD', $this->availability());
    }

    public function test_payment_before_deadline_keeps_slot_booked_after_deadline(): void
    {
        $booking = $this->hold();
        $deadline = $booking->hold_expires_at->copy();
        app(PaymentService::class)->markAsPaid($booking->payment);
        $this->travelTo($deadline->addMinute());
        $this->artisan('bookings:expire-holds')->assertSuccessful();
        $this->assertSame('CONFIRMED', $booking->fresh()->status);
        $this->assertSame('BOOKED', $this->availability());
        $this->actingAs(User::factory()->create())->post(route('bookings.store'), $this->payload())
            ->assertSessionHas('booking_errors');
        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_late_payment_cannot_take_slot_from_new_holder(): void
    {
        $booking = $this->hold();
        $this->travelTo($booking->hold_expires_at);
        $replacement = $this->hold();
        try {
            app(PaymentService::class)->markAsPaid($booking->payment);
            $this->fail('Late payment must be rejected.');
        } catch (\DomainException $exception) {
            $this->assertStringContainsString('giữ chỗ đã hết', $exception->getMessage());
        }
        $this->assertSame('PENDING', $booking->payment->fresh()->status);
        $this->assertSame('PENDING_PAYMENT', $replacement->fresh()->status);
    }

    public function test_overlapping_slot_is_held_but_adjacent_slot_is_available(): void
    {
        $this->hold();
        $overlap = TimeSlot::create(['name' => 'Overlap', 'start_time' => '19:30', 'end_time' => '20:30', 'duration' => 60, 'status' => 'ACTIVE']);
        $adjacent = TimeSlot::create(['name' => 'Adjacent', 'start_time' => '20:00', 'end_time' => '21:00', 'duration' => 60, 'status' => 'ACTIVE']);
        $service = app(CourtAvailabilityService::class);
        $this->assertSame('HOLD', $service->checkAvailability($this->court->id, today()->addDay(), $overlap->id));
        $this->assertSame('AVAILABLE', $service->checkAvailability($this->court->id, today()->addDay(), $adjacent->id));
    }

    public function test_staff_cannot_confirm_an_expired_hold_before_scheduler_runs(): void
    {
        $booking = $this->hold();
        $this->travelTo($booking->hold_expires_at);
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $this->actingAs($admin)->put(route('admin.bookings.update', $booking), [
            'status' => 'CONFIRMED', 'reason' => 'Confirm booking',
        ])->assertSessionHas('error');
        $employee = User::factory()->create(['role' => 'EMPLOYEE', 'permissions' => ['payments.counter']]);
        $this->actingAs($employee)->post(route('employee.bookings.payment', $booking), [
            'payment_method' => 'CASH', 'amount' => 150000, 'transaction_id' => 'TEST-HOLD',
        ])->assertSessionHas('error');
        $this->assertSame('PENDING_PAYMENT', $booking->fresh()->status);
        $this->assertSame('PENDING', $booking->payment->fresh()->status);
    }

    public function test_gateway_deadline_does_not_extend_hold_when_payment_starts_later(): void
    {
        config(['vnpay.tmn_code' => 'TEST1234', 'vnpay.hash_secret' => 'test-secret']);
        $booking = $this->hold();
        $this->travel(2)->minutes();
        $url = app(\App\Services\VnPayService::class)->createPaymentUrl($booking, route('bookings.vnpay.return'));
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $this->assertSame($booking->hold_expires_at->format('YmdHis'), $query['vnp_ExpireDate']);
    }

    private function callbackData(Booking $booking, array $overrides = []): array
    {
        config(['vnpay.tmn_code' => 'TEST1234', 'vnpay.hash_secret' => 'test-secret']);
        $data = array_replace(['vnp_TmnCode' => 'TEST1234', 'vnp_TxnRef' => $booking->id.now()->format('YmdHis'),
            'vnp_Amount' => '15000000', 'vnp_ResponseCode' => '24', 'vnp_TransactionStatus' => '02', 'vnp_TransactionNo' => '123456'], $overrides);
        ksort($data);
        $data['vnp_SecureHash'] = hash_hmac('sha512', http_build_query($data, '', '&', PHP_QUERY_RFC1738), 'test-secret');
        return $data;
    }

    public function test_failed_payment_callback_releases_hold_and_duplicate_cannot_revive_it(): void
    {
        $booking = $this->hold();
        $data = $this->callbackData($booking);
        $this->get(route('bookings.vnpay.ipn', $data))->assertJsonPath('RspCode', '00');
        $this->assertSame('EXPIRED', $booking->fresh()->status);
        $this->assertSame('FAILED', $booking->payment->fresh()->status);
        $this->assertSame('AVAILABLE', $this->availability());
        $replacement = $this->hold();
        $this->get(route('bookings.vnpay.ipn', $data))->assertJsonPath('RspCode', '00');
        $this->get(route('bookings.vnpay.ipn', $this->callbackData($booking, ['vnp_ResponseCode' => '00', 'vnp_TransactionStatus' => '00'])))
            ->assertJsonPath('RspCode', '02');
        $this->assertSame('PENDING_PAYMENT', $replacement->fresh()->status);
        $this->assertSame('EXPIRED', $booking->fresh()->status);
    }

    public function test_failure_after_success_and_invalid_receipts_do_not_release_slots(): void
    {
        $booking = $this->hold();
        $this->get(route('bookings.vnpay.ipn', $this->callbackData($booking, ['vnp_Amount' => '1'])))
            ->assertJsonPath('RspCode', '04');
        $this->assertSame('HOLD', $this->availability());
        app(PaymentService::class)->markAsPaid($booking->payment);
        $this->get(route('bookings.vnpay.ipn', $this->callbackData($booking)))->assertJsonPath('RspCode', '00');
        $this->assertSame('CONFIRMED', $booking->fresh()->status);
        $this->assertSame('BOOKED', $this->availability());
    }
}
