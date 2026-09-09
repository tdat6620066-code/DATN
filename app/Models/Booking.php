<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    protected static function booted(): void
    {
        static::updated(function (Booking $booking) {
            if ($booking->wasChanged('status') && $booking->fixed_booking_id && in_array($booking->status, ['COMPLETED', 'CANCELLED'])) {
                FixedBooking::whereKey($booking->fixed_booking_id)->where('status', 'ACTIVE')
                    ->whereDoesntHave('bookings', fn ($q) => $q->whereNotIn('status', ['COMPLETED', 'CANCELLED']))
                    ->update(['status' => 'COMPLETED']);
            }
        });
    }

    protected $fillable = [
        'booking_code', 'user_id', 'subtotal', 'discount', 'total_amount',
        'status', 'payment_status', 'booking_type', 'start_date', 'end_date', 'note', 'hold_expires_at', 'confirmed_at', 'cancelled_at',
        'checked_in_at', 'checked_out_at', 'fixed_booking_id', 'recurrence_key'
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
        'hold_expires_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'checked_in_at' => 'datetime',
        'checked_out_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function fixedBooking()
    {
        return $this->belongsTo(FixedBooking::class);
    }

    public function bookingDetails()
    {
        return $this->hasMany(BookingDetail::class);
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    public function getPaymentAttribute()
    {
        return $this->getRelationValue('payment') ?? $this->fixedBooking?->payment;
    }

    public function refunds()
    {
        return $this->hasManyThrough(Refund::class, RefundRequest::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function refundRequests()
    {
        return $this->hasMany(RefundRequest::class);
    }

    public function incidentResolutions() { return $this->hasMany(IncidentResolution::class); }

    public function auditLogs()
    {
        return $this->hasMany(BookingAuditLog::class);
    }

    public function services() { return $this->hasMany(BookingService::class); }
    public function checkedInBy() { return $this->belongsTo(User::class, 'checked_in_by'); }
    public function checkedOutBy() { return $this->belongsTo(User::class, 'checked_out_by'); }

    public function isHoldExpired()
    {
        return $this->hold_expires_at && $this->hold_expires_at < now();
    }
}
