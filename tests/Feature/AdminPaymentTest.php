<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_search_transaction_and_reconcile(): void
    {
        [$admin, $payment] = $this->data();
        $payment->update(['status' => 'FAILED', 'transaction_id' => 'TX-UC49']);
        $this->actingAs($admin)->get(route('admin.payments.index', ['search' => 'TX-UC49']))->assertOk()->assertSee('TX-UC49');
        $this->actingAs($admin)->put(route('admin.payments.reconcile', $payment), ['action' => 'MARK_PAID', 'amount' => 300000, 'transaction_id' => 'TX-UC49', 'note' => 'Đối chiếu ngân hàng.'])->assertRedirect();
        $this->assertSame('PAID', $payment->fresh()->status);
        $this->assertDatabaseHas('payment_transaction_logs', ['payment_id' => $payment->id, 'action' => 'RECONCILED']);
    }

    public function test_wrong_reconciliation_amount_is_rejected(): void
    {
        [$admin, $payment] = $this->data();
        $this->actingAs($admin)->put(route('admin.payments.reconcile', $payment), ['action' => 'MARK_PAID', 'amount' => 500000, 'note' => 'Sai tiền.'])->assertSessionHas('error');
        $this->assertDatabaseCount('payment_transaction_logs', 0);
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
