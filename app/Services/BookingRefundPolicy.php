<?php

namespace App\Services;

use App\Models\{Booking, Payment};
use Illuminate\Validation\ValidationException;

class BookingRefundPolicy
{
    public function approvalAmount(\App\Models\RefundRequest $request): float
    {
        return $request->incident_resolution_id && in_array($request->incidentResolution?->choice, ['CHANGE_COURT', 'RESCHEDULE'], true)
            ? (float) $request->amount : $this->remaining($request->booking);
    }
    public function eligible(Booking $booking): bool
    {
        return !$booking->checked_in_at && !in_array($booking->status, ['CHECKED_IN', 'COMPLETED'], true)
            && !$booking->bookingDetails()->whereIn('status', ['CHECKED_IN', 'COMPLETED'])->exists();
    }

    public function assertEligible(Booking $booking): void
    {
        if (!$this->eligible($booking)) {
            throw ValidationException::withMessages(['amount' => 'Booking đã check-in không được hoàn tiền.']);
        }
    }

    public function remaining(Booking $booking, ?Payment $payment = null): float
    {
        $payment ??= Payment::forBooking($booking)->first();
        if (!$payment || !in_array($payment->status, ['PAID', 'PARTIALLY_REFUNDED'], true)) return 0.0;
        $paidRemaining = (int) round((float) $payment->amount * 100)
            - (int) round((float) $payment->refunds()->whereIn('status', ['PROCESSING', 'COMPLETED'])->sum('amount') * 100);
        $bookingRemaining = (int) round((float) $booking->total_amount * 100)
            - (int) round((float) $booking->refunds()->whereIn('refunds.status', ['PROCESSING', 'COMPLETED'])->sum('refunds.amount') * 100);
        return max(0, min($paidRemaining, $bookingRemaining)) / 100.0;
    }
}
