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
        $dailyMethods = collect();
        $courts = collect();
        $slots = 0;
        foreach ($payments as $payment) {
            $date = $payment->paid_at->toDateString();
            $cents = max(0, (int) round((float) $payment->amount * 100));
            $daily[$date] = ($daily[$date] ?? 0) + $cents;
            $method = $payment->payment_method ?: 'UNKNOWN';
            $dayMethods = $dailyMethods[$date] ?? [];
            $dayMethods[$method] = ($dayMethods[$method] ?? 0) + $cents;
            $dailyMethods[$date] = $dayMethods;
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
                    $row = $courts[$key] ?? ['name' => $detail->court?->name ?? 'Sân đã xóa', 'slots' => 0, 'amount' => 0, 'methods' => []];
                    $row['slots']++;
                    $row['amount'] += $amounts[$detail->id] ?? 0;
                    $row['methods'][$method] = ($row['methods'][$method] ?? 0) + ($amounts[$detail->id] ?? 0);
                    $courts[$key] = $row;
                    $slots++;
                }
            }
        }
        return ['revenue' => $daily->sum() / 100.0, 'slots' => $slots,
            'daily' => $daily->sortKeys()->map(fn ($cents) => $cents / 100.0),
            'daily_methods' => $dailyMethods->map(fn ($methods) => collect($methods)->map(fn ($cents) => $cents / 100.0)),
            'courts' => $courts->map(fn ($row) => array_replace($row, ['amount' => $row['amount'] / 100.0, 'methods' => collect($row['methods'])->map(fn ($cents) => $cents / 100.0)]))];
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
            ->get(['amount', 'processed_at', 'refund_method']);
        $sum = fn ($rows) => $rows->sum(fn ($row) => (int) round((float) $row->amount * 100)) / 100.0;
        $gross = $sum($payments);
        $returned = $sum($refunds);
        $sources = $payments->groupBy(fn ($payment) => match (true) {
            $payment->purpose === 'SERVICE' => 'service',
            $payment->purpose === 'BOOKING' && (bool) $payment->fixed_booking_id => 'fixed',
            $payment->purpose === 'BOOKING' => 'booking',
            default => 'other',
        });
        $sourceTotals = collect([
            'booking' => 'Đặt sân theo ngày',
            'fixed' => 'Đặt lịch cố định',
            'service' => 'Dịch vụ phát sinh tại sân',
            'other' => 'Khoản thu khác',
        ])->map(fn ($label, $key) => [
            'label' => $label, 'count' => ($sources[$key] ?? collect())->count(),
            'amount' => $sum($sources[$key] ?? collect()),
        ]);

        return [
            'gross_revenue' => $gross,
            'sources' => $sourceTotals,
            'methods' => $payments->groupBy(fn ($payment) => $payment->payment_method ?: 'Chưa xác định')->map(fn ($rows) => ['count' => $rows->count(), 'amount' => $sum($rows)]),
            'refund_amount' => $returned,
            'net_revenue' => round($gross - $returned, 2),
            'gross_daily' => $payments->groupBy(fn ($p) => $p->paid_at->toDateString())->map($sum),
            'receipt_methods_daily' => $payments->groupBy(fn ($p) => $p->paid_at->toDateString())
                ->map(fn ($rows) => $rows->groupBy(fn ($p) => $p->payment_method ?: 'UNKNOWN')->map($sum)),
            'refund_methods_daily' => $refunds->groupBy(fn ($r) => $r->processed_at->toDateString())
                ->map(fn ($rows) => $rows->groupBy(fn ($r) => $r->refund_method ?: 'UNKNOWN')->map($sum)),
            'refund_daily' => $refunds->groupBy(fn ($r) => $r->processed_at->toDateString())->map($sum),
        ];
    }
}
