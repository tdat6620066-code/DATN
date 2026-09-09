<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Court;
use App\Models\CourtType;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\RefundRequest;
use App\Models\TimeSlot;
use App\Models\User;
use App\Services\OperationsReportService;
use App\Services\RevenueReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OperationsReportTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $customer = User::factory()->create(['role' => 'CUSTOMER']);
        $type = CourtType::create(['name' => 'Standard']);
        $court = Court::create(['code' => 'RP-A', 'name' => 'Court A', 'court_type_id' => $type->id, 'status' => 'ACTIVE']);
        $slot = TimeSlot::create(['name' => 'Evening', 'start_time' => '19:00:00', 'end_time' => '20:00:00', 'duration' => 60, 'status' => 'ACTIVE']);
        $booking = Booking::create(['booking_code' => 'REPORT', 'user_id' => $customer->id, 'subtotal' => 300000, 'total_amount' => 300000, 'status' => 'CONFIRMED', 'payment_status' => 'PAID']);
        $booking->bookingDetails()->create(['court_id' => $court->id, 'time_slot_id' => $slot->id, 'booking_date' => '2026-09-10', 'price' => 300000, 'subtotal' => 300000, 'status' => 'CONFIRMED']);
        $payment = Payment::create(['booking_id' => $booking->id, 'amount' => 300000, 'status' => 'PAID', 'paid_at' => '2026-09-05 10:00:00']);

        return [$admin, $customer, $booking, $payment];
    }

    public function test_use_date_report_is_separate_from_cash_flow(): void
    {
        [$admin, $customer] = $this->fixture();
        $operations = app(OperationsReportService::class);
        $this->assertSame(0, $operations->bookings(Carbon::parse('2026-09-05'), Carbon::parse('2026-09-05')->endOfDay())['total']);
        $this->assertSame(1, $operations->bookings(Carbon::parse('2026-09-10'), Carbon::parse('2026-09-10')->endOfDay())['total']);
        $this->assertSame(300000.0, app(RevenueReportService::class)->report(Carbon::parse('2026-09-05'), Carbon::parse('2026-09-05')->endOfDay())['gross_revenue']);
        $this->actingAs($customer)->get(route('admin.reports.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.reports.index', ['from' => '2026-09-01', 'to' => '2026-09-30']))->assertOk()->assertSee('Booking theo ngày sử dụng sân');
    }

    public function test_refund_report_separates_request_cohort_and_successful_cash_out(): void
    {
        [$admin, , $booking, $payment] = $this->fixture();
        foreach (['PENDING', 'REJECTED', 'APPROVED'] as $status) {
            $request = RefundRequest::create(['booking_id' => $booking->id, 'requested_by' => $admin->id, 'reason' => 'Weather', 'reason_code' => 'WEATHER', 'amount' => 150000, 'status' => $status]);
            $request->forceFill(['created_at' => '2026-09-01'])->save();
            if ($status === 'APPROVED') {
                Refund::create(['refund_request_id' => $request->id, 'payment_id' => $payment->id, 'refund_code' => 'R1', 'amount' => 150000, 'status' => 'COMPLETED', 'processed_at' => '2026-09-06 10:00:00']);
            }
        }
        $report = app(OperationsReportService::class)->refunds(Carbon::parse('2026-09-01'), Carbon::parse('2026-09-30')->endOfDay());
        $this->assertSame(3, $report['requests']);
        $this->assertSame(1, $report['completed']);
        $this->assertSame(1, $report['processing']);
        $this->assertSame(1, $report['rejected']);
        $this->assertSame(150000.0, $report['amount']);
        $this->assertSame(1, $report['partial']);
        $this->assertSame(0, $report['full']);
        $this->assertSame(150000.0, $report['reasons']['WEATHER']['amount']);
        $this->assertSame(150000.0, $report['courts']->first()['amount']);
        $later = RefundRequest::create(['booking_id' => $booking->id, 'requested_by' => $admin->id, 'amount' => 150000, 'reason' => 'Remaining refund', 'status' => 'APPROVED']);
        Refund::create(['refund_request_id' => $later->id, 'payment_id' => $payment->id, 'refund_code' => 'R2', 'amount' => 150000, 'status' => 'COMPLETED', 'processed_at' => '2026-10-01 10:00:00']);
        $september = app(OperationsReportService::class)->refunds(Carbon::parse('2026-09-01'), Carbon::parse('2026-09-30')->endOfDay());
        $october = app(OperationsReportService::class)->refunds(Carbon::parse('2026-10-01'), Carbon::parse('2026-10-31')->endOfDay());
        $this->assertSame(1, $september['partial']);
        $this->assertSame(0, $september['full']);
        $this->assertSame(1, $october['full']);
        $this->assertSame(150000.0, $october['amount']);
    }

    public function test_migration_preserves_receipt_and_backfills_separate_refund_state(): void
    {
        [$admin, , $booking, $payment] = $this->fixture();
        $request = RefundRequest::create(['booking_id' => $booking->id, 'requested_by' => $admin->id, 'reason' => 'Weather', 'amount' => 300000, 'status' => 'APPROVED']);
        Refund::create(['refund_request_id' => $request->id, 'payment_id' => $payment->id, 'refund_code' => 'LEGACY', 'amount' => 300000, 'status' => 'COMPLETED', 'processed_at' => '2026-09-06 10:00:00']);
        $migration = require database_path('migrations/2026_09_07_010000_separate_payment_refund_state.php');
        $migration->down();
        DB::table('payments')->where('id', $payment->id)->update(['status' => 'REFUNDED']);
        $migration->up();
        $this->assertSame('PAID', $payment->fresh()->status);
        $this->assertSame('REFUNDED', $payment->fresh()->refund_status);
        $this->assertSame('300000.00', $payment->fresh()->refunded_amount);
        $this->assertSame('300000.00', $payment->fresh()->amount);
        $this->assertSame('2026-09-05', $payment->fresh()->paid_at->toDateString());
        $this->assertSame(0.0, app(RevenueReportService::class)->report()['net_revenue']);
    }
}
