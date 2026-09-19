<?php

namespace Tests\Feature;

use App\Models\{Booking, Court, CourtType, Payment, TimeSlot, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingExtensionTest extends TestCase
{
    use RefreshDatabase;

    private User $employee;
    private Booking $booking;
    private Court $court;
    private TimeSlot $next;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(today()->setTime(18, 55));
        $this->employee = User::factory()->create(['role' => 'EMPLOYEE', 'permissions' => ['bookings.view', 'payments.counter', 'bookings.checkin', 'bookings.checkout']]);
        $customer = User::factory()->create(['role' => 'CUSTOMER']);
        $type = CourtType::create(['name' => 'Standard', 'status' => 'ACTIVE']);
        $this->court = $this->court('C3', $type->id, 120000);
        $slot = TimeSlot::create(['name' => '18:00–19:00', 'start_time' => '18:00', 'end_time' => '19:00', 'duration' => 60, 'status' => 'ACTIVE']);
        $this->next = TimeSlot::create(['name' => '19:00–20:00', 'start_time' => '19:00', 'end_time' => '20:00', 'duration' => 60, 'status' => 'ACTIVE']);
        $this->price($this->court, 120000);
        $this->booking = Booking::create(['booking_code' => 'BK001', 'user_id' => $customer->id, 'status' => 'CHECKED_IN', 'payment_status' => 'PAID', 'subtotal' => 100000, 'total_amount' => 100000, 'checked_in_at' => today()->setTime(18, 0), 'checked_in_by' => $this->employee->id]);
        $this->booking->bookingDetails()->create(['court_id' => $this->court->id, 'booking_date' => today(), 'time_slot_id' => $slot->id, 'price' => 100000, 'subtotal' => 100000, 'status' => 'CHECKED_IN']);
        $this->booking->payment()->create(['amount' => 100000, 'status' => 'PAID', 'paid_at' => today()->setTime(18, 0), 'payment_method' => 'CASH', 'transaction_id' => 'ORIGINAL']);
        $this->actingAs($this->employee);
    }

    private function court(string $code, int $type, float $price): Court
    {
        return Court::create(['code' => $code, 'name' => 'Sân '.$code, 'court_type_id' => $type, 'status' => 'ACTIVE', 'opening_time' => '06:00', 'closing_time' => '23:00', 'operational_status' => 'AVAILABLE', 'availability_status' => 'OCCUPIED']);
    }

    private function price(Court $court, float $price): void
    {
        foreach (['WEEKDAY','WEEKEND'] as $day) $court->prices()->create(['time_slot_id' => $this->next->id, 'price' => $price, 'day_type' => $day, 'effective_from' => today()->subDay(), 'status' => 'ACTIVE']);
    }

    private function data(?Court $court = null): array
    {
        return ['detail_id' => $this->booking->bookingDetails()->first()->id, 'time_slot_ids' => [$this->next->id], 'court_id' => ($court ?? $this->court)->id];
    }

    private function extension(): Booking
    {
        $this->post(route('employee.bookings.extend', $this->booking), $this->data())->assertSessionHas('success');
        return Booking::where('extension_of_id', $this->booking->id)->firstOrFail();
    }

    private function occupyNext(): void
    {
        $other = Booking::create(['booking_code' => 'OTHER', 'user_id' => User::factory()->create()->id, 'status' => 'CONFIRMED', 'payment_status' => 'PAID']);
        $other->bookingDetails()->create(['court_id' => $this->court->id, 'booking_date' => today(), 'time_slot_id' => $this->next->id, 'price' => 120000, 'subtotal' => 120000, 'status' => 'CONFIRMED']);
    }

    public function test_preview_shows_next_slot_price_and_alternative_when_current_court_is_booked(): void
    {
        $alternate = $this->court('C2', $this->court->court_type_id, 150000); $this->price($alternate, 150000);
        $this->occupyNext();
        $this->get(route('employee.bookings.extension-options', $this->booking).'?detail_id='.$this->data()['detail_id'])
            ->assertOk()->assertSee('19:00–20:00')->assertSee('150,000')->assertSee('Không thể gia hạn trên sân này')
            ->assertSee('Sân nào còn trống để chơi tiếp?')->assertSee('Không khả dụng')->assertSee('Còn trống')
            ->assertViewHas('timeline', fn ($timeline) => $timeline->count() === 1
                && !$timeline->first()['courts'][$this->court->id]['available']
                && $timeline->first()['courts'][$alternate->id]['available'])
            ->assertViewHas('options', fn ($options) => !$options->firstWhere('court.id', $this->court->id)['available'] && $options->firstWhere('court.id', $alternate->id)['available']);
        $this->post(route('employee.bookings.extend', $this->booking), $this->data())->assertSessionHas('error');
        $this->post(route('employee.bookings.extend', $this->booking), $this->data($alternate))->assertSessionHas('success');
        $child = Booking::where('extension_of_id', $this->booking->id)->firstOrFail();
        $this->assertEquals(150000, $child->total_amount);
        $this->assertSame($alternate->id, $child->bookingDetails()->first()->court_id);
        $this->post(route('employee.bookings.payment', $child), ['payment_method'=>'CASH', 'amount'=>150000])->assertSessionHas('success');
        $this->travelTo(today()->setTime(19, 0));
        $this->post(route('employee.bookings.check-in', $child))->assertSessionHas('success');
        $this->assertSame('AVAILABLE', $this->court->fresh()->availability_status);
        $this->assertSame('OCCUPIED', $alternate->fresh()->availability_status);
    }

    public function test_payment_and_original_schedule_are_preserved_and_early_checkin_is_rejected(): void
    {
        $originalPayment = $this->booking->payment->toArray();
        $originalDetail = $this->booking->bookingDetails()->first()->toArray();
        $child = $this->extension();
        $this->assertSame(2, Payment::count());
        $this->post(route('employee.bookings.check-in', $child))->assertSessionHas('error');
        $this->post(route('employee.bookings.payment', $child), ['payment_method' => 'CASH', 'amount' => 120000])->assertSessionHas('success');
        $this->post(route('employee.bookings.check-in', $child))->assertSessionHas('error');
        $this->assertSame($originalPayment, $this->booking->fresh()->payment->toArray());
        $this->assertSame($originalDetail, $this->booking->bookingDetails()->first()->toArray());
        $this->travelTo(today()->setTime(19, 0));
        $this->post(route('employee.bookings.check-in', $child))->assertSessionHas('success');
        $this->get(route('employee.bookings.show', $child))->assertOk()->assertSee('Phiên chơi liên tục')->assertSee('BK001');
    }

    public function test_extension_can_start_in_boundary_minute_but_normal_booking_cannot(): void
    {
        $this->travelTo(today()->setTime(19, 0, 30));
        $this->extension();
        $this->expectException(\Exception::class);
        app(\App\Services\BookingService::class)->createBooking($this->booking->user_id, [['court_id'=>$this->court->id, 'booking_date'=>today()->toDateString(), 'time_slot_id'=>$this->next->id]]);
    }

    public function test_late_extension_and_tampered_price_are_rejected(): void
    {
        $this->post(route('employee.bookings.extend', $this->booking), $this->data() + ['quoted_price'=>1])->assertSessionHas('error');
        $this->travelTo(today()->setTime(19, 1));
        $this->post(route('employee.bookings.extend', $this->booking), $this->data())->assertSessionHas('error');
        $this->assertSame(1, Booking::count());
    }

    public function test_duplicate_extension_on_another_court_is_rejected(): void
    {
        $this->extension();
        $alternate = $this->court('C2', $this->court->court_type_id, 150000); $this->price($alternate, 150000);
        $this->post(route('employee.bookings.extend', $this->booking), $this->data($alternate))->assertSessionHas('error');
        $this->assertSame(1, Booking::where('extension_of_id', $this->booking->id)->count());
    }

    public function test_session_checkout_is_atomic_when_original_has_unpaid_services(): void
    {
        $child = $this->extension();
        $this->post(route('employee.bookings.payment', $child), ['payment_method'=>'CASH', 'amount'=>120000])->assertSessionHas('success');
        $this->travelTo(today()->setTime(19, 0));
        $this->post(route('employee.bookings.check-in', $child))->assertSessionHas('success');
        $this->booking->update(['payment_status'=>'PENDING']);
        $this->post(route('employee.bookings.session-checkout', $child))->assertSessionHas('error');
        $this->assertSame('CHECKED_IN', $child->fresh()->status);
        $this->booking->update(['payment_status'=>'PAID']);
        $this->travelTo(today()->setTime(20, 0));
        $this->post(route('employee.bookings.session-checkout', $child))->assertSessionHas('success');
        $this->assertSame('COMPLETED', $child->fresh()->status);
        $this->assertSame('COMPLETED', $this->booking->fresh()->status);
        $this->assertSame('AVAILABLE', $this->court->fresh()->availability_status);
    }

    public function test_new_endpoints_require_employee_permissions(): void
    {
        $this->employee->forceFill(['permissions'=>['bookings.view']])->save();
        $this->get(route('employee.bookings.extension-options', $this->booking).'?detail_id='.$this->data()['detail_id'])->assertForbidden();
        $this->post(route('employee.bookings.session-checkout', $this->booking))->assertForbidden();
    }

    public function test_pending_extension_holds_the_court_until_expiry(): void
    {
        $child = $this->extension();
        $availability = app(\App\Services\CourtAvailabilityService::class);
        $this->assertSame('HOLD', $availability->checkAvailability($this->court->id, today(), $this->next->id));
        $child->update(['hold_expires_at' => now()->subSecond()]);
        $this->assertSame('AVAILABLE', $availability->checkAvailability($this->court->id, today(), $this->next->id));
        $this->post(route('employee.bookings.payment', $child), ['payment_method' => 'CASH', 'amount' => 120000])->assertSessionHas('error');
        $this->assertSame('PENDING', $child->fresh()->payment->status);
    }

    public function test_extension_rejects_a_slot_that_is_not_immediately_next(): void
    {
        $later = TimeSlot::create(['name' => '21:00–22:00', 'start_time' => '21:00', 'end_time' => '22:00', 'duration' => 60, 'status' => 'ACTIVE']);
        $data = $this->data();
        $data['time_slot_ids'] = [$later->id];
        $this->post(route('employee.bookings.extend', $this->booking), $data)->assertSessionHas('error');
        $this->assertSame(1, Booking::count());
    }
}
