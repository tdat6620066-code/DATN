<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Payment;

class PaymentService
{
    public function __construct(private readonly CustomerNotificationService $notifications) {}

    /**
     * Create payment record
     */
    public function createPayment(Booking $booking, $amount, $paymentMethod = null, $transactionId = null)
    {
        $payment = Payment::create([
            'booking_id' => $booking->id,
            'amount' => $amount,
            'payment_method' => $paymentMethod,
            'transaction_id' => $transactionId,
            'status' => 'PENDING',
        ]);

        return $payment;
    }

    /**
     * Mark payment as paid
     */
    public function markAsPaid(Payment $payment, $transactionId = null, $paymentMethod = null)
    {
        if ($payment->fixed_booking_id) {
            if (! app(FixedBookingPaymentService::class)->settle($payment, true, $transactionId, $paymentMethod ?? 'vnpay')) {
                throw new \DomainException('Lịch cố định đã hết hạn hoặc không còn đủ buổi để xác nhận.');
            }
            return $payment->refresh();
        }
        return \Illuminate\Support\Facades\DB::transaction(function () use ($payment, $transactionId, $paymentMethod) {
            $courtIds = $payment->booking->bookingDetails()->pluck('court_id');
            \App\Models\Court::whereIn('id', $courtIds)->orderBy('id')->lockForUpdate()->get();
            $locked = Payment::lockForUpdate()->findOrFail($payment->id);
            if ($locked->status !== 'PAID' && ! $locked->hasRefundActivity()) {
                foreach ($locked->booking->bookingDetails()->with('timeSlot')->get() as $detail) {
                    $held = \App\Models\BookingDetail::where('court_id', $detail->court_id)->whereDate('booking_date', $detail->booking_date)
                        ->where('status', '!=', 'CANCELLED')->whereHas('timeSlot', fn ($q) => $q->where('start_time', '<', $detail->timeSlot->end_time)->where('end_time', '>', $detail->timeSlot->start_time))
                        ->whereHas('booking', fn ($q) => $q->whereHas('fixedBooking', fn ($f) => $f->where('status', '!=', 'LEGACY'))
                            ->where(fn ($b) => $b->whereIn('status', ['CONFIRMED', 'CHECKED_IN', 'COMPLETED'])
                                ->orWhere(fn ($h) => $h->where('status', 'PENDING_PAYMENT')->where('hold_expires_at', '>', now()))))->exists();
                    if ($held) {
                        throw new \DomainException('Khung giờ đang được giữ cho lịch cố định. Vui lòng liên hệ nhân viên để đối chiếu thanh toán.');
                    }
                }
            }
            return $this->markSingleAsPaid($locked, $transactionId, $paymentMethod);
        }, 3);
    }

    private function markSingleAsPaid(Payment $payment, $transactionId = null, $paymentMethod = null)
    {
        $payment->refresh();
        if ($payment->status === 'PAID' || $payment->hasRefundActivity() || $payment->booking->status === 'CANCELLED') return $payment;
        $wasPaid = $payment->status === 'PAID';
        $payment->update([
            'status' => 'PAID',
            'paid_at' => now(),
            'transaction_id' => $transactionId ?? $payment->transaction_id,
            'payment_method' => $paymentMethod ?? $payment->payment_method,
        ]);

        // Update booking status to CONFIRMED
        $payment->booking->update([
            'status' => 'CONFIRMED',
            'payment_status' => 'PAID',
            'confirmed_at' => now(),
        ]);

        // Update booking details status
        foreach ($payment->booking->bookingDetails as $detail) {
            $detail->update(['status' => 'CONFIRMED']);
        }

        if (! $wasPaid) {
            $this->notifications->payment($payment->booking, 'PAID');
        }

        return $payment;
    }

    /**
     * Mark payment as failed
     */
    public function markAsFailed(Payment $payment, $transactionId = null)
    {
        if ($payment->fixed_booking_id) {
            app(FixedBookingPaymentService::class)->settle($payment, false, $transactionId);
            return $payment->refresh();
        }
        $payment->refresh();
        if ($payment->status === 'PAID' || $payment->hasRefundActivity()) return $payment;
        $wasFailed = $payment->status === 'FAILED';
        $payment->update([
            'status' => 'FAILED',
            'transaction_id' => $transactionId ?? $payment->transaction_id,
        ]);

        // Update booking status to PENDING_PAYMENT (still on hold)
        $payment->booking->update([
            'payment_status' => 'FAILED',
        ]);

        if (! $wasFailed) {
            $this->notifications->payment($payment->booking, 'FAILED');
        }

        return $payment;
    }

    /**
     * Get payment details
     */
    public function getPaymentDetails(Booking $booking)
    {
        $payment = $booking->payment;

        if (! $payment) {
            return null;
        }

        return [
            'id' => $payment->id,
            'booking_id' => $payment->booking_id,
            'amount' => $payment->amount,
            'status' => $payment->status,
            'payment_method' => $payment->payment_method,
            'transaction_id' => $payment->transaction_id,
            'paid_at' => $payment->paid_at,
        ];
    }
}
