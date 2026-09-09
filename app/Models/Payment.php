<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'booking_id', 'fixed_booking_id', 'transaction_id', 'amount', 'payment_method',
        'status', 'paid_at', 'refunded_amount', 'refund_status',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'refunded_amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function refunds()
    {
        return $this->hasMany(Refund::class);
    }

    public function fixedBooking() { return $this->belongsTo(FixedBooking::class); }

    public function scopeForBooking($query, Booking $booking)
    {
        return $query->where(function ($q) use ($booking) {
            $q->where('booking_id', $booking->id);
            if ($booking->fixed_booking_id) {
                $q->orWhere('fixed_booking_id', $booking->fixed_booking_id);
            }
        });
    }

    public function hasRefundActivity(): bool
    {
        return ($this->refund_status ?? 'NONE') !== 'NONE' || $this->refunds()->exists();
    }

    public function transactionLogs()
    {
        return $this->hasMany(PaymentTransactionLog::class);
    }
}
