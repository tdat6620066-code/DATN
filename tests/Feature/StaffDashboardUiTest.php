<?php

namespace Tests\Feature;

use App\Models\{Court, CourtType, TimeSlot, User, EquipmentLoan, Equipment};
use App\Services\{BookingService, PaymentService, BookingOperationsService, StaffBookingUiService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffDashboardUiTest extends TestCase
{
    use RefreshDatabase;
    protected User $employee;
    protected Court $court;
    protected TimeSlot $slot;
    protected TimeSlot $next;
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setTime(17, 0));
        $this->employee = User::factory()->create(['role'=>'EMPLOYEE','permissions'=>['employee.dashboard','bookings.view','bookings.checkin','bookings.checkout','payments.counter','services.manage','incidents.manage']]);
        $type = CourtType::create(['name'=>'Standard','status'=>'ACTIVE']);
        $this->court = Court::create(['code'=>'STAFF','name'=>'Sân Staff','court_type_id'=>$type->id,'status'=>'ACTIVE','operational_status'=>'AVAILABLE']);
        $this->slot = TimeSlot::create(['name'=>'18-19','start_time'=>'18:00','end_time'=>'19:00','duration'=>60,'status'=>'ACTIVE']);
        $this->next = TimeSlot::create(['name'=>'19-20','start_time'=>'19:00','end_time'=>'20:00','duration'=>60,'status'=>'ACTIVE']);
        foreach ([$this->slot,$this->next] as $slot) foreach (['WEEKDAY','WEEKEND'] as $dayType) $this->court->prices()->create(['time_slot_id'=>$slot->id,'price'=>100000,'day_type'=>$dayType,'effective_from'=>today()->subDay(),'status'=>'ACTIVE']);
        $this->actingAs($this->employee);
    }
    protected function booking()
    {
        $customer = User::factory()->create(['role'=>'CUSTOMER']);
        return app(BookingService::class)->createBooking($customer->id, [['court_id'=>$this->court->id,'time_slot_id'=>$this->slot->id,'booking_date'=>today()->toDateString()]]);
    }
    public function test_modes_render_and_free_slots_preserve_counter_context(): void
    {
        $booking = $this->booking();
        foreach (['day','week','month'] as $mode) $this->get(route('employee.schedule', compact('mode')))->assertOk()->assertSee($booking->booking_code)->assertSee('data-dialog=', false);
        $this->get(route('employee.schedule'))->assertViewHas('cells', fn ($cells) => $cells[$this->court->id.'|'.today()->toDateString()]['available'] === [$this->next->id])->assertSee('title="Tạo booking"', false);
        $this->get(route('employee.counter.create', ['court_id'=>$this->court->id,'booking_date'=>today()->toDateString(),'time_slot_id'=>$this->next->id]))->assertOk()->assertSee('checked', false);
        $this->get(route('employee.schedule', ['status'=>'COMPLETED']))->assertOk()->assertDontSee('class="sc-free"', false)->assertDontSee($booking->booking_code);
    }
    public function test_modal_actions_use_existing_checkin_and_checkout_guards(): void
    {
        $booking = $this->booking();
        app(PaymentService::class)->markAsPaid($booking->payment);
        $this->travelTo(now()->setTime(16, 59, 59));
        $this->get(route('employee.schedule'))->assertOk()->assertDontSee('data-staff-action="checkin"', false);
        $this->travelTo(now()->setTime(17, 0));
        $this->get(route('employee.schedule'))->assertOk()->assertSee('data-staff-action="checkin"', false)->assertDontSee('data-staff-action="checkout"', false);
        app(BookingOperationsService::class)->checkIn($booking->fresh(), $this->employee);
        $this->get(route('employee.schedule'))->assertOk()->assertSee('data-staff-action="checkout"', false)->assertSee('data-staff-action="services"', false)->assertSee('data-staff-action="extend"', false)->assertDontSee('data-staff-action="checkin"', false);
        $this->get(route('employee.dashboard'))->assertOk()->assertViewHas('statistics', fn ($stats) => $stats['playing_courts'] === 1 && $stats['checked_in'] === 1);
    }
    public function test_permissions_hide_actions_and_customer_access_is_denied(): void
    {
        $this->booking();
        $this->actingAs(User::factory()->create(['role'=>'EMPLOYEE','permissions'=>['employee.dashboard']]));
        $this->get(route('employee.schedule'))->assertOk()->assertDontSee('class="sc-free"', false)->assertDontSee('data-staff-action=', false);
        $this->get(route('employee.dashboard',['panel'=>'customers']))->assertForbidden();
        $this->actingAs(User::factory()->create(['role'=>'CUSTOMER']));
        $this->get(route('employee.schedule'))->assertForbidden();
    }
    public function test_customer_panel_and_existing_staff_destinations_render(): void
    {
        $this->booking();
        $this->get(route('employee.dashboard',['panel'=>'customers']))->assertOk()->assertViewHas('customers', fn ($rows) => $rows->total() === 1);
        foreach (['employee.dashboard','employee.bookings.index','employee.counter.create','employee.retail.index','employee.equipment.index','employee.shifts.index'] as $route) $this->get(route($route))->assertOk();
    }
    public function test_checkout_is_hidden_until_services_are_settled(): void
    {
        $booking = $this->booking();
        app(PaymentService::class)->markAsPaid($booking->payment);
        $this->travelTo(now()->setTime(17,59));
        $booking = app(BookingOperationsService::class)->checkIn($booking->fresh(), $this->employee);
        $item = \App\Models\ServiceItem::create(['code'=>'WATER-STAFF','name'=>'Nước','category'=>'SALE','price'=>10000,'stock'=>10,'is_active'=>true]);
        app(\App\Services\ServiceOrderService::class)->create($booking,$this->employee,[['service_item_id'=>$item->id,'quantity'=>1]],'staff-ui-order');
        $this->get(route('employee.schedule'))->assertOk()->assertDontSee('data-staff-action="checkout"',false)->assertSee('data-staff-action="services"',false);
    }
}
