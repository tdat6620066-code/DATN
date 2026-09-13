<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceOrder extends Model
{
    protected $fillable = ['booking_id', 'payment_id', 'added_by', 'request_key', 'source', 'status', 'delivered_at', 'delivered_by', 'expires_at', 'pay_at_checkout'];
    protected $casts = ['delivered_at' => 'datetime', 'expires_at' => 'datetime'];
    public function booking() { return $this->belongsTo(Booking::class); }
    public function payment() { return $this->belongsTo(Payment::class); }
    public function items() { return $this->hasMany(BookingService::class); }
}
