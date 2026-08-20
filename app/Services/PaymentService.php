<?php

namespace App\Services;

use App\Models\{Booking, Payment};

class PaymentService
{
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

        return $payment;
    }

    /**
     * Mark payment as failed
     */
    public function markAsFailed(Payment $payment, $transactionId = null)
    {
        $payment->update([
            'status' => 'FAILED',
            'transaction_id' => $transactionId ?? $payment->transaction_id,
        ]);

        // Update booking status to PENDING_PAYMENT (still on hold)
        $payment->booking->update([
            'payment_status' => 'FAILED',
        ]);

        return $payment;
    }

    /**
     * Process refund
     */
    public function refund(Payment $payment)
    {
        $payment->update([
            'status' => 'REFUNDED',
        ]);

        // Update booking status
        $payment->booking->update([
            'payment_status' => 'REFUNDED',
        ]);

        return $payment;
    }

    /**
     * Get payment details
     */
    public function getPaymentDetails(Booking $booking)
    {
        $payment = $booking->payment;

        if (!$payment) {
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
