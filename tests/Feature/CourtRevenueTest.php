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

    public function test_revenue_uses_payment_date_even_before_play_and_does_not_move_at_checkout(): void
    {
        $booking = $this->booking('2027-03-15', 'CONFIRMED');
        $booking->payment->update(['paid_at' => '2027-01-15 10:00:00']);
        $this->assertSame(150000.0, $this->revenue('2027-01-15')['revenue']);
        $this->assertSame(0.0, $this->revenue('2027-03-15')['revenue']);
        $booking->update(['status' => 'COMPLETED']);
        $this->assertSame(150000.0, $this->revenue('2027-01-15')['revenue']);
        $this->assertSame(0.0, $this->revenue('2027-03-15')['revenue']);
    }

    public function test_fixed_receipt_is_counted_once_and_refunds_do_not_rewrite_payment_day(): void
    {
        $group = FixedBooking::create(['code'=>'FIX-REV','user_id'=>$this->customer->id,'confirmation_key'=>str()->uuid(),'definition'=>[],'occurrences'=>[],'status'=>'ACTIVE','total_price'=>1500000]);
        $payment = Payment::create(['fixed_booking_id'=>$group->id,'amount'=>1500000,'status'=>'PAID','paid_at'=>'2026-09-11 10:00:00']);
        for ($i=0;$i<10;$i++) $booking=$this->booking(Carbon::parse('2026-09-15')->addWeeks($i)->toDateString(),'CONFIRMED',$group);
        $booking->update(['status'=>'CANCELLED']);
        $booking->bookingDetails()->update(['status'=>'CANCELLED']);
        $request=RefundRequest::create(['booking_id'=>$booking->id,'requested_by'=>$this->customer->id,'amount'=>150000,'reason'=>'Court closed','status'=>'APPROVED']);
        Refund::create(['refund_request_id'=>$request->id,'payment_id'=>$payment->id,'amount'=>150000,'refund_code'=>'REF-REV','status'=>'COMPLETED','processed_at'=>'2026-10-01 10:00:00']);
        $report=$this->revenue('2026-09-11');
        $this->assertSame(1500000.0,$report['revenue']);
        $this->assertSame(10,$report['slots']);
        $this->assertSame(1500000.0,$report['courts']->sum('amount'));
        $this->assertSame(0.0,$this->revenue('2026-09-15')['revenue']);
        $this->assertSame(-150000.0,app(RevenueReportService::class)->cashFlow(Carbon::parse('2026-10-01'),Carbon::parse('2026-10-01')->endOfDay())['net_revenue']);
        $this->assertDatabaseCount('payments',1);
    }

    public function test_receipt_amount_after_discount_is_allocated_across_all_slots(): void
    {
        $booking=$this->booking('2026-09-15');
        $booking->update(['subtotal'=>300000,'total_amount'=>270000]);
        $booking->payment->update(['amount'=>270000]);
        $booking->bookingDetails()->create(['court_id'=>$this->court->id,'time_slot_id'=>$this->slot->id,'booking_date'=>'2026-10-15','price'=>150000,'subtotal'=>150000,'status'=>'CONFIRMED']);
        $report=$this->revenue('2026-09-11');
        $this->assertSame(270000.0,$report['revenue']);
        $this->assertSame(270000.0,$report['daily']['2026-09-11']);
        $this->assertSame(270000.0,$report['courts']->sum('amount'));
        $this->assertSame(2,$report['slots']);
        $this->assertSame(0.0,$this->revenue('2026-09-15')['revenue']);
    }

    public function test_pending_failed_and_service_payments_are_not_court_revenue(): void
    {
        $booking=$this->booking('2026-09-15');
        foreach (['PENDING','FAILED'] as $status) {
            $booking->payment->update(['status'=>$status]);
            $this->assertSame(0.0,$this->revenue('2026-09-11')['revenue']);
        }
        $booking->payment->update(['status'=>'PAID','paid_at'=>null]);
        $this->assertSame(0.0,$this->revenue('2026-09-11')['revenue']);
        Payment::create(['booking_id'=>$booking->id,'purpose'=>'SERVICE','amount'=>20000,'status'=>'PAID','paid_at'=>'2026-09-11 12:00:00']);
        $this->assertSame(0.0,$this->revenue('2026-09-11')['revenue']);
    }

    public function test_dashboard_reports_and_export_use_payment_date(): void
    {
        $this->booking('2026-09-15','CONFIRMED');
        $this->actingAs(User::factory()->create(['role'=>'ADMIN']));
        $range=['from'=>'2026-09-11','to'=>'2026-09-11'];
        $this->get(route('admin.reports.index',$range))->assertOk()->assertViewHas('revenue',fn($r)=>$r['revenue']===150000.0);
        $this->get(route('admin.dashboard',$range))->assertOk()->assertViewHas('kpis',fn($k)=>$k['revenue']===150000.0 && $k['gross_revenue']===150000.0);
        $this->get(route('admin.reports.index',['from'=>'2026-09-15','to'=>'2026-09-15']))->assertOk()->assertViewHas('revenue',fn($r)=>$r['revenue']===0.0);
        $csv=$this->get(route('admin.reports.export',$range))->assertOk()->streamedContent();
        $this->assertStringContainsString('150000',$csv);
    }
}
