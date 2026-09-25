<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Court;
use App\Models\CourtIncident;
use App\Models\CourtPrice;
use App\Models\CourtType;
use App\Models\IncidentEvidence;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\RefundRequest;
use App\Models\TimeSlot;
use App\Models\User;
use App\Services\RevenueReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IncidentTicketTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Concerns\CreatesReceiptImages;

    private function fixture(): array
    {
        Storage::fake('local');
        $customer = User::factory()->create(['role' => 'CUSTOMER']);
        $staff = User::factory()->create(['role' => 'EMPLOYEE', 'permissions' => ['incidents.manage', 'bookings.view']]);
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $type = CourtType::create(['name' => 'Standard']);
        $court = Court::create(['code' => 'TK-A', 'name' => 'Court A', 'court_type_id' => $type->id, 'status' => 'ACTIVE']);
        $slot = TimeSlot::create(['name' => 'Evening', 'start_time' => '19:00:00', 'end_time' => '20:00:00', 'duration' => 60, 'status' => 'ACTIVE']);
        $booking = Booking::create(['booking_code' => 'TICKET-BOOKING', 'user_id' => $customer->id, 'subtotal' => 150000, 'total_amount' => 150000, 'status' => 'CONFIRMED', 'payment_status' => 'PAID']);
        $booking->bookingDetails()->create(['court_id' => $court->id, 'time_slot_id' => $slot->id, 'booking_date' => today()->addDay(), 'price' => 150000, 'subtotal' => 150000, 'status' => 'CONFIRMED']);
        Payment::create(['booking_id' => $booking->id, 'amount' => 150000, 'status' => 'PAID', 'paid_at' => now()]);

        return [$customer, $staff, $admin, $booking];
    }

    private function payload(Booking $booking): array
    {
        return ['booking_detail_id' => $booking->bookingDetails()->first()->id, 'type' => 'WEATHER', 'description' => 'Sân bị ngập do mưa lớn, không thể sử dụng.', 'requested_solution' => 'REFUND', 'bank_name' => 'Test Bank', 'bank_account_number' => '001234567890', 'bank_account_holder' => 'TEST CUSTOMER', 'recipient_confirmed' => 1];
    }

    public function test_refund_approval_uses_all_slots_and_cancels_entire_booking(): void
    {
        [$customer, , $admin, $booking] = $this->fixture();
        $detail = $booking->bookingDetails()->first();
        $slot = TimeSlot::create(['name' => 'Later', 'start_time' => '20:00', 'end_time' => '21:00', 'duration' => 60, 'status' => 'ACTIVE']);
        $booking->bookingDetails()->create(['court_id' => $detail->court_id, 'time_slot_id' => $slot->id, 'booking_date' => $detail->booking_date, 'price' => 150000, 'subtotal' => 150000, 'status' => 'CONFIRMED']);
        $booking->update(['subtotal' => 300000, 'total_amount' => 270000]);
        $booking->payment->update(['amount' => 270000]);
        $this->actingAs($customer)->post(route('incident-tickets.store', $booking), $this->payload($booking))->assertSessionHasNoErrors();
        $ticket = CourtIncident::firstOrFail();
        $this->actingAs($admin)->post(route('incident-tickets.review', $ticket), ['action' => 'APPROVED', 'note' => 'Whole booking'])->assertSessionHasNoErrors();
        $refund = RefundRequest::firstOrFail();
        $this->assertSame('270000.00', $refund->amount);
        $this->assertTrue((bool) $refund->cancel_booking);
        $this->assertSame(2, $booking->bookingDetails()->where('status', 'CANCELLED')->count());
        $this->assertSame('CANCELLED', $booking->fresh()->status);
    }

    public function test_customer_creates_ticket_without_refund_and_one_open_ticket_per_booking(): void
    {
        [$customer,$staff,$admin,$booking] = $this->fixture();
        $this->actingAs($customer)->get(route('incident-tickets.create', $booking))->assertOk()->assertSee('Báo cáo sự cố');
        $this->post(route('incident-tickets.store', $booking), $this->payload($booking))->assertRedirect()->assertSessionHasNoErrors();
        $ticket = CourtIncident::firstOrFail();
        $this->assertSame('PENDING', $ticket->status);
        $this->assertSame('CUSTOMER', $ticket->source);
        $this->assertSame('CONFIRMED', $booking->fresh()->status);
        $this->assertDatabaseCount('refund_requests', 0);
        $this->assertSame(3, Notification::count());
        $this->assertDatabaseHas('user_notifications', ['user_id' => $staff->id, 'booking_id' => $booking->id]);
        $this->assertDatabaseHas('user_notifications', ['user_id' => $admin->id, 'booking_id' => $booking->id]);
        $this->post(route('incident-tickets.store', $booking), $this->payload($booking))->assertSessionHasErrors('description');
        $this->assertDatabaseCount('court_incidents', 1);
        $this->get(route('incident-tickets.show', $ticket))->assertOk()->assertSee('Chờ xử lý');
        $this->actingAs($staff)->get(route('incident-tickets.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertSee('Yêu cầu xử lý sự cố');
    }

    public function test_refund_requires_confirmed_bank_details_and_does_not_flash_them(): void
    {
        [$customer,,,$booking] = $this->fixture();
        $payload = $this->payload($booking);
        unset($payload['recipient_confirmed']);
        $this->actingAs($customer)->post(route('incident-tickets.store', $booking), $payload)
            ->assertSessionHasErrors('recipient_confirmed')->assertSessionMissing('_old_input.bank_account_number');
        $this->assertDatabaseCount('court_incidents', 0);
        $payload = $this->payload($booking);
        $payload['bank_account_number'] = 'abc';
        $this->post(route('incident-tickets.store', $booking), $payload)->assertSessionHasErrors('bank_account_number');
        $this->post(route('incident-tickets.store', $booking), $this->payload($booking))->assertSessionHasNoErrors();
        $ticket = CourtIncident::firstOrFail();
        $this->assertSame('001234567890', $ticket->refund_recipient['bank_account_number']);
        $this->assertStringNotContainsString('001234567890', $ticket->getRawOriginal('refund_recipient'));
        $this->assertArrayNotHasKey('refund_recipient', $ticket->toArray());
    }

    public function test_non_refund_solution_does_not_require_or_store_bank_details(): void
    {
        [$customer,,,$booking] = $this->fixture();
        $payload = $this->payload($booking);
        $payload['requested_solution'] = 'RESCHEDULE';
        $payload['bank_account_number'] = 'invalid-ignored';
        unset($payload['recipient_confirmed']);
        $this->actingAs($customer)->post(route('incident-tickets.store', $booking), $payload)->assertSessionHasNoErrors();
        $this->assertNull(CourtIncident::firstOrFail()->refund_recipient);
        $this->assertDatabaseCount('refund_bank_accounts', 0);
    }

    public function test_delayed_approval_preserves_original_bank_confirmation_time(): void
    {
        [$customer,,$admin,$booking] = $this->fixture();
        $this->actingAs($customer)->post(route('incident-tickets.store', $booking), $this->payload($booking))->assertSessionHasNoErrors();
        $ticket = CourtIncident::firstOrFail();
        $confirmedAt = $ticket->refund_recipient['confirmed_at'];
        $this->travel(25)->hours();
        $this->actingAs($admin)->post(route('incident-tickets.review', $ticket), ['action' => 'APPROVED', 'note' => 'Đã xác minh sự cố', 'amount' => 150000])->assertSessionHasNoErrors();
        $refund = RefundRequest::firstOrFail();
        $this->assertSame('APPROVED', $refund->status);
        $this->assertTrue($refund->bankAccount->confirmed_at->equalTo(Carbon::parse($confirmedAt)));
        $this->assertSame('WAITING_BANK_CONFIRMATION', $refund->payout_status);
        $this->post(route('special-refunds.processing', $refund), ['refund_method' => 'BANK_TRANSFER'])->assertSessionHasErrors('refund_method');
        $this->assertNull($refund->fresh()->processing_started_at);
    }

    public function test_staff_verifies_admin_approves_and_refund_completion_closes_ticket(): void
    {
        [$customer,$staff,$admin,$booking] = $this->fixture();
        $this->actingAs($customer)->post(route('incident-tickets.store', $booking), $this->payload($booking));
        $ticket = CourtIncident::firstOrFail();
        $this->actingAs($staff)->post(route('incident-tickets.review', $ticket), ['action' => 'APPROVED', 'note' => 'Sân thực sự ngập', 'amount' => 150000])->assertForbidden();
        $this->post(route('incident-tickets.review', $ticket), ['action' => 'NEED_MORE_INFO', 'note' => 'Vui lòng mô tả thời gian xảy ra.'])->assertSessionHasNoErrors();
        $this->actingAs($customer)->post(route('incident-tickets.supplement', $ticket), ['note' => 'Mưa lớn từ 19h.'])->assertSessionHasNoErrors();
        $this->assertSame('REVIEWING', $ticket->fresh()->status);
        $this->actingAs($staff)->post(route('incident-tickets.review', $ticket), ['action' => 'PROPOSE', 'proposed_solution' => 'REFUND', 'note' => 'Đã xác minh sân ngập, đề nghị hoàn toàn bộ.', 'amount' => 150000])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('refund_requests', 0);
        $this->actingAs($admin)->post(route('incident-tickets.review', $ticket), ['action' => 'APPROVED', 'note' => 'Xác nhận không cung cấp được dịch vụ.', 'amount' => 100000])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('refund_requests', 1);
        $this->actingAs($customer)->get(route('incident-tickets.show', $ticket))->assertOk()->assertSee('Thông tin nhận hoàn tiền');
        $this->actingAs($admin);
        $refund = RefundRequest::firstOrFail();
        $this->assertSame('APPROVED', $refund->status);
        $this->assertSame('PAID', $booking->fresh()->payment->status);
        $this->assertSame('APPROVED', $ticket->fresh()->status);
        $this->post(route('incident-tickets.review', $ticket), ['action' => 'RESOLVED', 'note' => 'Thử đóng trước hoàn tiền'])->assertSessionHasErrors();
        $this->actingAs($staff)->post(route('special-refunds.review', $refund), ['decision' => 'APPROVED', 'decision_note' => 'Thử duyệt'])->assertForbidden();
        $this->actingAs($admin);
        $this->assertSame('150000.00', $refund->fresh()->amount);
        $this->post(route('special-refunds.processing', $refund), ['refund_method' => 'CASH'])->assertSessionHasNoErrors();
        $this->post(route('special-refunds.complete', $refund), ['amount' => 150000, 'receipt_image' => $this->receiptImage(), 'refund_code' => 'TICKET-REFUND'])->assertSessionHasNoErrors();
        $this->assertSame('RESOLVED', $ticket->fresh()->status);
        $this->assertNull($ticket->fresh()->active_booking_id);
        $this->assertSame('REFUNDED', $booking->fresh()->payment->refund_status);
    }

    public function test_private_evidence_and_cross_customer_authorization(): void
    {
        [$customer,$staff,$admin,$booking] = $this->fixture();
        $stranger = User::factory()->create(['role' => 'CUSTOMER']);
        $this->actingAs($stranger)->post(route('incident-tickets.store', $booking), $this->payload($booking))->assertForbidden();
        $image = UploadedFile::fake()->createWithContent('proof.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));
        $this->actingAs($customer)->post(route('incident-tickets.store', $booking), $this->payload($booking) + ['evidences' => [$image]])->assertSessionHasNoErrors();
        $ticket = CourtIncident::firstOrFail();
        $evidence = IncidentEvidence::firstOrFail();
        Storage::disk('local')->assertExists($evidence->file_path);
        $this->get(route('incident-tickets.evidence', $evidence))->assertOk()->assertHeader('Content-Type', 'image/png')
            ->assertHeader('Content-Disposition', 'inline; filename=evidence-'.$evidence->id.'.png');
        $this->get(route('incident-tickets.show', $ticket))->assertOk()->assertSee('<img src="'.route('incident-tickets.evidence', $evidence).'"', false);
        $this->actingAs($stranger)->get(route('incident-tickets.show', $ticket))->assertForbidden();
        $this->get(route('incident-tickets.evidence', $evidence))->assertForbidden();
        $this->post(route('incident-tickets.supplement', $ticket), ['note' => 'Forged'])->assertForbidden();
        $this->actingAs($staff)->get(route('incident-tickets.evidence', $evidence))->assertOk();
        $unprivileged = User::factory()->create(['role' => 'EMPLOYEE', 'permissions' => []]);
        $this->actingAs($unprivileged)->get(route('incident-tickets.show', $ticket))->assertForbidden();
        $this->actingAs($admin)->put(route('admin.incidents.update', $ticket), ['status' => 'CLOSED'])->assertForbidden();
    }

    public function test_direct_special_refund_closes_reviewing_refund_ticket_only_after_payment(): void
    {
        [$customer, $staff, $admin, $booking] = $this->fixture();
        $this->actingAs($customer)->post(route('incident-tickets.store', $booking), $this->payload($booking))->assertSessionHasNoErrors();
        $ticket = CourtIncident::firstOrFail();
        $this->actingAs($staff)->post(route('incident-tickets.review', $ticket), ['action' => 'REVIEWING', 'note' => 'Đang xác minh.'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('special-refunds.store', $booking), [
            'reason_code' => 'SERVICE_INTERRUPTED', 'reason' => 'Sân ngập.',
            'supporting_information' => 'Hoàn phần thời gian chưa sử dụng.',
            'amount' => 100000, 'approve_now' => 1,
        ])->assertSessionHasNoErrors();
        $refund = RefundRequest::firstOrFail();
        $this->assertSame('REVIEWING', $ticket->fresh()->status);
        $this->post(route('special-refunds.processing', $refund), ['refund_method' => 'CASH'])->assertSessionHasNoErrors();
        $this->post(route('special-refunds.complete', $refund), ['amount' => 100000, 'receipt_image' => $this->receiptImage(), 'refund_code' => 'DIRECT-REFUND'])->assertSessionHasErrors('amount');
        $this->assertSame('REVIEWING', $ticket->fresh()->status);
        $this->post(route('special-refunds.complete', $refund), ['amount' => 150000, 'receipt_image' => $this->receiptImage(), 'refund_code' => 'DIRECT-REFUND'])->assertSessionHasNoErrors();
        $this->assertSame('RESOLVED', $ticket->fresh()->status);
        $this->assertNull($ticket->fresh()->active_booking_id);
        $this->assertTrue($ticket->fresh()->resolved_at->equalTo($refund->fresh()->refund->processed_at));
        $this->assertSame(0, CourtIncident::where('source', 'CUSTOMER')->where('status', 'REVIEWING')->count());
        $this->assertSame(1, CourtIncident::where('status', 'RESOLVED')->whereDate('resolved_at', today())->count());
        app(\App\Services\IncidentTicketService::class)->closeAfterDirectRefund($refund->fresh());
        $this->assertSame(1, $ticket->updates()->where('event_type', 'RESOLVED')->count());

        // A completed refund must not silently close a request to contact the customer.
        $ticket->update(['status' => 'REVIEWING', 'requested_solution' => 'CONTACT_ME', 'resolved_at' => null]);
        app(\App\Services\IncidentTicketService::class)->closeAfterDirectRefund($refund->fresh());
        $this->assertSame('REVIEWING', $ticket->fresh()->status);
    }

    public function test_rejection_reopens_submission_and_invalid_upload_is_rejected(): void
    {
        [$customer,,$admin,$booking] = $this->fixture();
        $this->actingAs($customer)->post(route('incident-tickets.store', $booking), $this->payload($booking) + ['evidences' => [UploadedFile::fake()->create('bad.php', 1, 'application/x-php')]])->assertSessionHasErrors();
        $this->assertDatabaseCount('court_incidents', 0);
        $this->post(route('incident-tickets.store', $booking), $this->payload($booking))->assertSessionHasNoErrors();
        $ticket = CourtIncident::firstOrFail();
        $this->actingAs($admin)->post(route('incident-tickets.review', $ticket), ['action' => 'REJECTED', 'note' => 'Không xác minh được sự cố.'])->assertSessionHasNoErrors();
        $this->assertNull($ticket->fresh()->active_booking_id);
        $this->assertDatabaseCount('refund_requests', 0);
        $this->actingAs($customer)->post(route('incident-tickets.store', $booking), $this->payload($booking))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('court_incidents', 2);
    }

    public function test_approved_reschedule_uses_existing_resolution_flow_and_closes_ticket(): void
    {
        [$customer, , $admin, $booking] = $this->fixture();
        $payload = $this->payload($booking);
        $payload['requested_solution'] = 'RESCHEDULE';
        $this->actingAs($customer)->post(route('incident-tickets.store', $booking), $payload)->assertRedirect();
        $ticket = CourtIncident::firstOrFail();
        $this->actingAs($admin)->get(route('incident-tickets.show', $ticket))->assertOk();
        $this->post(route('incident-tickets.review', $ticket), ['action' => 'APPROVED', 'note' => 'Xác minh, cho khách chọn lại lịch.', 'amount' => 150000])->assertSessionHasNoErrors();
        $resolution = $ticket->resolutions()->firstOrFail();
        $detail = $booking->bookingDetails()->first();
        CourtPrice::create(['court_id' => $detail->court_id, 'time_slot_id' => $detail->time_slot_id, 'price' => 150000, 'day_type' => 'WEEKDAY', 'effective_from' => today()->subYear(), 'status' => 'ACTIVE']);
        $this->actingAs($customer)->post(route('incident-resolutions.choose', $resolution), ['choice' => 'RESCHEDULE', 'court_id' => $detail->court_id, 'time_slot_id' => $detail->time_slot_id, 'date' => today()->addDays(2)->toDateString()])->assertSessionHasNoErrors();
        $this->assertSame('RESOLVED', $ticket->fresh()->status);
        $this->assertSame('CONFIRMED', $booking->fresh()->status);
        $this->assertDatabaseCount('refund_requests', 0);
        $this->assertSame(today()->addDay()->toDateString(), $ticket->fresh()->booking_snapshot['date']);
    }

    public function test_staff_can_report_court_time_window_without_approving_refunds(): void
    {
        [, $staff, , $booking] = $this->fixture();
        $detail = $booking->bookingDetails()->first();
        $data = ['court_id' => $detail->court_id, 'date' => $detail->booking_date->toDateString(), 'start_time' => '19:00', 'end_time' => '20:00', 'reason_code' => 'COURT_FAILURE', 'reason' => 'Mất điện không thể phục vụ', 'notify_customers' => 1];
        $this->actingAs($staff)->get(route('admin.incidents.bulk'))->assertOk();
        $this->post(route('admin.incidents.bulk.store'), $data)->assertOk();
        $this->post(route('admin.incidents.bulk.store'), $data + ['confirm' => 1, 'preview_token' => session('bulk_incident_preview.token')])->assertSessionHasNoErrors()->assertRedirect(route('employee.incidents.index'));
        $this->assertDatabaseHas('court_incidents', ['source' => 'COURT', 'reported_by' => $staff->id]);
        $this->assertDatabaseHas('incident_resolutions', ['booking_id' => $booking->id, 'status' => 'AWAITING_CHOICE']);
        $this->assertDatabaseCount('refund_requests', 0);
    }

    public function test_timeline_links_refund_progress_notifications_and_actual_cash_out(): void
    {
        $this->travelTo(Carbon::parse('2026-09-08 18:45:00'));
        [$customer,$staff,$admin,$booking] = $this->fixture();
        $this->actingAs($customer)->post(route('incident-tickets.store', $booking), $this->payload($booking))->assertRedirect();
        $ticket = CourtIncident::firstOrFail();
        $this->travelTo(now()->setTime(18, 48));
        $this->actingAs($staff)->post(route('incident-tickets.review', $ticket), ['action' => 'REVIEWING', 'note' => 'Đã tiếp nhận.'])->assertSessionHasNoErrors();
        $this->travelTo(now()->setTime(19, 5));
        $this->post(route('incident-tickets.review', $ticket), ['action' => 'PROPOSE', 'note' => 'Đã kiểm tra sân ngập.', 'proposed_solution' => 'REFUND', 'amount' => 150000])->assertSessionHasNoErrors();
        $this->travelTo(now()->setTime(19, 10));
        $this->actingAs($admin)->post(route('incident-tickets.review', $ticket), ['action' => 'APPROVED', 'note' => 'Xác nhận sự cố.', 'amount' => 150000])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('refund_requests', 1);
        $this->actingAs($customer)->get(route('incident-tickets.show', $ticket))->assertOk()->assertSee('Thông tin nhận hoàn tiền');
        $this->actingAs($admin);
        $refund = RefundRequest::firstOrFail();
        $this->travelTo(now()->setTime(19, 12));
        $this->actingAs($staff)->post(route('special-refunds.processing', $refund), ['refund_method' => 'CASH'])->assertForbidden();
        $this->travelTo(now()->setTime(19, 15));
        $this->actingAs($admin)->post(route('special-refunds.processing', $refund), ['refund_method' => 'CASH'])->assertSessionHasNoErrors();
        $this->travelTo(now()->setTime(19, 16));
        $this->post(route('special-refunds.processing', $refund), ['refund_method' => 'CASH'])->assertSessionHasNoErrors();
        $this->assertSame('19:15', $refund->fresh()->processing_started_at->format('H:i'));
        $this->assertSame(1, Notification::where('unique_key', 'refund-progress:'.$refund->id.':PROCESSING')->count());
        $this->assertSame(0.0, app(RevenueReportService::class)->report()['refund_amount']);
        $this->assertDatabaseCount('refunds', 0);
        $this->travelTo(now()->setTime(19, 30));
        $this->post(route('special-refunds.complete', $refund), ['receipt_image' => $this->receiptImage(), 'refund_code' => 'TIMELINE-OK', 'amount' => 150000])->assertSessionHasNoErrors();
        $this->assertSame(0.0, app(RevenueReportService::class)->report()['net_revenue']);
        $this->assertSame('PAID', $booking->fresh()->payment->status);
        $this->actingAs($customer)->get(route('incident-tickets.show', $ticket))->assertOk()->assertSeeInOrder(['Đã gửi yêu cầu', 'Nhân viên đã tiếp nhận', 'Nhân viên đã xác minh', 'Admin phê duyệt hoàn 150,000đ', 'Đang xử lý hoàn tiền', 'Hoàn tiền thành công: 150,000đ']);
        $this->assertDatabaseHas('user_notifications', ['user_id' => $customer->id, 'unique_key' => 'refund-progress:'.$refund->id.':APPROVED']);
    }

    public function test_unpaid_booking_cannot_create_incident_ticket(): void
    {
        [$customer,,,$booking] = $this->fixture();
        $booking->payment->update(['status' => 'PENDING', 'paid_at' => null]);
        $booking->update(['payment_status' => 'PENDING', 'status' => 'PENDING_PAYMENT']);
        $this->actingAs($customer)->post(route('incident-tickets.store', $booking), $this->payload($booking))->assertSessionHasErrors('description');
        $this->assertDatabaseCount('court_incidents', 0);
        $this->assertDatabaseCount('user_notifications', 0);
        $this->get(route('incident-tickets.create', $booking))->assertOk()->assertDontSee('name="description"', false);
    }
}
