<?php
namespace Tests\Feature;

use App\Http\Controllers\AdminContentController;
use App\Models\{AccessRole, Banner, Booking, Brand, ChatbotFaq, ContactThread, Court, CourtPrice, CourtType, News, Review, ServiceItem, SystemSetting, TimeSlot, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create(['role'=>'ADMIN']);
        $this->actingAs($admin);
        return $admin;
    }

    public function test_admin_can_open_all_new_management_forms(): void
    {
        $this->admin();
        foreach (array_keys(AdminContentController::TYPES) as $kind) {
            $this->get(route('admin.content.index',$kind))->assertOk();
            if ($kind!=='reviews') $this->get(route('admin.content.create',$kind))->assertOk();
        }
        foreach (['admin.users.index','admin.users.create','admin.roles.index','admin.settings.edit','admin.slots.index','contacts.index'] as $route) $this->get(route($route))->assertOk();
    }

    public function test_non_admin_cannot_read_or_mutate_management_resources(): void
    {
        $this->actingAs(User::factory()->create(['role'=>'CUSTOMER']));
        foreach (['admin.users.index','admin.roles.index','admin.settings.edit','admin.slots.index','admin.reports.export'] as $route) $this->get(route($route))->assertForbidden();
        foreach (array_keys(AdminContentController::TYPES) as $kind) {
            $this->get(route('admin.content.index',$kind))->assertForbidden();
            $this->post(route('admin.content.store',$kind),[])->assertForbidden();
        }
        $this->assertDatabaseCount('brands',0);
    }

    public function test_brand_crud_upload_and_link_validation(): void
    {
        $this->admin(); Storage::fake('public');
        $this->post(route('admin.content.store','brands'),['name'=>'Yonex','status'=>'ACTIVE','website'=>'https://example.com','image'=>UploadedFile::fake()->image('brand.png')])->assertSessionHasNoErrors()->assertRedirect();
        $brand = Brand::firstOrFail(); Storage::disk('public')->assertExists($brand->image);
        $this->get(route('admin.content.show',['brands',$brand->id]))->assertOk()->assertSee('Yonex');
        $this->put(route('admin.content.update',['brands',$brand->id]),['name'=>'Yonex mới','status'=>'INACTIVE','website'=>'javascript:alert(1)'])->assertSessionHasErrors('website');
        $this->put(route('admin.content.update',['brands',$brand->id]),['name'=>'Yonex mới','status'=>'INACTIVE'])->assertSessionHasNoErrors();
        $this->assertSame('INACTIVE',$brand->fresh()->status);
        $this->delete(route('admin.content.destroy',['brands',$brand->id]))->assertRedirect();
        $this->assertDatabaseCount('brands',0);
    }

    public function test_service_stock_is_audited_and_cannot_be_negative(): void
    {
        $this->admin();
        $this->post(route('admin.content.store','services'),['code'=>'WATER','name'=>'Nước','category'=>'DRINK','price'=>15000,'is_active'=>1])->assertSessionHasNoErrors();
        $service = ServiceItem::firstOrFail(); $this->assertEquals(0,$service->stock);
        $this->post(route('admin.services.stock',$service),['quantity'=>8,'reason'=>'Nhập hàng'])->assertSessionHasNoErrors();
        $this->post(route('admin.services.stock',$service),['quantity'=>-9,'reason'=>'Xuất hàng'])->assertSessionHasErrors('quantity');
        $this->assertEquals(8,$service->fresh()->stock); $this->assertDatabaseCount('stock_movements',1);
        $this->get(route('admin.content.edit',['services',$service->id]))->assertOk()->assertSee('Nhập hàng');
        $this->delete(route('admin.content.destroy',['services',$service->id]))->assertRedirect();
        $this->assertFalse($service->fresh()->is_active);
    }

    public function test_news_faq_and_slider_appear_only_when_published(): void
    {
        $this->admin();
        $this->post(route('admin.content.store','news'),['title'=>'Tin mới','content'=>'Nội dung an toàn <script>alert(1)</script>','status'=>'PUBLISHED'])->assertSessionHasNoErrors();
        $news = News::firstOrFail();
        $this->get(route('news.show',$news))->assertOk()->assertSee('&lt;script&gt;',false)->assertDontSee('<script>alert(1)</script>',false);
        $this->post(route('admin.content.store','faqs'),['category'=>'booking','question'=>'Đặt sân thế nào?','answer'=>'Chọn sân và giờ.','priority'=>5,'active'=>1])->assertSessionHasNoErrors();
        $this->post(route('admin.content.store','banners'),['title'=>'Slider mới','status'=>'ACTIVE','sort_order'=>0])->assertSessionHasNoErrors();
        $this->get(route('information'))->assertOk()->assertSee('Đặt sân thế nào?');
        $this->put(route('admin.content.update',['news',$news->id]),['title'=>'Tin mới','content'=>'Nội dung','status'=>'DRAFT'])->assertSessionHasNoErrors();
        $this->get(route('news.show',$news))->assertNotFound();
        $this->get(route('home'))->assertOk()->assertViewHas('banners',fn($b)=>$b->contains('title','Slider mới'));
    }

    public function test_role_permissions_are_saved_and_enforced(): void
    {
        $admin = $this->admin();
        $this->post(route('admin.roles.store'),['name'=>'Lễ tân','permissions'=>['employee.dashboard']])->assertSessionHasNoErrors();
        $role = AccessRole::firstOrFail();
        $this->post(route('admin.users.store'),['name'=>'Nhân viên','email'=>'staff-role@example.com','password'=>'password123','password_confirmation'=>'password123','role'=>'EMPLOYEE','status'=>'ACTIVE','customer_segment'=>'REGULAR','access_role_id'=>$role->id,'permissions'=>['bookings.view']])->assertSessionHasNoErrors();
        $staff = User::where('email','staff-role@example.com')->firstOrFail();
        $this->assertTrue($staff->hasPermission('employee.dashboard')); $this->assertTrue($staff->hasPermission('bookings.view'));
        $this->actingAs($staff)->get(route('employee.dashboard'))->assertOk();
        $this->actingAs($admin)->put(route('admin.roles.update',$role),['name'=>'Lễ tân','permissions'=>[]])->assertSessionHasNoErrors();
        $this->actingAs($staff->fresh())->get(route('employee.dashboard'))->assertForbidden();
        $this->actingAs($admin)->delete(route('admin.roles.destroy',$role))->assertSessionHas('error');
    }

    public function test_admin_cannot_demote_self_and_customer_cannot_gain_staff_permission(): void
    {
        $admin = $this->admin();
        $this->put(route('admin.users.update',$admin),['name'=>$admin->name,'email'=>$admin->email,'role'=>'CUSTOMER','status'=>'ACTIVE','customer_segment'=>'REGULAR'])->assertSessionHasErrors('role');
        $this->assertSame('ADMIN',$admin->fresh()->role);
        $customer = User::factory()->create(['permissions'=>['employee.dashboard']]);
        $this->assertFalse($customer->hasPermission('employee.dashboard'));
    }

    public function test_user_delete_preserves_booking_history(): void
    {
        $this->admin(); $customer = User::factory()->create();
        Booking::create(['booking_code'=>'HISTORY','user_id'=>$customer->id]);
        $this->delete(route('admin.users.destroy',$customer))->assertSessionHas('error');
        $this->assertDatabaseHas('users',['id'=>$customer->id]);
        $unused = User::factory()->create();
        $this->delete(route('admin.users.destroy',$unused))->assertRedirect();
        $this->assertDatabaseMissing('users',['id'=>$unused->id]);
    }

    public function test_contact_conversation_is_private_and_admin_can_reply(): void
    {
        $customer = User::factory()->create(['role'=>'CUSTOMER']); $other = User::factory()->create(['role'=>'CUSTOMER']);
        $this->actingAs($customer)->post(route('contacts.store'),['subject'=>'Cần hỗ trợ','body'=>'Tôi cần đổi sân.'])->assertSessionHasNoErrors()->assertRedirect();
        $thread = ContactThread::firstOrFail();
        $this->actingAs($other)->get(route('contacts.show',$thread))->assertForbidden();
        $this->actingAs($other)->post(route('contacts.reply',$thread),['body'=>'Truy cập trái phép'])->assertForbidden();
        $admin = $this->admin();
        $this->post(route('contacts.reply',$thread),['body'=>'Vui lòng cung cấp mã đặt sân.'])->assertSessionHasNoErrors();
        $this->actingAs($customer)->get(route('contacts.show',$thread))->assertOk()->assertSee('Vui lòng cung cấp mã đặt sân.');
        $this->actingAs($admin)->put(route('contacts.status',$thread),['status'=>'CLOSED'])->assertSessionHasNoErrors();
        $this->actingAs($customer)->post(route('contacts.reply',$thread),['body'=>'Tin mới'])->assertStatus(422);
        $this->assertDatabaseCount('contact_messages',2);
    }

    public function test_settings_persist_and_disabled_gateway_is_blocked(): void
    {
        $this->admin();
        $this->put(route('admin.settings.update'),['site_name'=>'Sân thử nghiệm','vnpay_enabled'=>0,'cash_enabled'=>1])->assertSessionHasNoErrors();
        $this->get(route('information'))->assertOk()->assertSee('Sân thử nghiệm');
        $this->assertSame('0',SystemSetting::valueFor('vnpay_enabled'));
        $booking = Booking::create(['booking_code'=>'GATEWAY','user_id'=>User::factory()->create()->id]);
        try { app(\App\Services\VnPayService::class)->createPaymentUrl($booking,'http://localhost/return'); $this->fail('Disabled gateway must reject payment.'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(422,$e->getStatusCode()); }
    }

    public function test_slot_reactivation_checks_overlap_and_render_edit(): void
    {
        $this->admin();
        TimeSlot::create(['name'=>'Sáng','start_time'=>'09:00','end_time'=>'10:00','duration'=>60,'status'=>'ACTIVE']);
        $slot = TimeSlot::create(['name'=>'Trùng','start_time'=>'09:30','end_time'=>'10:30','duration'=>60,'status'=>'INACTIVE']);
        $this->put(route('admin.slots.toggle',$slot))->assertSessionHas('error');
        $this->assertSame('INACTIVE',$slot->fresh()->status);
        $this->get(route('admin.slots.index'))->assertOk()->assertSee('Trùng');
    }

    public function test_report_download_is_csv_and_rejects_invalid_date_range(): void
    {
        $this->admin();
        $response = $this->get(route('admin.reports.export'));
        $response->assertOk()->assertDownload();
        $this->assertStringContainsString('Tiền đã thu',$response->streamedContent());
        $this->get(route('admin.reports.export',['from'=>'2026-09-14','to'=>'2026-01-01']))->assertSessionHasErrors('to');
    }

    public function test_customer_segmentation_and_court_filters_work(): void
    {
        $this->admin();
        User::factory()->create(['name'=>'Khách VIP kiểm thử','customer_segment'=>'VIP']);
        User::factory()->create(['name'=>'Khách thường kiểm thử','customer_segment'=>'REGULAR']);
        $this->get(route('admin.customers.index',['segment'=>'VIP']))->assertOk()->assertSee('Khách VIP kiểm thử')->assertDontSee('Khách thường kiểm thử');
        $type = CourtType::create(['name'=>'Loại kiểm thử']);
        $court = Court::create(['name'=>'Sân tạm đóng','code'=>'FILTER','court_type_id'=>$type->id,'status'=>'INACTIVE']);
        $this->get(route('admin.courts.index',['status'=>'ACTIVE']))->assertOk()->assertDontSee('Sân tạm đóng');
        $this->get(route('admin.courts.show',$court))->assertOk()->assertSee('Sân tạm đóng');
    }

    public function test_review_can_be_hidden_without_deleting_booking(): void
    {
        $this->admin(); $customer = User::factory()->create();
        $type = CourtType::create(['name'=>'Review']); $court = Court::create(['name'=>'Sân review','code'=>'REVIEW','court_type_id'=>$type->id]);
        $booking = Booking::create(['booking_code'=>'REVIEW','user_id'=>$customer->id]);
        $review = Review::create(['user_id'=>$customer->id,'court_id'=>$court->id,'booking_id'=>$booking->id,'rating'=>5,'content'=>'Sân tốt','status'=>'APPROVED']);
        $this->put(route('admin.content.update',['reviews',$review->id]),['content'=>'Sân tốt','status'=>'REJECTED'])->assertSessionHasNoErrors();
        $this->get(route('home'))->assertOk()->assertViewHas('reviews',fn($r)=>$r->isEmpty());
        $this->delete(route('admin.content.destroy',['reviews',$review->id]))->assertRedirect();
        $this->assertDatabaseHas('bookings',['id'=>$booking->id]);
    }

    public function test_reschedule_checks_availability_and_keeps_audit_and_price(): void
    {
        $this->admin(); $customer = User::factory()->create();
        $type = CourtType::create(['name'=>'Reschedule']);
        $court = Court::create(['name'=>'Sân đổi lịch','code'=>'MOVE','court_type_id'=>$type->id,'status'=>'ACTIVE','opening_time'=>'06:00','closing_time'=>'22:00']);
        $slot = TimeSlot::create(['name'=>'09:00','start_time'=>'09:00','end_time'=>'10:00','duration'=>60,'status'=>'ACTIVE']);
        CourtPrice::create(['court_id'=>$court->id,'time_slot_id'=>$slot->id,'price'=>100000,'day_type'=>'WEEKDAY','effective_from'=>today(),'status'=>'ACTIVE']);
        $booking = Booking::create(['booking_code'=>'MOVE','user_id'=>$customer->id,'status'=>'CONFIRMED']);
        $line = $booking->bookingDetails()->create(['court_id'=>$court->id,'time_slot_id'=>$slot->id,'booking_date'=>today()->addDays(2),'price'=>100000,'subtotal'=>100000,'status'=>'CONFIRMED']);
        $data = ['booking_date'=>today()->addDays(3)->toDateString(),'time_slot_id'=>$slot->id,'reason'=>'Khách yêu cầu'];
        $hold = Booking::create(['booking_code'=>'HELD','user_id'=>$customer->id,'status'=>'PENDING_PAYMENT','hold_expires_at'=>now()->addMinutes(10)]);
        $hold->bookingDetails()->create(['court_id'=>$court->id,'time_slot_id'=>$slot->id,'booking_date'=>$data['booking_date'],'price'=>100000,'subtotal'=>100000,'status'=>'PENDING']);
        $this->put(route('admin.bookings.reschedule',[$booking,$line]),$data)->assertSessionHas('error');
        $this->assertSame(today()->addDays(2)->toDateString(),$line->fresh()->booking_date->toDateString());
        $hold->update(['hold_expires_at'=>now()->subMinute()]);
        $this->put(route('admin.bookings.reschedule',[$booking,$line]),$data)->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertSame($data['booking_date'],$line->fresh()->booking_date->toDateString());
        $this->assertDatabaseHas('booking_audit_logs',['booking_id'=>$booking->id,'action'=>'RESCHEDULED']);
        $this->put(route('admin.bookings.reschedule',[$booking,$line]),$data)->assertSessionHas('error');
    }

    public function test_only_ended_unpaid_bookings_can_be_removed_from_admin_list(): void
    {
        $this->admin(); $customer = User::factory()->create();
        $booking = Booking::create(['booking_code'=>'ARCHIVE','user_id'=>$customer->id,'status'=>'CONFIRMED']);
        $this->delete(route('admin.bookings.destroy',$booking))->assertStatus(422);
        $booking->update(['status'=>'CANCELLED']);
        $this->delete(route('admin.bookings.destroy',$booking))->assertRedirect();
        $this->get(route('admin.bookings.index'))->assertOk()->assertDontSee('ARCHIVE');
        $this->assertDatabaseHas('bookings',['id'=>$booking->id]);
    }

    public function test_court_create_route_is_not_shadowed_by_detail_route(): void
    {
        $this->admin();
        $this->get(route('admin.courts.create'))->assertOk();
    }

    public function test_used_slot_times_cannot_rewrite_booking_history(): void
    {
        $this->admin();
        $type = CourtType::create(['name' => 'History']);
        $court = Court::create(['code' => 'HISTORY', 'name' => 'History', 'court_type_id' => $type->id]);
        $slot = TimeSlot::create(['name' => 'Morning', 'start_time' => '09:00', 'end_time' => '10:00', 'duration' => 60]);
        $booking = Booking::create(['booking_code' => 'SLOT-HISTORY', 'user_id' => User::factory()->create()->id]);
        $booking->bookingDetails()->create(['court_id' => $court->id, 'time_slot_id' => $slot->id, 'booking_date' => today(), 'price' => 100000, 'subtotal' => 100000]);
        $this->put(route('admin.pricing.slots.update', $slot), ['name' => 'Changed', 'start_time' => '10:00', 'end_time' => '11:00', 'duration' => 60])->assertSessionHas('error');
        $this->assertSame('09:00', substr($slot->fresh()->start_time, 0, 5));
    }

    public function test_service_brand_is_saved_and_cannot_be_deleted_while_in_use(): void
    {
        $this->admin();
        $brand = Brand::create(['name' => 'Brand']);
        $this->post(route('admin.content.store', 'services'), ['code' => 'BRANDED', 'name' => 'Water', 'price' => 10000, 'brand_id' => $brand->id, 'is_active' => 1])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('service_items', ['code' => 'BRANDED', 'brand_id' => $brand->id]);
        $this->delete(route('admin.content.destroy', ['brands', $brand->id]))->assertSessionHas('error');
        $this->assertDatabaseHas('brands', ['id' => $brand->id]);
    }

    public function test_disabled_cash_does_not_change_payment_status(): void
    {
        SystemSetting::create(['key' => 'cash_enabled', 'value' => '0']);
        $booking = Booking::create(['booking_code' => 'NO-CASH', 'user_id' => User::factory()->create()->id]);
        $payment = $booking->payment()->create(['amount' => 100000, 'status' => 'PENDING']);
        try {
            app(\App\Services\PaymentService::class)->markAsPaid($payment, null, 'CASH');
            $this->fail('Disabled cash must reject payment.');
        } catch (\DomainException $e) {
            $this->assertSame('PENDING', $payment->fresh()->status);
        }
    }
}
