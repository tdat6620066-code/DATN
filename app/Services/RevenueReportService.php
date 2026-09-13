<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Refund;
use App\Models\Booking;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class RevenueReportService
{
    public function courtRevenue(CarbonInterface $from, CarbonInterface $to): array
    {
        $bookings = Booking::query()
            ->whereNotIn('status', ['CANCELLED', 'EXPIRED', 'PENDING_PAYMENT'])
            ->whereHas('bookingDetails', fn ($q) => $q->whereDate('booking_date', '>=', $from->toDateString())->whereDate('booking_date', '<=', $to->toDateString()))
            ->with(['bookingDetails.court', 'payment', 'fixedBooking.payment', 'refundRequests.refund', 'refundRequests.incidentResolution'])
            ->get();
        $daily = collect();
        $courts = collect();
        $slots = 0;
        foreach ($bookings as $booking) {
            if ($booking->payment?->status !== 'PAID' || ! $booking->payment->paid_at) continue;
            // Allocate across ALL details, including cancelled and out-of-period slots.
            // The discounted total must never be redistributed to remaining sessions.
            $details = $booking->bookingDetails->sortBy('id');
            $weights = $details->mapWithKeys(fn ($d) => [$d->id => max(0, (int) round((float) $d->subtotal * 100))])->all();
            $amounts = $this->allocate(max(0, (int) round((float) $booking->total_amount * 100)), $weights);
            foreach ($booking->refundRequests as $request) {
                if ($request->refund?->status !== 'COMPLETED' || ! $request->refund->processed_at) continue;
                $cents = (int) round((float) $request->refund->amount * 100);
                $target = $request->incidentResolution?->booking_detail_id;
                $deductions = $target ? [$target => $cents] : $this->allocate($cents, $weights);
                foreach ($deductions as $id => $deduction) $amounts[$id] = max(0, ($amounts[$id] ?? 0) - $deduction);
            }
            foreach ($details as $detail) {
                $date = $detail->booking_date->toDateString();
                if ($detail->status === 'CANCELLED' || ($detail->status !== 'COMPLETED' && $booking->status !== 'COMPLETED')
                    || $date < $from->toDateString() || $date > $to->toDateString() || $date > today()->toDateString()) continue;
                $amount = $amounts[$detail->id] ?? 0;
                $daily[$date] = ($daily[$date] ?? 0) + $amount;
                $key = $detail->court_id;
                $row = $courts[$key] ?? ['name' => $detail->court?->name ?? 'Sân đã xóa', 'slots' => 0, 'amount' => 0];
                $row['slots']++;
                $row['amount'] += $amount;
                $courts[$key] = $row;
                $slots++;
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
