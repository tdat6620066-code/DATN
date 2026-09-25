<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\RefundRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpecialRefundTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Concerns\CreatesReceiptImages;

    private function fixture(): array
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $staff = User::factory()->create(['role' => 'EMPLOYEE', 'permissions' => ['incidents.manage', 'bookings.view', 'payments.counter']]);
        $customer = User::factory()->create(['role' => 'CUSTOMER']);
        $booking = Booking::create(['booking_code' => 'REF-'.uniqid(), 'user_id' => $customer->id, 'total_amount' => 300000, 'status' => 'CONFIRMED', 'payment_status' => 'PAID']);
        $payment = Payment::create(['booking_id' => $booking->id, 'amount' => 300000, 'status' => 'PAID', 'paid_at' => now()]);

        return [$admin, $staff, $customer, $booking, $payment];
    }

    private function payload(int $amount = 150000): array
    {
        return ['reason_code' => 'SERVICE_INTERRUPTED', 'reason' => 'Mất điện', 'supporting_information' => 'Đã chơi 60/120 phút; 300000 × 60/120.', 'amount' => $amount];
    }

    public function test_checkin_blocks_creation_approval_and_payout_even_if_status_changes(): void
    {
        [$admin, , , $booking] = $this->fixture();
        $this->actingAs($admin);
        $this->post(route('special-refunds.store', $booking), $this->payload())->assertSessionHasNoErrors();
        $item = RefundRequest::firstOrFail();
        $this->assertSame('300000.00', $item->amount);
        $booking->update(['checked_in_at' => now(), 'status' => 'CHECKED_IN']);
        $this->post(route('special-refunds.review', $item), ['decision' => 'APPROVED', 'decision_note' => 'Test'])->assertSessionHasErrors('amount');
        $item->update(['status' => 'APPROVED']);
        $this->post(route('special-refunds.processing', $item), ['refund_method' => 'CASH'])->assertSessionHasErrors('amount');
        $this->post(route('special-refunds.complete', $item), ['amount' => 300000, 'receipt_image' => $this->receiptImage(), 'refund_code' => 'BLOCKED'])->assertSessionHasErrors('amount');
        $booking->update(['status' => 'CONFIRMED']);
        $this->post(route('special-refunds.store', $booking), $this->payload())->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('refunds', 0);
    }

    public function test_staff_request_admin_review_and_manual_full_refund(): void
    {
        [$admin, $staff, , $booking, $payment] = $this->fixture();
        $this->actingAs($staff)->get(route('employee.bookings.show', $booking))->assertOk()->assertSee('Hoàn tiền đặc biệt');
        $this->post(route('special-refunds.store', $booking), $this->payload())->assertSessionHasNoErrors();
        $item = RefundRequest::firstOrFail();
        $this->assertSame('PAID', $payment->fresh()->status);
        $this->post(route('special-refunds.review', $item), ['decision' => 'APPROVED', 'decision_note' => 'Xác nhận mất điện'])->assertForbidden();
        $this->actingAs($admin)->post(route('special-refunds.complete', $item), ['amount' => 150000, 'receipt_image' => $this->receiptImage(), 'refund_code' => 'RF-1'])->assertSessionHasErrors();
        $this->post(route('special-refunds.review', $item), ['decision' => 'APPROVED', 'decision_note' => 'Xác nhận mất điện'])->assertSessionHasNoErrors();
        $this->assertSame('CONFIRMED', $booking->fresh()->status);
        $this->post(route('special-refunds.complete', $item), ['amount' => 150000, 'receipt_image' => $this->receiptImage(), 'refund_code' => 'RF-1'])->assertSessionHasErrors('amount');
        $this->post(route('special-refunds.processing', $item), ['refund_method' => 'CASH'])->assertSessionHasNoErrors();
        $this->post(route('special-refunds.complete', $item), ['amount' => 300000, 'receipt_image' => $this->receiptImage(), 'refund_code' => 'RF-1'])->assertSessionHasNoErrors();
        $this->assertSame('CANCELLED', $booking->fresh()->status);
        $this->assertSame('PAID', $payment->fresh()->status);
        $this->assertSame('REFUNDED', $payment->fresh()->refund_status);
        $this->assertDatabaseHas('refunds', ['amount' => 300000, 'status' => 'COMPLETED']);
        $this->post(route('special-refunds.complete', $item), ['amount' => 150000, 'receipt_image' => $this->receiptImage(), 'refund_code' => 'RF-2'])->assertSessionHasErrors();
        $this->assertDatabaseCount('refunds', 1);
        $this->actingAs($staff)->post(route('employee.bookings.payment', $booking), ['amount' => 300000, 'payment_method' => 'CASH'])->assertSessionHas('error');
        $this->assertSame('PAID', $payment->fresh()->status);
    }

    public function test_customer_is_blocked_and_amount_and_duplicate_requests_are_checked(): void
    {
        [$admin, , $customer, $booking] = $this->fixture();
        $this->actingAs($customer)->post(route('special-refunds.store', $booking), $this->payload())->assertForbidden();
        $this->post(route('bookings.cancel', $booking))->assertForbidden();
        $this->actingAs($admin)->post(route('special-refunds.store', $booking), $this->payload(300001))->assertSessionHasErrors('amount');
        $this->post(route('special-refunds.store', $booking), $this->payload(300000))->assertSessionHasNoErrors();
        $this->post(route('special-refunds.store', $booking), $this->payload())->assertSessionHasErrors();
        $this->assertDatabaseCount('refund_requests', 1);
    }
}
