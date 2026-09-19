<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\ChatbotLog;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\RefundRequest;
use App\Models\User;
use App\Services\RevenueReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RevenueReportTest extends TestCase
{
    use RefreshDatabase;

    private function payment(int $amount, string $status = 'PAID', string $date = '2026-09-01 10:00:00'): Payment
    {
        $user = User::factory()->create();
        $booking = Booking::create(['booking_code' => 'REV-'.uniqid(), 'user_id' => $user->id, 'subtotal' => $amount, 'total_amount' => $amount, 'status' => $status === 'REFUNDED' ? 'CANCELLED' : 'CONFIRMED', 'payment_status' => $status]);

        return Payment::create(['booking_id' => $booking->id, 'amount' => $amount, 'status' => in_array($status, ['REFUNDED', 'PARTIALLY_REFUNDED']) ? 'PAID' : $status, 'refund_status' => in_array($status, ['REFUNDED', 'PARTIALLY_REFUNDED']) ? $status : 'NONE', 'paid_at' => $date]);
    }

    private function refund(Payment $payment, int $amount, string $status = 'COMPLETED', string $date = '2026-09-02 10:00:00'): Refund
    {
        $request = RefundRequest::create(['booking_id' => $payment->booking_id, 'requested_by' => $payment->booking->user_id, 'amount' => $amount, 'reason' => 'Weather', 'status' => 'APPROVED']);

        return Refund::create(['refund_request_id' => $request->id, 'payment_id' => $payment->id, 'amount' => $amount, 'refund_code' => 'RF-'.uniqid(), 'status' => $status, 'processed_at' => $date]);
    }

    public function test_dashboard_matches_example_and_preserves_gross_receipts(): void
    {
        $this->payment(150000);
        $full = $this->payment(200000, 'REFUNDED');
        $this->refund($full, 200000);
        $partial = $this->payment(300000, 'PARTIALLY_REFUNDED');
        $this->refund($partial, 150000);
        $this->payment(180000);
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $this->actingAs($admin)->get(route('admin.dashboard', ['from' => '2026-09-01', 'to' => '2026-09-02']))->assertOk()
            ->assertViewHas('kpis', fn ($k) => $k['gross_revenue'] === 830000.0 && $k['refund_amount'] === 350000.0 && $k['net_revenue'] === 480000.0)
            ->assertViewHas('chart', fn ($c) => $c['gross_revenue']->all() === [830000.0, 0.0] && $c['refund_amount']->all() === [0.0, 350000.0] && $c['net_cash']->all() === [830000.0, -350000.0] && $c['revenue']->all() === [830000.0, 0.0]);
        $this->assertDatabaseCount('payments', 4);
        $this->assertDatabaseCount('bookings', 4);
        $this->assertSame('200000.00', $full->fresh()->amount);
        $this->assertSame('2026-09-01', $full->fresh()->paid_at->toDateString());
    }

    public function test_only_completed_refunds_reduce_revenue_even_with_multiple_refunds(): void
    {
        $payment = $this->payment(300000);
        foreach (['PENDING', 'APPROVED'] as $status) {
            RefundRequest::create(['booking_id' => $payment->booking_id, 'requested_by' => $payment->booking->user_id, 'amount' => 5000, 'reason' => 'Test', 'status' => $status]);
        }
        $this->refund($payment, 10000, 'PROCESSING');
        $this->refund($payment, 10000, 'FAILED');
        $this->refund($payment, 50000);
        $this->refund($payment, 100000);
        $this->payment(900000, 'FAILED');
        $this->payment(900000, 'PENDING');
        $report = app(RevenueReportService::class)->report();
        $this->assertSame(300000.0, $report['gross_revenue']);
        $this->assertSame(150000.0, $report['refund_amount']);
        $this->assertSame(150000.0, $report['net_revenue']);
    }

    public function test_refund_in_later_month_is_recognized_on_refund_date(): void
    {
        $payment = $this->payment(300000, 'REFUNDED', '2026-08-31 23:59:59');
        $this->refund($payment, 300000, 'COMPLETED', '2026-09-01 00:00:00');
        $service = app(RevenueReportService::class);
        $august = $service->report(Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31')->endOfDay());
        $september = $service->report(Carbon::parse('2026-09-01'), Carbon::parse('2026-09-30')->endOfDay());
        $this->assertSame(300000.0, $august['net_revenue']);
        $this->assertSame(0.0, $september['gross_revenue']);
        $this->assertSame(-300000.0, $september['net_revenue']);
        $this->assertSame(0.0, $service->report()['net_revenue']);
    }

    public function test_booking_scope_and_completion_date_are_independent_of_booking_creation(): void
    {
        $payment = $this->payment(300000);
        $this->refund($payment, 150000);
        $this->payment(900000);
        $report = app(RevenueReportService::class)->report(bookingIds: collect([$payment->booking_id]));
        $this->assertSame(150000.0, $report['net_revenue']);
        $this->assertSame(0.0, app(RevenueReportService::class)->report(bookingIds: collect())['net_revenue']);
        $payment->booking->forceFill(['status' => 'COMPLETED', 'created_at' => '2026-08-01', 'checked_out_at' => '2026-09-02 20:00:00'])->save();
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $this->actingAs($admin)->get(route('admin.dashboard', ['from' => '2026-09-01', 'to' => '2026-09-02']))->assertOk()->assertViewHas('kpis', fn ($k) => $k['completed'] === 1);
    }

    public function test_chatbot_report_includes_refunds_for_older_chatbot_bookings(): void
    {
        $this->travelTo(Carbon::parse('2026-09-15 12:00:00'));
        $payment = $this->payment(300000, 'REFUNDED', '2026-07-01 10:00:00');
        $this->refund($payment, 300000);
        $log = ChatbotLog::create(['question' => 'Booking', 'answer' => 'Created', 'engine' => 'RULE_BASED', 'intent' => 'BOOKING', 'status' => 'SUCCESS', 'metadata' => ['booking_id' => $payment->booking_id]]);
        $log->forceFill(['created_at' => '2026-07-01 10:00:00'])->save();
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $this->actingAs($admin)->get(route('admin.chatbot-analytics', ['days' => 30]))->assertOk()
            ->assertViewHas('summary', fn ($s) => $s['chatbot_gross_revenue'] === 0.0 && $s['chatbot_refund_amount'] === 300000.0 && $s['chatbot_revenue'] === -300000.0);
    }
}
