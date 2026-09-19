<?php
namespace Tests\Feature;
use App\Models\{Booking,Court,CourtType,Payment,ServiceItem,TimeSlot,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class EmployeeUsecaseAuditTest extends TestCase {
    use RefreshDatabase;
    private function fixture(string $status='CONFIRMED'): array {
        $staff=User::factory()->create(['role'=>'EMPLOYEE','permissions'=>['employee.dashboard','bookings.view','bookings.checkin','bookings.checkout','payments.counter','services.manage']]);
        $customer=User::factory()->create(['role'=>'CUSTOMER']);
        $type=CourtType::create(['name'=>'Audit']);
        $court=Court::create(['code'=>'AUDIT','name'=>'Audit Court','court_type_id'=>$type->id,'status'=>'ACTIVE']);
        $slot=TimeSlot::create(['name'=>'09-10','start_time'=>'09:00','end_time'=>'10:00','duration'=>60,'status'=>'ACTIVE']);
        $booking=Booking::create(['booking_code'=>'AUDIT-BOOKING-123','user_id'=>$customer->id,'status'=>$status,'payment_status'=>'PAID','subtotal'=>100000,'total_amount'=>100000]);
        $booking->bookingDetails()->create(['court_id'=>$court->id,'time_slot_id'=>$slot->id,'booking_date'=>today(),'price'=>100000,'subtotal'=>100000,'status'=>'CONFIRMED']);
        $payment=Payment::create(['booking_id'=>$booking->id,'amount'=>100000,'status'=>'PAID','payment_method'=>'CASH','paid_at'=>now()]);
        $this->actingAs($staff);
        return [$staff,$booking,$payment];
    }
    public function test_existing_booking_is_visible_in_calendar():void {
        $this->fixture();
        $this->get(route('employee.schedule',['mode'=>'day','date'=>today()->toDateString()]))->assertOk()->assertSee('AUDIT-BOOKING-123');
    }
    public function test_adding_service_preserves_already_paid_receipt():void {
        [, $booking,$payment]=$this->fixture('CHECKED_IN');
        $item=ServiceItem::create(['code'=>'WATER','name'=>'Water','price'=>20000,'stock'=>5,'is_active'=>true]);
        $this->post(route('employee.bookings.services.store',$booking),['service_item_id'=>$item->id,'quantity'=>1])->assertSessionHas('success');
        $this->assertSame('PAID',$payment->fresh()->status,'Adding 20,000 VND service must preserve the paid 100,000 VND receipt.');
    }
    public function test_checkin_saves_staff_actor():void {
        [$staff,$booking]=$this->fixture();
        $this->post(route('employee.bookings.check-in',$booking))->assertSessionHas('success');
        $this->assertEquals($staff->id,$booking->fresh()->checked_in_by);
    }
    public function test_cashier_can_collect_only_new_service_amount():void {
        [, $booking]=$this->fixture('CHECKED_IN');
        $item=ServiceItem::create(['code'=>'WATER','name'=>'Water','price'=>20000,'stock'=>5,'is_active'=>true]);
        $this->post(route('employee.bookings.services.store',$booking),['service_item_id'=>$item->id,'quantity'=>1])->assertSessionHas('success');
        $this->post(route('employee.bookings.payment',$booking),['payment_method'=>'CASH','amount'=>20000,'transaction_id'=>'AUDIT-POS'])->assertSessionHas('success');
    }
}
