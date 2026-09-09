<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IncidentResolution extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['original_slot' => 'array', 'refund_amount' => 'decimal:2', 'resolved_at' => 'datetime'];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function detail()
    {
        return $this->belongsTo(BookingDetail::class, 'booking_detail_id');
    }

    public function incident()
    {
        return $this->belongsTo(CourtIncident::class, 'court_incident_id');
    }

    public function refundRequests()
    {
        return $this->hasMany(RefundRequest::class);
    }
}
