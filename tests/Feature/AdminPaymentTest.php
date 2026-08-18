<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\RefundRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_search_transaction_and_reconcile(): void
    {
        [$admin,$payment] = $this->data();
        $payment->update(['status' => 'FAILED', 'transaction_id' => 'TX-UC49']);
        $this->actingAs($admin)->get(route('admin.payments.index', ['search' => 'TX-UC49']))->assertOk()->assertSee('TX-UC49');
        $this->actingAs($admin)->put(route('admin.payments.reconcile', $payment), ['action' => 'MARK_PAID', 'amount' => 300000, 'transaction_id' => 'TX-UC49', 'note' => 'Đối chiếu ngân hàng.'])->assertRedirect();
        $this->assertSame('PAID', $payment->fresh()->status);
        $this->assertDatabaseHas('payment_transaction_logs', ['payment_id' => $payment->id, 'action' => 'RECONCILED']);
    }

    public function test_wrong_reconciliation_amount_is_rejected(): void
    {
        [$admin,$payment] = $this->data();
        $this->actingAs($admin)->put(route('admin.payments.reconcile', $payment), ['action' => 'MARK_PAID', 'amount' => 500000, 'note' => 'Sai tiền.'])->assertSessionHas('error');
        $this->assertDatabaseCount('payment_transaction_logs', 0);
    }

    public function test_admin_can_approve_and_complete_refund(): void
    {
        [$admin,$payment,$customer] = $this->data();
        $request = RefundRequest::create(['booking_id' => $payment->booking_id, 'requested_by' => $customer->id, 'amount' => 300000, 'reason' => 'Yêu cầu hoàn hợp lệ.']);
        $this->actingAs($admin)->put(route('admin.payments.refunds.approve', $request), ['note' => 'Đồng ý hoàn.'])->assertRedirect();
        $refund = $request->fresh()->refund;
        $this->actingAs($admin)->put(route('admin.payments.refunds.process', $refund), ['result' => 'COMPLETED', 'note' => 'Ngân hàng xác nhận.'])->assertRedirect();
        $this->assertSame('COMPLETED', $refund->fresh()->status);
        $this->assertSame('REFUNDED', $payment->fresh()->status);
        $this->assertDatabaseHas('payment_transaction_logs', ['payment_id' => $payment->id, 'action' => 'REFUND_COMPLETED']);
    }

    public function test_refund_cannot_exceed_paid_amount_or_be_processed_twice(): void
    {
        [$admin,$payment,$customer] = $this->data();
        $request = RefundRequest::create(['booking_id' => $payment->booking_id, 'requested_by' => $customer->id, 'amount' => 400000, 'reason' => 'Quá số tiền.', 'status' => 'APPROVED']);
        $refund = Refund::create(['refund_request_id' => $request->id, 'payment_id' => $payment->id, 'refund_code' => 'RF-OVER', 'amount' => 400000, 'status' => 'PROCESSING']);
        $this->actingAs($admin)->put(route('admin.payments.refunds.process', $refund), ['result' => 'COMPLETED', 'note' => 'Thử hoàn.'])->assertSessionHas('error');
        $refund->update(['amount' => 300000]);
        $this->actingAs($admin)->put(route('admin.payments.refunds.process', $refund), ['result' => 'COMPLETED', 'note' => 'Hoàn đúng.'])->assertRedirect();
        $this->actingAs($admin)->put(route('admin.payments.refunds.process', $refund), ['result' => 'COMPLETED', 'note' => 'Hoàn lần hai.'])->assertSessionHas('error');
    }

    private function data(): array
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $customer = User::factory()->create(['role' => 'CUSTOMER']);
        $booking = Booking::create(['booking_code' => 'PAY-'.uniqid(), 'user_id' => $customer->id, 'total_amount' => 300000, 'status' => 'CONFIRMED', 'payment_status' => 'PAID']);
        $payment = Payment::create(['booking_id' => $booking->id, 'amount' => 300000, 'status' => 'PAID', 'paid_at' => now()]);

        return [$admin, $payment, $customer];
    }
}
