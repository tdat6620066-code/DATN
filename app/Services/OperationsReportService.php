<?php

namespace App\Services;

use App\Models\BookingDetail;
use App\Models\IncidentResolution;
use App\Models\Refund;
use App\Models\RefundRequest;
use Carbon\CarbonInterface;

class OperationsReportService
{
    public function bookings(CarbonInterface $from, CarbonInterface $to): array
    {
        $details = BookingDetail::with('booking')->whereDate('booking_date', '>=', $from->toDateString())->whereDate('booking_date', '<=', $to->toDateString())->get();
        $active = $details->whereIn('status', ['CONFIRMED', 'CHECKED_IN', 'COMPLETED']);
        $incidentBookings = IncidentResolution::all()->filter(fn ($r) => ($r->original_slot['date'] ?? '') >= $from->toDateString() && ($r->original_slot['date'] ?? '') <= $to->toDateString())->pluck('booking_id')->unique();

        return [
            'total' => $details->pluck('booking_id')->unique()->count(),
            'no_show' => $details->where('booking.status', 'NO_SHOW')->pluck('booking_id')->unique()->count(),
            'completed' => $details->where('status', 'COMPLETED')->pluck('booking_id')->unique()->count(),
            'cancelled' => $details->where('status', 'CANCELLED')->pluck('booking_id')->unique()->count(),
            'incident' => $incidentBookings->count(),
            'slots' => $details->count(),
            'scheduled_value' => round($active->sum(fn ($d) => (float) $d->subtotal * min(1, (float) $d->booking->total_amount / max(1, (float) $d->booking->subtotal))), 2),
            'daily' => $details->groupBy(fn ($d) => $d->booking_date->toDateString())->map(fn ($rows) => ['bookings' => $rows->pluck('booking_id')->unique()->count(), 'slots' => $rows->count(), 'cancelled' => $rows->where('status', 'CANCELLED')->count()]),
        ];
    }

    public function refunds(CarbonInterface $from, CarbonInterface $to): array
    {
        $requests = RefundRequest::with('refund')->whereBetween('created_at', [$from, $to])->get();
        $completed = $requests->filter(fn ($r) => $r->refund?->status === 'COMPLETED')->count();
        $rejected = $requests->where('status', 'REJECTED')->count();
        $transactions = Refund::with(['payment', 'refundRequest.incidentResolution', 'refundRequest.booking.bookingDetails.court'])
            ->where('status', 'COMPLETED')->whereBetween('processed_at', [$from, $to])->get();
        $sum = fn ($rows) => $rows->sum(fn ($r) => (int) round((float) $r->amount * 100)) / 100.0;
        $group = fn ($rows) => ['count' => $rows->count(), 'amount' => $sum($rows)];
        $paymentIds = $transactions->pluck('payment_id')->unique();
        $cumulative = Refund::whereIn('payment_id', $paymentIds)->where('status', 'COMPLETED')->where('processed_at', '<=', $to)->get()->groupBy('payment_id')->map($sum);
        $full = $transactions->unique('payment_id')->filter(fn ($r) => round(($cumulative[$r->payment_id] ?? 0) * 100) >= round((float) $r->payment->amount * 100))->count();

        return [
            'requests' => $requests->count(), 'completed' => $completed, 'rejected' => $rejected,
            'processing' => $requests->count() - $completed - $rejected,
            'amount' => $sum($transactions), 'transactions' => $transactions->count(),
            'full' => $full, 'partial' => $paymentIds->count() - $full,
            'reasons' => $transactions->groupBy(fn ($r) => $r->refundRequest->reason_code ?? 'OTHER')->map($group),
            'courts' => $transactions->groupBy(function ($r) {
                $original = $r->refundRequest->incidentResolution?->original_slot;
                if ($original) {
                    return ($original['court'] ?? 'Sân').' (#'.($original['court_id'] ?? '?').')';
                }
                $courts = $r->refundRequest->booking->bookingDetails->pluck('court')->unique('id');

                return $courts->count() === 1 ? $courts->first()->name.' (#'.$courts->first()->id.')' : 'Nhiều sân / chưa xác định';
            })->map($group)->sortByDesc('amount'),
        ];
    }
}
