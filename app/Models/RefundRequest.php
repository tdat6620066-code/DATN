<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RefundRequest extends Model
{
    protected $fillable = [
        'booking_id', 'requested_by', 'reviewed_by', 'amount', 'reason',
        'supporting_information', 'status', 'decision_note',
        'requested_information', 'reviewed_at',
    ];

    protected $casts = ['amount' => 'decimal:2', 'reviewed_at' => 'datetime'];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function refund()
    {
        return $this->hasOne(Refund::class);
    }
}
