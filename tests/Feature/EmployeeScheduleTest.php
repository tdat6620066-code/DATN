<?php
namespace Tests\Feature;
use App\Models\{Booking, Court, CourtType, Payment, ServiceItem, TimeSlot, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class EmployeeScheduleTest extends TestCase
{
    use RefreshDatabase;
    private function fixture(string $status = 'CHECKED_IN'): array
    {
        $customer = User::factory()->create(['role' => 'CUSTOMER']);
        $staff = User::factory()->create(['role' => 'EMPLOYEE', 'permissions' => ['employee.dashboard', 'bookings.view', 'services.manage', 'payments.counter', 'bookings.checkout']]);
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


    public function test_calendar_groups_cast_dates_and_renders_each_mode(): void
    {
        [$customer,$staff,$booking] = $this->fixture();
        foreach (['day','week','month'] as $mode) {
            $this->actingAs($staff)->get(route('employee.schedule',['date'=>today()->addDay()->toDateString(),'mode'=>$mode]))
                ->assertOk()->assertSee($booking->booking_code)->assertSee('sc-dialog',false);
        }
    }
    public function test_filters_preserve_occupancy_and_expired_holds_are_excluded(): void
    {
        [$customer,$staff,$booking] = $this->fixture();
        $this->actingAs($staff)->get(route('employee.schedule',['date'=>today()->addDay()->toDateString(),'search'=>'no-such-booking']))
            ->assertOk()->assertDontSee($booking->booking_code)->assertViewHas('cells',fn($cells)=>collect($cells)->sum('used')===1);
        $booking->update(['status'=>'PENDING_PAYMENT','hold_expires_at'=>now()->subMinute()]);
        $this->get(route('employee.schedule',['date'=>today()->addDay()->toDateString()]))->assertOk()->assertDontSee($booking->booking_code);
    }
    public function test_customer_cannot_read_staff_calendar(): void
    {
        [$customer] = $this->fixture();
        $this->actingAs($customer)->get(route('employee.schedule'))->assertForbidden();
    }

    public function test_maintenance_and_contiguous_booking_blocks_are_visible(): void
    {
        [$customer,$staff,$booking] = $this->fixture();
        $detail = $booking->bookingDetails()->first();
        $slot = TimeSlot::create(['name'=>'20:00 - 21:00','start_time'=>'20:00','end_time'=>'21:00','duration'=>60,'status'=>'ACTIVE']);
        $booking->bookingDetails()->create(['court_id'=>$detail->court_id,'time_slot_id'=>$slot->id,'booking_date'=>today()->addDay(),'price'=>150000,'subtotal'=>150000,'status'=>'CONFIRMED']);
        \App\Models\MaintenanceSchedule::create(['court_id'=>$detail->court_id,'maintenance_date'=>today()->addDay(),'start_date'=>today()->addDay(),'end_date'=>today()->addDay(),'start_time'=>'20:00','end_time'=>'21:00','reason'=>'Sửa đèn','status'=>'SCHEDULED']);
        $this->actingAs($staff)->get(route('employee.schedule',['date'=>today()->addDay()->toDateString()]))
            ->assertOk()->assertSee('Sửa đèn')->assertSee('19:00 – 21:00')
            ->assertViewHas('cells', fn($cells)=>collect($cells)->first()['blocks']->count()===1 && collect($cells)->first()['free']===0);
    }
}
