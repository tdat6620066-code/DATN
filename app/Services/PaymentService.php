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
        if ($payment->purpose === 'SERVICE') {
            if (! app(ServiceOrderService::class)->settle($payment, true, $transactionId, $paymentMethod ?? 'vnpay')) throw new \DomainException('Khoản dịch vụ đã hủy hoặc hết hạn.');
            return $payment->refresh();
        }
        if ($payment->fixed_booking_id) {
            if (! app(FixedBookingPaymentService::class)->settle($payment, true, $transactionId, $paymentMethod ?? 'vnpay')) {
                throw new \DomainException('Lịch cố định đã hết hạn hoặc không còn đủ buổi để xác nhận.');
            }
            return $payment->refresh();
        }
        $courtIds = $payment->booking->bookingDetails()->pluck('court_id');
        return \Illuminate\Support\Facades\DB::transaction(function () use ($payment, $transactionId, $paymentMethod, $courtIds) {
            \App\Models\Court::whereIn('id', $courtIds)->orderBy('id')->lockForUpdate()->get();
            $booking = Booking::lockForUpdate()->findOrFail($payment->booking_id);
            $locked = Payment::lockForUpdate()->findOrFail($payment->id);
            $locked->setRelation('booking', $booking);
            if ($locked->status !== 'PAID' && ! $locked->hasRefundActivity()) {
                if ($locked->booking->status === 'EXPIRED' || $locked->booking->isHoldExpired()) {
                    throw new \DomainException('Thời gian giữ chỗ đã hết. Vui lòng chọn lại khung giờ.');
                }
                foreach ($locked->booking->bookingDetails()->with('timeSlot')->get() as $detail) {
                    $held = \App\Models\BookingDetail::where('court_id', $detail->court_id)->whereDate('booking_date', $detail->booking_date)
                        ->where('booking_id', '!=', $locked->booking_id)
                        ->where('status', '!=', 'CANCELLED')->whereHas('timeSlot', fn ($q) => $q->where('start_time', '<', $detail->timeSlot->end_time)->where('end_time', '>', $detail->timeSlot->start_time))
                        ->whereHas('booking', fn ($q) => $q
                            ->where(fn ($b) => $b->whereIn('status', ['CONFIRMED', 'CHECKED_IN', 'COMPLETED'])
                                ->orWhere(fn ($h) => $h->where('status', 'PENDING_PAYMENT')->where('hold_expires_at', '>', now()))))->exists();
                    if ($held) {
                        throw new \DomainException('Khung giờ đã được đặt hoặc đang được giữ chỗ. Vui lòng liên hệ nhân viên để đối chiếu thanh toán.');
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
            'hold_expires_at' => null,
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
        if ($payment->purpose === 'SERVICE') {
            app(ServiceOrderService::class)->settle($payment, false, $transactionId, 'ADMIN_RECONCILIATION');
            return $payment->refresh();
        }
        if ($payment->fixed_booking_id) {
            app(FixedBookingPaymentService::class)->settle($payment, false, $transactionId);
            return $payment->refresh();
        }
        return \Illuminate\Support\Facades\DB::transaction(function () use ($payment, $transactionId) {
            $booking = Booking::lockForUpdate()->findOrFail($payment->booking_id);
            $locked = Payment::lockForUpdate()->findOrFail($payment->id);
            if ($locked->status === 'PAID' || $locked->hasRefundActivity() || $booking->status !== 'PENDING_PAYMENT') return $locked;
            $locked->update(['status' => 'FAILED', 'transaction_id' => $transactionId ?? $locked->transaction_id]);
            $booking->update(['status' => 'EXPIRED', 'payment_status' => 'FAILED']);
            $booking->bookingDetails()->update(['status' => 'CANCELLED']);
            $this->notifications->payment($booking, 'FAILED');
            return $locked;
        }, 3);
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
