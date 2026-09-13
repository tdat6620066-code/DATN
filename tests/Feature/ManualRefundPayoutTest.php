<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\RefundRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ManualRefundPayoutTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Concerns\CreatesReceiptImages;

    public function test_private_recipient_and_finance_only_manual_payout(): void
    {
        $customer = User::factory()->create(['role' => 'CUSTOMER']);
        $other = User::factory()->create(['role' => 'CUSTOMER']);
        $staff = User::factory()->create(['role' => 'EMPLOYEE', 'permissions' => ['incidents.manage', 'bookings.view']]);
        $finance = User::factory()->create(['role' => 'EMPLOYEE', 'permissions' => ['refunds.process']]);
        $booking = Booking::create(['booking_code' => 'MANUAL-1', 'user_id' => $customer->id, 'total_amount' => 200000, 'status' => 'CONFIRMED', 'payment_status' => 'PAID']);
        $payment = Payment::create(['booking_id' => $booking->id, 'amount' => 200000, 'status' => 'PAID', 'paid_at' => now()]);
        $item = RefundRequest::create(['booking_id' => $booking->id, 'requested_by' => $staff->id, 'reason_code' => 'COURT_FAILURE', 'reason' => 'Court failure', 'amount' => 200000, 'status' => 'APPROVED']);
        $bank = ['bank_name' => 'Test Bank', 'bank_account_number' => '001234567890', 'bank_account_holder' => 'TEST CUSTOMER', 'recipient_confirmed' => 1];
        $this->actingAs($other)->post(route('refund-recipient.update', $item), $bank)->assertForbidden();
        $this->actingAs($customer)->post(route('refund-recipient.update', $item), array_diff_key($bank, ['recipient_confirmed' => true]))->assertSessionHasErrors('recipient_confirmed');
        $this->assertDatabaseCount('refund_bank_accounts', 0);
        $this->post(route('refund-recipient.update', $item), $bank)->assertSessionHasNoErrors();
        $this->assertNotSame($bank['bank_account_number'], DB::table('refund_bank_accounts')->where('refund_request_id', $item->id)->value('account_number'));
        $this->assertArrayNotHasKey('bank_account_number', $item->fresh()->toArray());
        $this->post(route('refund-recipient.update', $item), array_merge($bank, ['bank_name' => '']))->assertSessionHasErrors('bank_name')->assertSessionMissing('_old_input.bank_account_number');
        $this->actingAs($staff)->get(route('refund-payouts.show', $item))->assertForbidden();
        $this->post(route('special-refunds.processing', $item), ['refund_method' => 'CASH'])->assertForbidden();
        $this->post(route('special-refunds.complete', $item), ['amount' => 200000, 'receipt_image' => $this->receiptImage(), 'refund_code' => 'MANUAL-OK'])->assertForbidden();
        $this->get(route('employee.bookings.show', $booking))->assertOk()->assertDontSee($bank['bank_account_number'])->assertDontSee($bank['bank_account_holder']);
        $this->actingAs($finance)->get(route('refund-payouts.index'))->assertOk();
        $this->get(route('refund-payouts.show', $item))->assertOk()->assertSee($bank['bank_account_number']);
        $this->post(route('special-refunds.review', $item), ['decision' => 'APPROVED', 'decision_note' => 'OK'])->assertForbidden();
        $this->post(route('special-refunds.complete', $item), ['amount' => 200000, 'receipt_image' => $this->receiptImage(), 'refund_code' => 'MANUAL-OK'])->assertSessionHasErrors();
        $this->assertDatabaseCount('refunds', 0);
        $this->post(route('special-refunds.processing', $item), ['refund_method' => 'BANK_TRANSFER'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('refunds', 0);
        $this->assertEquals(0, $payment->fresh()->refunded_amount);
        $this->post(route('special-refunds.processing', $item), ['refund_method' => 'CASH'])->assertSessionHasErrors('refund_method');
        $this->actingAs($customer)->post(route('refund-recipient.update', $item), $bank)->assertSessionHasErrors()->assertSessionMissing('_old_input.bank_account_number');
        $this->actingAs($finance)->post(route('special-refunds.complete', $item), ['amount' => 200000, 'receipt_image' => $this->receiptImage(), 'refund_code' => 'MANUAL-OK', 'processing_note' => 'Transferred successfully'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('refunds', ['refund_request_id' => $item->id, 'status' => 'COMPLETED', 'refund_method' => 'BANK_TRANSFER', 'processed_by' => $finance->id, 'amount' => 200000]);
        $this->assertNotNull($item->fresh()->refund->processed_at);
        $this->assertSame('Transferred successfully', $item->fresh()->refund->processing_note);
        $this->assertSame('REFUNDED', $item->fresh()->payout_status);
        $this->assertSame('PAID', $payment->fresh()->status);
        $this->assertSame('REFUNDED', $payment->fresh()->refund_status);
        $receipt = $item->fresh()->refund;
        \Illuminate\Support\Facades\Storage::disk('local')->assertExists($receipt->receipt_path);
        $this->get(route('refund-payouts.show', $item))->assertOk()->assertSee('Ảnh xác nhận chi trả thành công');
        $this->get(route('refund-payouts.receipt', $receipt))->assertOk()->assertHeader('Content-Type', 'image/png')
            ->assertHeader('Content-Disposition', 'inline; filename=refund-'.$receipt->id.'.png');
        $this->actingAs($customer)->get(route('refund-payouts.receipt', $receipt))->assertOk();
        $this->actingAs($other)->get(route('refund-payouts.receipt', $receipt))->assertForbidden();
        $unprivileged = User::factory()->create(['role' => 'EMPLOYEE', 'permissions' => []]);
        $this->actingAs($unprivileged)->get(route('refund-payouts.receipt', $receipt))->assertForbidden();
        $this->actingAs($finance);
        $this->post(route('special-refunds.complete', $item), ['amount' => 200000, 'receipt_image' => $this->receiptImage(), 'refund_code' => 'DUPLICATE'])->assertSessionHasErrors();
        $this->assertDatabaseCount('refunds', 1);
    }

    public function test_transfer_requires_recipient_but_cash_does_not(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $booking = Booking::create(['booking_code' => 'CASH-1', 'user_id' => $admin->id, 'total_amount' => 200000, 'status' => 'CONFIRMED', 'payment_status' => 'PAID']);
        Payment::create(['booking_id' => $booking->id, 'amount' => 200000, 'status' => 'PAID', 'paid_at' => now()]);
        $item = RefundRequest::create(['booking_id' => $booking->id, 'requested_by' => $admin->id, 'reason_code' => 'COURT_FAILURE', 'reason' => 'Failure', 'amount' => 200000, 'status' => 'APPROVED']);
        $this->actingAs($admin)->post(route('special-refunds.processing', $item), ['refund_method' => 'BANK_TRANSFER'])->assertSessionHasErrors('refund_method');
        $this->assertNull($item->fresh()->processing_started_at);
        $this->post(route('special-refunds.processing', $item), ['refund_method' => 'CASH'])->assertSessionHasNoErrors();
        $this->post(route('special-refunds.complete', $item), ['amount' => 200000, 'receipt_image' => $this->receiptImage(), 'refund_code' => 'CASH-RECEIPT'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('refunds', ['refund_method' => 'CASH', 'processed_by' => $admin->id]);
    }

    public function test_stale_bank_requires_owner_confirmation_and_processing_locks_it(): void
    {
        $customer = User::factory()->create();
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $booking = Booking::create(['booking_code' => 'STALE-BANK', 'user_id' => $customer->id, 'total_amount' => 200000, 'status' => 'CONFIRMED', 'payment_status' => 'PAID']);
        Payment::create(['booking_id' => $booking->id, 'amount' => 200000, 'status' => 'PAID', 'paid_at' => now()]);
        $item = RefundRequest::create(['booking_id' => $booking->id, 'requested_by' => $customer->id, 'reason_code' => 'COURT_FAILURE', 'reason' => 'Failure', 'amount' => 200000, 'status' => 'APPROVED']);
        $bank = ['bank_name' => 'MB Bank', 'bank_account_number' => '001234567890', 'bank_account_holder' => 'TEST CUSTOMER', 'recipient_confirmed' => 1];
        $this->actingAs($customer)->post(route('refund-recipient.update', $item), $bank)->assertSessionHasNoErrors();
        $this->travel(24)->hours();
        $this->assertSame('WAITING_BANK_CONFIRMATION', $item->fresh()->payout_status);
        $this->actingAs($admin)->post(route('special-refunds.processing', $item), ['refund_method' => 'BANK_TRANSFER'])->assertSessionHasErrors('refund_method');
        $confirmation = ['recipient_confirmed' => 1, 'account_version' => hash('sha256', $item->fresh()->bankAccount->getRawOriginal('account_number'))];
        $this->actingAs(User::factory()->create())->post(route('refund-recipient.confirm', $item), $confirmation)->assertForbidden();
        $this->actingAs($customer)->post(route('refund-recipient.confirm', $item), array_replace($confirmation, ['account_version' => 'old']))->assertSessionHasErrors('recipient_confirmed');
        $this->post(route('refund-recipient.confirm', $item), $confirmation)->assertSessionHasNoErrors();
        $this->assertSame('APPROVED', $item->fresh()->payout_status);
        $this->actingAs($admin)->post(route('special-refunds.processing', $item), ['refund_method' => 'BANK_TRANSFER'])->assertSessionHasNoErrors();
        $this->assertSame('PROCESSING', $item->fresh()->payout_status);
        $this->actingAs($customer)->post(route('refund-recipient.update', $item), $bank)->assertSessionHasErrors();
        $this->post(route('refund-recipient.confirm', $item), $confirmation)->assertSessionHasErrors('recipient_confirmed');
    }

    public function test_receipt_image_is_required_and_invalid_uploads_do_not_complete_payout(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $booking = Booking::create(['booking_code' => 'PROOF-1', 'user_id' => $admin->id, 'total_amount' => 200000, 'status' => 'CONFIRMED', 'payment_status' => 'PAID']);
        $payment = Payment::create(['booking_id' => $booking->id, 'amount' => 200000, 'status' => 'PAID', 'paid_at' => now()]);
        $item = RefundRequest::create(['booking_id' => $booking->id, 'requested_by' => $admin->id, 'reason_code' => 'COURT_FAILURE', 'reason' => 'Failure', 'amount' => 200000, 'status' => 'APPROVED']);
        $this->actingAs($admin)->post(route('special-refunds.processing', $item), ['refund_method' => 'CASH'])->assertSessionHasNoErrors();
        $payload = ['amount' => 200000, 'refund_code' => 'PROOF-REFUND'];
        $this->post(route('special-refunds.complete', $item), $payload)->assertSessionHasErrors('receipt_image');
        $this->post(route('special-refunds.complete', $item), $payload + ['receipt_image' => \Illuminate\Http\UploadedFile::fake()->create('bad.php', 1, 'application/x-php')])->assertSessionHasErrors('receipt_image');
        $this->assertDatabaseCount('refunds', 0);
        $this->assertSame('CONFIRMED', $booking->fresh()->status);
        $this->assertEquals(0, $payment->fresh()->refunded_amount);
        $this->assertCount(0, \Illuminate\Support\Facades\Storage::disk('local')->allFiles('refund-receipts'));
    }
}
