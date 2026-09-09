<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Refund;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class RevenueReportService
{
    /** Cash received and cash returned are recognized on their own transaction dates. */
    public function report(?CarbonInterface $from = null, ?CarbonInterface $to = null, ?Collection $bookingIds = null): array
    {
        $payments = Payment::query()->where('status', 'PAID')
            ->whereNotNull('paid_at')
            ->when($from, fn ($q) => $q->where('paid_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('paid_at', '<=', $to))
            ->when($bookingIds !== null, fn ($q) => $q->where(fn ($p) => $p->whereIn('booking_id', $bookingIds)
                ->orWhereHas('fixedBooking.bookings', fn ($b) => $b->whereIn('id', $bookingIds))))
            ->with('fixedBooking.bookings')
            ->get();
        if ($bookingIds !== null) {
            foreach ($payments as $payment) {
                if ($payment->fixed_booking_id) {
                    // Allocate one receipt to selected sessions without counting the full group repeatedly.
                    $payment->amount = $payment->fixedBooking->bookings->whereIn('id', $bookingIds)->sum('total_amount');
                }
            }
        }
        $refunds = Refund::query()->where('status', 'COMPLETED')->whereNotNull('processed_at')
            ->when($from, fn ($q) => $q->where('processed_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('processed_at', '<=', $to))
            ->when($bookingIds !== null, fn ($q) => $q->whereHas('refundRequest', fn ($r) => $r->whereIn('booking_id', $bookingIds)))
            ->get(['amount', 'processed_at']);
        $sum = fn ($rows) => $rows->sum(fn ($row) => (int) round((float) $row->amount * 100)) / 100.0;
        $gross = $sum($payments);
        $returned = $sum($refunds);

        return [
            'gross_revenue' => $gross,
            'refund_amount' => $returned,
            'net_revenue' => round($gross - $returned, 2),
            'gross_daily' => $payments->groupBy(fn ($p) => $p->paid_at->toDateString())->map($sum),
            'refund_daily' => $refunds->groupBy(fn ($r) => $r->processed_at->toDateString())->map($sum),
        ];
    }
}
