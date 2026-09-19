<?php

namespace Tests\Feature;

use App\Models\{Booking, Court, CourtType, TimeSlot, User, Voucher};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_destinations_render_with_shared_components(): void
    {
        $admin = User::factory()->create(['role'=>'ADMIN']);
        $this->actingAs($admin);
        foreach (['admin.dashboard','admin.courts.index','admin.courts.create','admin.court-types.index','admin.court-types.create','admin.slots.index','admin.pricing.index','admin.bookings.index','admin.customers.index','admin.employees.index','admin.employees.create','admin.vouchers.index','admin.vouchers.create','admin.payments.index','admin.maintenance.index','admin.announcements.index','admin.incidents.index','admin.incidents.bulk','admin.users.index','admin.users.create','admin.roles.index','admin.settings.edit','admin.knowledge-base.index','admin.knowledge-base.create','admin.reports.index','admin.reports.cash-flow','special-refunds.index','refund-payouts.index','notifications.index'] as $destination) {
            $this->get(route($destination))->assertOk()->assertSee('admin-confirm', false)->assertSee('admin-ui.css', false);
        }
        foreach (array_keys(\App\Http\Controllers\AdminContentController::TYPES) as $kind) {
            $this->get(route('admin.content.index',$kind))->assertOk()->assertSee('admin-filters',false);
            if ($kind !== 'reviews') $this->get(route('admin.content.create',$kind))->assertOk();
        }
    }

    public function test_populated_pages_preserve_actions_and_dashboard_data(): void
    {
        $admin = User::factory()->create(['role'=>'ADMIN']);
        $customer = User::factory()->create(['role'=>'CUSTOMER']);
        $type = CourtType::create(['name'=>'Standard']);
        $court = Court::create(['code'=>'UI08','name'=>'Sân SmashZone 01','court_type_id'=>$type->id,'status'=>'ACTIVE','operational_status'=>'AVAILABLE']);
        $slot = TimeSlot::create(['name'=>'18:00 - 19:00','start_time'=>'18:00','end_time'=>'19:00','duration'=>60,'status'=>'ACTIVE']);
        $booking = Booking::create(['booking_code'=>'BK-ADMIN-UI','user_id'=>$customer->id,'status'=>'CHECKED_IN','payment_status'=>'PAID','total_amount'=>120000]);
        $booking->bookingDetails()->create(['court_id'=>$court->id,'time_slot_id'=>$slot->id,'booking_date'=>today(),'price'=>120000,'subtotal'=>120000,'status'=>'CHECKED_IN']);
        $voucher = Voucher::create(['code'=>'SPORT10','name'=>'Ưu đãi thể thao','discount_type'=>'PERCENTAGE','discount_value'=>10,'min_order_amount'=>100000,'start_at'=>now()->subDay(),'end_at'=>now()->addDays(7),'status'=>'ACTIVE']);
        $this->actingAs($admin);
        $dashboard = $this->get(route('admin.dashboard'))->assertOk()->assertSee('BK-ADMIN-UI')
            ->assertViewHas('todayStats',fn($stats)=>$stats['bookings']===1 && $stats['playing']===1 && $stats['courts']===1);
        $courts = $this->get(route('admin.courts.index'))->assertOk()->assertSee('admin-row-actions',false)->assertSee('value="DELETE"',false);
        $slots = $this->get(route('admin.slots.index'))->assertOk()->assertSee('slot-edit-'.$slot->id);
        $vouchers = $this->get(route('admin.vouchers.index'))->assertOk()->assertSee('SPORT10')->assertSee('admin-row-actions',false);
        $this->get(route('admin.bookings.index',['fixed'=>1]))->assertOk()->assertDontSee('BK-ADMIN-UI');
        foreach ([['admin.courts.show',$court],['admin.courts.edit',$court],['admin.bookings.show',$booking],['admin.customers.show',$customer],['admin.customers.edit',$customer],['admin.vouchers.edit',$voucher],['admin.users.edit',$admin]] as [$destination,$model]) $this->get(route($destination,$model))->assertOk();
        // Optional isolated HTML fixtures for browser review; never writes database fixtures to MySQL.
        if (getenv('ADMIN_UI_PREVIEW')) foreach (compact('dashboard','courts','slots','vouchers') as $name=>$response) {
            file_put_contents(storage_path('app/phase8-'.$name.'.html'), str_replace(['http://127.0.0.1:8000','http://localhost'], 'http://127.0.0.1:8765', $response->getContent()));
        }
    }
}
