<?php

namespace Tests\Feature;

use App\Models\{Booking, ContactThread, CounterSale, Court, CourtType, EmployeeShift, Equipment, EquipmentLoan, ServiceItem, TimeSlot, User};
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class EmployeeOperationsTest extends TestCase
{
    use RefreshDatabase;

    private User $employee;
    private User $customer;
    private Court $court;
    private TimeSlot $slot;
    private TimeSlot $nextSlot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->startOfDay()->addHours(10));
        $this->employee = User::factory()->create(['role' => 'EMPLOYEE', 'permissions' => ['employee.dashboard', 'bookings.view', 'payments.counter', 'bookings.checkin', 'bookings.checkout', 'services.manage', 'courts.status.manage', 'incidents.manage']]);
        $this->customer = User::factory()->create(['role' => 'CUSTOMER']);
        $type = CourtType::create(['name' => 'Standard', 'status' => 'ACTIVE']);
        $this->court = Court::create(['code' => 'C1', 'name' => 'Court One', 'court_type_id' => $type->id, 'status' => 'ACTIVE', 'opening_time' => '06:00', 'closing_time' => '23:00', 'operational_status' => 'AVAILABLE']);
        $this->slot = TimeSlot::create(['name' => '18-19', 'start_time' => '18:00', 'end_time' => '19:00', 'duration' => 60, 'status' => 'ACTIVE']);
        $this->nextSlot = TimeSlot::create(['name' => '19-20', 'start_time' => '19:00', 'end_time' => '20:00', 'duration' => 60, 'status' => 'ACTIVE']);
        foreach ([$this->slot, $this->nextSlot] as $slot) {
            foreach (['WEEKDAY', 'WEEKEND'] as $dayType) $this->court->prices()->create(['time_slot_id' => $slot->id, 'price' => 100000, 'effective_from' => today()->subDay(), 'day_type' => $dayType, 'status' => 'ACTIVE']);
        }
        $this->actingAs($this->employee);
    }

    private function booking(bool $paid = true): Booking
    {
        $booking = app(BookingService::class)->createBooking($this->customer->id, [['court_id' => $this->court->id, 'booking_date' => today()->toDateString(), 'time_slot_id' => $this->slot->id]]);
        if ($paid) {
            $this->post(route('employee.bookings.payment', $booking), ['payment_method' => 'CASH', 'amount' => 100000])->assertSessionHas('success');
            $this->post(route('employee.bookings.check-in', $booking))->assertSessionHas('success');
        }
        return $booking->fresh();
    }

    public function test_employee_pages_render_and_unassigned_employee_is_denied(): void
    {
        foreach (['counter.create', 'retail.index', 'equipment.index', 'shifts.index', 'bookings.index', 'schedule'] as $route) $this->get(route('employee.'.$route))->assertOk();
        $this->get(route('employee.courts.edit', $this->court))->assertOk();
        $this->get(route('contacts.index'))->assertOk();
        $this->employee->update(['permissions' => []]);
        foreach (['counter.create', 'retail.index', 'equipment.index', 'shifts.index'] as $route) $this->get(route('employee.'.$route))->assertForbidden();
        $this->get(route('contacts.index'))->assertForbidden();
    }

    public function test_counter_booking_uses_existing_customer_price_and_prevents_double_booking(): void
    {
        $data = ['phone' => $this->customer->phone, 'name' => 'Counter name', 'court_id' => $this->court->id, 'booking_date' => today()->toDateString(), 'time_slot_ids' => [$this->slot->id]];
        $this->post(route('employee.counter.store'), $data)->assertSessionHas('success');
        $booking = Booking::firstOrFail();
        $this->assertEquals(100000, $booking->total_amount);
        $this->assertSame($this->customer->id, $booking->user_id);
        $this->get(route('employee.bookings.show', $booking))->assertOk();
        $this->post(route('employee.counter.store'), $data)->assertSessionHas('error');
        $this->assertSame(1, Booking::count());
        $this->post(route('employee.bookings.payment', $booking), ['payment_method' => 'CASH', 'amount' => 100000])->assertSessionHas('success');
        $this->assertSame('CONFIRMED', $booking->bookingDetails()->first()->status);
    }

    public function test_extension_reserves_only_next_slot_and_links_to_original(): void
    {
        $booking = $this->booking();
        $payload = ['detail_id' => $booking->bookingDetails()->first()->id, 'time_slot_ids' => [$this->slot->id]];
        $this->post(route('employee.bookings.extend', $booking), $payload)->assertSessionHas('error');
        $payload['time_slot_ids'] = [$this->nextSlot->id];
        $this->post(route('employee.bookings.extend', $booking), $payload)->assertSessionHas('success');
        $next = Booking::where('extension_of_id', $booking->id)->firstOrFail();
        $this->assertEquals(100000, $next->total_amount);
        $this->post(route('employee.bookings.extend', $booking), $payload)->assertSessionHas('error');
        $this->post(route('employee.bookings.payment', $next), ['payment_method' => 'CASH', 'amount' => 100000])->assertSessionHas('success');
        $this->travelTo(today()->setTime(19, 0));
        $this->post(route('employee.bookings.check-in', $next))->assertSessionHas('success');
        $this->post(route('employee.bookings.complete', $booking))->assertSessionHas('success');
        $this->assertSame('OCCUPIED', $this->court->fresh()->availability_status);
    }

    public function test_retail_calculates_price_and_repeated_submission_does_not_charge_twice(): void
    {
        $item = ServiceItem::create(['code' => 'WATER', 'name' => 'Water', 'price' => 20000, 'stock' => 5]);
        $data = ['request_key' => (string) Str::uuid(), 'quantities' => [$item->id => 2], 'payment_method' => 'CASH', 'total' => 1];
        $this->post(route('employee.retail.store'), $data)->assertSessionHas('success');
        $this->post(route('employee.retail.store'), $data)->assertSessionHas('success');
        $sale = CounterSale::firstOrFail();
        $this->assertEquals(40000, $sale->total);
        $this->assertSame(1, CounterSale::count());
        $this->assertSame(3, $item->fresh()->stock);
        $this->get(route('employee.retail.show', $sale))->assertOk()->assertSee('40,000');
        $report = app(\App\Services\RevenueReportService::class)->report(today(), today()->endOfDay());
        $this->assertSame(40000.0, $report['gross_revenue']);
        $this->assertSame(40000.0, $report['gross_daily'][today()->toDateString()]);
        $data['request_key'] = (string) Str::uuid(); $data['quantities'][$item->id] = 4;
        $this->post(route('employee.retail.store'), $data)->assertSessionHas('error');
        $this->assertSame(3, $item->fresh()->stock);
        $this->assertSame(1, CounterSale::count());
    }

    public function test_equipment_cannot_be_lent_twice_and_must_be_returned_before_checkout(): void
    {
        $booking = $this->booking();
        $equipment = Equipment::create(['code' => 'R1', 'name' => 'Racket', 'condition_note' => 'Good']);
        $data = ['equipment_id' => $equipment->id, 'booking_code' => $booking->booking_code, 'issue_note' => 'Good'];
        $this->post(route('employee.equipment.lend'), $data)->assertSessionHas('success');
        $this->post(route('employee.equipment.lend'), $data)->assertSessionHas('error');
        $this->postJson(route('bookings.checkout', $booking))->assertStatus(422);
        $this->post(route('employee.bookings.complete', $booking))->assertSessionHas('error');
        $loan = EquipmentLoan::firstOrFail();
        $this->post(route('employee.equipment.return', $loan), ['return_status' => 'AVAILABLE', 'return_note' => 'Good'])->assertSessionHas('success');
        $this->post(route('employee.equipment.return', $loan), ['return_status' => 'LOST', 'return_note' => 'Lost'])->assertSessionHas('error');
        $this->assertSame('AVAILABLE', $equipment->fresh()->status);
        $this->post(route('employee.bookings.complete', $booking))->assertSessionHas('success');
    }

    public function test_shift_report_tracks_only_employee_transactions_and_exports_csv(): void
    {
        $this->post(route('employee.shifts.start'))->assertSessionHas('success');
        $this->post(route('employee.shifts.start'));
        $this->assertSame(1, EmployeeShift::count());
        $this->travel(1)->minutes();
        $this->booking();
        $shift = EmployeeShift::firstOrFail();
        $this->travel(1)->minutes();
        $this->get(route('employee.shifts.index'))->assertOk()->assertViewHas('report', fn ($report) => $report['Số lượt check-in'] === 1 && (float) $report['Tiền thu booking tại quầy (đ)'] === 100000.0);
        $this->post(route('employee.shifts.close', $shift), ['note' => 'Done'])->assertSessionHas('success');
        $this->get(route('employee.shifts.export', $shift))->assertOk()->assertDownload('ca-'.$shift->id.'.csv');
        $other = User::factory()->create(['role' => 'EMPLOYEE', 'permissions' => ['employee.dashboard']]);
        $this->actingAs($other)->get(route('employee.shifts.export', $shift))->assertForbidden();
    }

    public function test_scan_filters_schedule_and_cleaning_record(): void
    {
        $booking = $this->booking();
        $this->post(route('employee.scan'), ['code' => json_encode(['booking_code' => $booking->booking_code, 'status' => 'FAKE'])])->assertRedirect(route('employee.bookings.show', $booking));
        $this->get(route('employee.schedule', ['court_id' => $this->court->id, 'status' => 'CHECKED_IN']))->assertOk()->assertSee($booking->booking_code);
        $this->get(route('employee.schedule', ['status' => 'COMPLETED']))->assertOk()->assertDontSee($booking->booking_code);
        $this->post(route('employee.courts.clean', $this->court), ['note' => 'Cleaned floor'])->assertSessionHas('success');
        $this->get(route('employee.courts.edit', $this->court))->assertOk()->assertSee('Cleaned floor');
    }

    public function test_support_employee_can_reply_reschedule_and_cancel_unpaid_booking(): void
    {
        $thread = ContactThread::create(['user_id' => $this->customer->id, 'subject' => 'Question']);
        $this->post(route('contacts.reply', $thread), ['body' => 'We can help'])->assertRedirect();
        $this->get(route('contacts.show', $thread))->assertOk()->assertSee('We can help');
        $booking = $this->booking(false);
        $detail = $booking->bookingDetails()->first();
        $this->put(route('employee.bookings.reschedule', [$booking, $detail]), ['booking_date' => today()->toDateString(), 'time_slot_id' => $this->nextSlot->id, 'reason' => 'Customer request'])->assertSessionHas('success');
        $this->assertSame($this->nextSlot->id, $detail->fresh()->time_slot_id);
        $this->put(route('employee.bookings.cancel', $booking), ['reason' => 'Customer request'])->assertSessionHas('success');
        $this->assertSame('CANCELLED', $booking->fresh()->status);
    }

    public function test_new_counter_customer_is_created_but_invalid_booking_rolls_back_customer(): void
    {
        $data = ['name' => 'New customer', 'phone' => '0901234567', 'email' => 'counter-new@example.com', 'court_id' => $this->court->id, 'booking_date' => today()->toDateString(), 'time_slot_ids' => [$this->slot->id]];
        $this->post(route('employee.counter.store'), $data)->assertSessionHas('success');
        $this->assertDatabaseHas('users', ['phone' => '0901234567', 'role' => 'CUSTOMER']);
        $data['phone'] = '0901234568'; $data['email'] = 'counter-other@example.com';
        $this->post(route('employee.counter.store'), $data)->assertSessionHas('error');
        $this->assertDatabaseMissing('users', ['email' => 'counter-other@example.com']);
    }

    public function test_older_unpaid_booking_cannot_take_counter_hold_when_settling(): void
    {
        $older = $this->booking(false);
        $data = ['phone' => $this->customer->phone, 'name' => 'Customer', 'court_id' => $this->court->id, 'booking_date' => today()->toDateString(), 'time_slot_ids' => [$this->slot->id]];
        $this->post(route('employee.counter.store'), $data)->assertSessionHas('success');
        $this->post(route('employee.bookings.payment', $older), ['payment_method' => 'CASH', 'amount' => 100000])->assertSessionHas('error');
        $this->assertSame('PENDING', $older->fresh()->payment_status);
        $this->expectException(\DomainException::class);
        app(\App\Services\PaymentService::class)->markAsPaid($older->payment, 'OLD-TXN', 'vnpay');
    }

    public function test_lost_equipment_notifies_admin_and_cannot_be_lent_until_restored(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $booking = $this->booking();
        $item = Equipment::create(['code' => 'R2', 'name' => 'Racket']);
        $data = ['equipment_id' => $item->id, 'booking_code' => $booking->booking_code, 'issue_note' => 'Good'];
        $this->post(route('employee.equipment.lend'), $data)->assertSessionHas('success');
        $this->post(route('employee.equipment.return', EquipmentLoan::firstOrFail()), ['return_status' => 'LOST', 'return_note' => 'Missing'])->assertSessionHas('success');
        $this->assertDatabaseHas('user_notifications', ['user_id' => $admin->id, 'content' => 'Missing']);
        $this->post(route('employee.equipment.lend'), $data)->assertSessionHas('error');
        $this->post(route('employee.equipment.restore', $item), ['condition_note' => 'Found and checked'])->assertSessionHas('success');
        $this->assertSame('AVAILABLE', $item->fresh()->status);
    }

    public function test_shift_separates_service_collections_and_retail_sales(): void
    {
        $this->post(route('employee.shifts.start'));
        $this->travel(1)->minutes();
        $booking = $this->booking();
        $item = ServiceItem::create(['code' => 'WATER', 'name' => 'Water', 'price' => 20000, 'stock' => 5]);
        $this->post(route('employee.bookings.services.store', $booking), ['service_item_id' => $item->id, 'quantity' => 1])->assertSessionHas('success');
        $this->post(route('employee.bookings.payment', $booking), ['payment_method' => 'CASH', 'amount' => 20000])->assertSessionHas('success');
        $this->post(route('employee.retail.store'), ['request_key' => (string) Str::uuid(), 'quantities' => [$item->id => 2], 'payment_method' => 'CASH'])->assertSessionHas('success');
        $this->travel(1)->minutes();
        $this->get(route('employee.shifts.index'))->assertOk()->assertViewHas('report', fn ($report) => (float) $report['Tiền thu booking tại quầy (đ)'] === 120000.0 && (float) $report['Trong đó tiền dịch vụ phát sinh (đ)'] === 20000.0 && (float) $report['Tiền bán lẻ (đ)'] === 40000.0);
    }
}
