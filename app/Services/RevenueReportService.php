<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Refund;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class RevenueReportService
{
    public function courtRevenue(CarbonInterface $from, CarbonInterface $to): array
    {
        // Recognize successful receipts on paid_at, independent of court usage/status.
        // Refunds remain separate transactions in cashFlow(), on processed_at.
        $payments = Payment::query()->where('purpose', 'BOOKING')->where('status', 'PAID')
            ->whereNotNull('paid_at')->whereBetween('paid_at', [$from, $to])
            ->with(['booking.bookingDetails.court', 'fixedBooking.bookings.bookingDetails.court'])->get();
        $daily = collect();
        $courts = collect();
        $slots = 0;
        foreach ($payments as $payment) {
            $date = $payment->paid_at->toDateString();
            $cents = max(0, (int) round((float) $payment->amount * 100));
            $daily[$date] = ($daily[$date] ?? 0) + $cents;
            $bookings = $payment->fixed_booking_id
                ? ($payment->fixedBooking?->bookings ?? collect())
                : collect($payment->booking ? [$payment->booking] : []);
            $bookings = $bookings->sortBy('id');
            $bookingWeights = $bookings->mapWithKeys(fn ($booking) => [$booking->id => max(0, (int) round((float) $booking->total_amount * 100))])->all();
            $bookingAmounts = $this->allocate($cents, $bookingWeights);
            foreach ($bookings as $booking) {
                $details = $booking->bookingDetails->sortBy('id');
                $weights = $details->mapWithKeys(fn ($detail) => [$detail->id => max(0, (int) round((float) $detail->subtotal * 100))])->all();
                $amounts = $this->allocate($bookingAmounts[$booking->id] ?? 0, $weights);
                foreach ($details as $detail) {
                    $key = $detail->court_id;
                    $row = $courts[$key] ?? ['name' => $detail->court?->name ?? 'Sân đã xóa', 'slots' => 0, 'amount' => 0];
                    $row['slots']++;
                    $row['amount'] += $amounts[$detail->id] ?? 0;
                    $courts[$key] = $row;
                    $slots++;
                }
            }
        }
        return ['revenue' => $daily->sum() / 100.0, 'slots' => $slots,
            'daily' => $daily->sortKeys()->map(fn ($cents) => $cents / 100.0),
            'courts' => $courts->map(fn ($row) => array_replace($row, ['amount' => $row['amount'] / 100.0]))];
    }

    private function allocate(int $cents, array $weights): array
    {
        $remainingWeight = array_sum($weights);
        $amounts = [];
        foreach ($weights as $id => $weight) {
            $share = $remainingWeight > 0 ? (int) round($cents * $weight / $remainingWeight) : 0;
            $amounts[$id] = $share;
            $remainingWeight -= $weight;
            $cents -= $share;
        }
        return $amounts;
    }

    public function cashFlow(?CarbonInterface $from = null, ?CarbonInterface $to = null, ?Collection $bookingIds = null): array
    {
        return $this->report($from, $to, $bookingIds);
    }

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
