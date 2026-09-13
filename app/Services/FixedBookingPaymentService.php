<?php

namespace App\Services;

use App\Models\FixedBooking;
use App\Models\Payment;
use App\Models\BookingDetail;
use App\Models\Court;
use Illuminate\Support\Facades\DB;

class FixedBookingPaymentService
{
    public function expire(FixedBooking $group): bool
    {
        return DB::transaction(function () use ($group) {
            $group = FixedBooking::lockForUpdate()->findOrFail($group->id);
            if ($group->status !== 'AWAITING_PAYMENT' || $group->expires_at?->isFuture()) {
                return $group->status === 'EXPIRED';
            }
            $payment = $group->payment()->lockForUpdate()->firstOrFail();
            $this->release($group, $payment);
            return true;
        }, 3);
    }

    private function release(FixedBooking $group, Payment $payment, string $status = 'EXPIRED'): void
    {
        $group->update(['status' => $status]);
        $payment->update(['status' => 'FAILED']);
        foreach ($group->bookings()->lockForUpdate()->get() as $booking) {
            if (in_array($booking->status, ['PENDING_PAYMENT', 'HOLD'])) {
                $booking->update(['status' => 'EXPIRED', 'payment_status' => 'FAILED']);
                $booking->bookingDetails()->update(['status' => 'CANCELLED']);
            }
        }
        app(CustomerNotificationService::class)->fixedBooking($group, $status === 'PAYMENT_FAILED' ? 'FAILED' : 'EXPIRED');
    }

    public function settle(Payment $payment, bool $success, ?string $transactionId = null, string $method = 'vnpay'): bool
    {
        $courtIds = BookingDetail::whereHas('booking', fn ($q) => $q->where('fixed_booking_id', $payment->fixed_booking_id))->pluck('court_id');
        return DB::transaction(function () use ($payment, $success, $transactionId, $method, $courtIds) {
            Court::whereIn('id', $courtIds)->orderBy('id')->lockForUpdate()->get();
            $group = FixedBooking::lockForUpdate()->findOrFail($payment->fixed_booking_id);
            $payment = $group->payment()->lockForUpdate()->firstOrFail();
            if ($payment->status === 'PAID') {
                return true; // Duplicate callbacks must never reset a refunded child.
            }
            if ($group->status !== 'AWAITING_PAYMENT') {
                return false;
            }
            if (! $group->expires_at || $group->expires_at->lessThanOrEqualTo(now())) {
                $this->release($group, $payment);
                return false;
            }
            if (! $success) {
                $payment->update(['transaction_id' => $transactionId ?? $payment->transaction_id, 'payment_method' => $method]);
                $this->release($group, $payment, 'PAYMENT_FAILED');
                return false;
            }
            $bookings = $group->bookings()->lockForUpdate()->get();
            if ($bookings->isEmpty() || $bookings->contains(fn ($booking) => $booking->status !== 'PENDING_PAYMENT'
                || $booking->bookingDetails()->where('status', '!=', 'PENDING')->exists())) {
                $this->release($group, $payment);
                return false;
            }
            if ($success) {
                foreach ($bookings as $booking) {
                    foreach ($booking->bookingDetails()->with('timeSlot')->get() as $detail) {
                        $conflict = BookingDetail::where('court_id', $detail->court_id)->whereDate('booking_date', $detail->booking_date)
                            ->where('status', '!=', 'CANCELLED')->whereHas('timeSlot', fn ($q) => $q->where('start_time', '<', $detail->timeSlot->end_time)->where('end_time', '>', $detail->timeSlot->start_time))
                            ->whereHas('booking', fn ($q) => $q->whereIn('status', ['CONFIRMED', 'CHECKED_IN', 'COMPLETED']))->exists();
                        if ($conflict || app(CourtAvailabilityService::class)->checkAvailability($detail->court_id, $detail->booking_date, $detail->time_slot_id) !== CourtAvailabilityService::STATUS_HOLD) {
                            $this->release($group, $payment);
                            return false;
                        }
                    }
                }
            }
            $payment->update(['status' => $success ? 'PAID' : 'FAILED', 'paid_at' => $success ? now() : null,
                'transaction_id' => $transactionId ?? $payment->transaction_id, 'payment_method' => $method]);
            $group->update(['status' => $success ? 'ACTIVE' : 'PAYMENT_FAILED']);
            foreach ($bookings as $booking) {
                $booking->update($success
                    ? ['status' => 'CONFIRMED', 'payment_status' => 'PAID', 'confirmed_at' => now(), 'hold_expires_at' => null]
                    : ['payment_status' => 'FAILED']);
                if ($success) {
                    $booking->bookingDetails()->update(['status' => 'CONFIRMED']);
                }
            }
            app(CustomerNotificationService::class)->fixedBooking($group, $success ? 'PAID' : 'FAILED');
            return $success;
        }, 3);
    }
}
