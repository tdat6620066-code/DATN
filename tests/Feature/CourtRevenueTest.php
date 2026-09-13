<?php

namespace Tests\Feature;

use App\Models\{Booking, Court, CourtType, FixedBooking, Payment, Refund, RefundRequest, TimeSlot, User};
use App\Services\RevenueReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourtRevenueTest extends TestCase
{
    use RefreshDatabase;

    private Court $court;
    private TimeSlot $slot;
    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2027-02-01'));
        $this->customer = User::factory()->create();
        $type = CourtType::create(['name' => 'Standard']);
        $this->court = Court::create(['name' => 'Court', 'code' => 'REV', 'court_type_id' => $type->id, 'status' => 'ACTIVE']);
        $this->slot = TimeSlot::create(['name' => 'Evening', 'start_time' => '19:00', 'end_time' => '20:00', 'duration' => 60, 'status' => 'ACTIVE']);
    }

    private function booking(string $date, string $status = 'COMPLETED', ?FixedBooking $group = null): Booking
    {
        $booking = Booking::create(['booking_code' => 'REV-'.str()->random(12), 'user_id' => $this->customer->id,
            'subtotal' => 150000, 'total_amount' => 150000, 'status' => $status, 'payment_status' => 'PAID', 'fixed_booking_id' => $group?->id]);
        $booking->bookingDetails()->create(['court_id' => $this->court->id, 'time_slot_id' => $this->slot->id, 'booking_date' => $date,
            'price' => 150000, 'subtotal' => 150000, 'status' => $status]);
        if (! $group) Payment::create(['booking_id' => $booking->id, 'amount' => 150000, 'status' => 'PAID', 'paid_at' => '2026-09-11 10:00:00']);
        return $booking;
    }

    private function revenue(string $from, ?string $to = null): array
    {
        return app(RevenueReportService::class)->courtRevenue(Carbon::parse($from), Carbon::parse($to ?? $from)->endOfDay());
    }

    public function test_service_date_and_cash_date_are_independent_and_only_completed_slots_earn_revenue(): void
    {
        $booking = $this->booking('2026-12-15', 'CONFIRMED');
        $booking->payment->update(['paid_at' => '2027-01-15 10:00:00']);
        $this->assertSame(0.0, $this->revenue('2026-12-15')['revenue']);
        $booking->update(['status' => 'COMPLETED', 'checked_out_at' => '2026-12-16']);
        $this->assertSame(150000.0, $this->revenue('2026-12-15')['revenue']);
        $this->assertSame(0.0, $this->revenue('2027-01-15')['revenue']);
        $cash = app(RevenueReportService::class)->cashFlow(Carbon::parse('2027-01-15'), Carbon::parse('2027-01-15')->endOfDay());
        $this->assertSame(150000.0, $cash['gross_revenue']);
        $this->assertSame(0.0, app(RevenueReportService::class)->cashFlow(Carbon::parse('2026-12-15'), Carbon::parse('2026-12-15')->endOfDay())['gross_revenue']);
    }

    public function test_one_receipt_for_ten_sessions_is_distributed_and_cancelled_session_is_zero(): void
    {
        $group = FixedBooking::create(['code' => 'FIX-REV', 'user_id' => $this->customer->id, 'confirmation_key' => str()->uuid(), 'definition' => [], 'occurrences' => [], 'status' => 'ACTIVE', 'total_price' => 1500000]);
        $payment = Payment::create(['fixed_booking_id' => $group->id, 'amount' => 1500000, 'status' => 'PAID', 'paid_at' => '2026-09-11 10:00:00']);
        for ($i = 0; $i < 10; $i++) {
            $booking = $this->booking(Carbon::parse('2026-09-15')->addWeeks($i)->toDateString(), $i === 2 ? 'CANCELLED' : 'COMPLETED', $group);
            if ($i === 2) {
                $request = RefundRequest::create(['booking_id' => $booking->id, 'requested_by' => $this->customer->id, 'amount' => 150000, 'reason' => 'Court closed', 'status' => 'APPROVED']);
                Refund::create(['refund_request_id' => $request->id, 'payment_id' => $payment->id, 'amount' => 150000, 'refund_code' => 'REF-REV', 'status' => 'COMPLETED', 'processed_at' => '2026-10-01 10:00:00']);
            }
        }
        $this->assertSame(0.0, $this->revenue('2026-09-11')['revenue']);
        $this->assertSame(150000.0, $this->revenue('2026-09-15')['revenue']);
        $this->assertSame(0.0, $this->revenue('2026-09-29')['revenue']);
        $this->assertSame(1350000.0, $this->revenue('2026-09-01', '2026-12-31')['revenue']);
        $cash = app(RevenueReportService::class)->cashFlow(Carbon::parse('2026-09-11'), Carbon::parse('2026-09-11')->endOfDay());
        $this->assertSame(1500000.0, $cash['gross_revenue']);
        $this->assertSame(0.0, $cash['refund_amount']);
        $this->assertSame(-150000.0, app(RevenueReportService::class)->cashFlow(Carbon::parse('2026-10-01'), Carbon::parse('2026-10-01')->endOfDay())['net_revenue']);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_discount_allocation_uses_all_slots_and_completed_refunds_only(): void
    {
        $booking = $this->booking('2026-09-15');
        $booking->update(['subtotal' => 300000, 'total_amount' => 270000]);
        $booking->bookingDetails()->create(['court_id' => $this->court->id, 'time_slot_id' => $this->slot->id, 'booking_date' => '2026-10-15', 'price' => 150000, 'subtotal' => 150000, 'status' => 'COMPLETED']);
        $this->assertSame(135000.0, $this->revenue('2026-09-15')['revenue']);
        $this->assertSame(270000.0, $this->revenue('2026-09-01', '2026-10-31')['revenue']);
        $request = RefundRequest::create(['booking_id' => $booking->id, 'requested_by' => $this->customer->id, 'amount' => 30000, 'reason' => 'Partial', 'status' => 'APPROVED']);
        $refund = Refund::create(['refund_request_id' => $request->id, 'payment_id' => $booking->payment->id, 'amount' => 30000, 'refund_code' => 'PARTIAL-REV', 'status' => 'PROCESSING']);
        $this->assertSame(135000.0, $this->revenue('2026-09-15')['revenue']);
        $refund->update(['status' => 'COMPLETED', 'processed_at' => '2026-11-01']);
        $this->assertSame(120000.0, $this->revenue('2026-09-15')['revenue']);
        $booking->bookingDetails()->whereDate('booking_date', '2026-10-15')->update(['status' => 'CANCELLED']);
        $this->assertSame(120000.0, $this->revenue('2026-09-01', '2026-10-31')['revenue']);
    }

    public function test_admin_reports_default_to_service_revenue_and_cash_has_separate_route(): void
    {
        $this->booking('2026-09-15');
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $range = ['from' => '2026-09-15', 'to' => '2026-09-15'];
        $this->actingAs($admin)->get(route('admin.reports.index', $range))->assertOk()->assertViewHas('revenue', fn ($r) => $r['revenue'] === 150000.0);
        $this->get(route('admin.reports.cash-flow', $range))->assertOk()->assertViewHas('cash', fn ($r) => $r['gross_revenue'] === 0.0);
        $this->get(route('admin.dashboard', $range))->assertOk()->assertViewHas('kpis', fn ($k) => $k['revenue'] === 150000.0 && $k['gross_revenue'] === 0.0);
        $this->actingAs($this->customer)->get(route('admin.reports.cash-flow'))->assertForbidden();
    }
}
