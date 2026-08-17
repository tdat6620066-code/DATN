<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\RefundRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RefundRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_can_approve_request_within_limit(): void
    {
        [$employee, $request] = $this->makeRequest(100000, 200000);

        $this->actingAs($employee)->postJson(route('employee.refund-requests.review', $request), [
            'decision' => 'APPROVED', 'decision_note' => 'Đủ điều kiện theo chính sách.',
        ])->assertOk()->assertJsonPath('data.status', 'APPROVED');

        $this->assertDatabaseHas('refunds', ['refund_request_id' => $request->id, 'status' => 'PROCESSING']);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $request->requested_by]);
    }

    public function test_employee_cannot_approve_above_limit(): void
    {
        [$employee, $request] = $this->makeRequest(300000, 200000);

        $this->actingAs($employee)->postJson(route('employee.refund-requests.review', $request), [
            'decision' => 'APPROVED', 'decision_note' => 'Duyệt.',
        ])->assertForbidden();
        $this->assertSame('PENDING', $request->fresh()->status);
    }

    public function test_missing_information_can_be_requested(): void
    {
        [$employee, $request] = $this->makeRequest(100000, 200000);

        $this->actingAs($employee)->postJson(route('employee.refund-requests.review', $request), [
            'decision' => 'NEEDS_INFO', 'requested_information' => 'Vui lòng gửi biên lai thanh toán.',
        ])->assertOk()->assertJsonPath('data.status', 'NEEDS_INFO');
    }

    private function makeRequest(float $amount, float $limit): array
    {
        $employee = User::factory()->create(['role' => 'EMPLOYEE', 'refund_approval_limit' => $limit]);
        $customer = User::factory()->create();
        $booking = Booking::create([
            'booking_code' => 'RF-'.uniqid(), 'user_id' => $customer->id,
            'total_amount' => 500000, 'status' => 'CONFIRMED', 'payment_status' => 'PAID',
        ]);
        Payment::create(['booking_id' => $booking->id, 'amount' => 500000, 'status' => 'PAID', 'paid_at' => now()]);
        $request = RefundRequest::create([
            'booking_id' => $booking->id, 'requested_by' => $customer->id,
            'amount' => $amount, 'reason' => 'Khách hàng không thể sử dụng sân.',
        ]);

        return [$employee, $request];
    }
}
