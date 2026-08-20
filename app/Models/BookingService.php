<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingService extends Model
{
    protected $fillable = ['booking_id', 'service_item_id', 'added_by', 'quantity', 'unit_price', 'subtotal'];
    protected $casts = ['unit_price' => 'decimal:2', 'subtotal' => 'decimal:2'];
    public function item() { return $this->belongsTo(ServiceItem::class, 'service_item_id'); }
    public function booking() { return $this->belongsTo(Booking::class); }
    public function employee() { return $this->belongsTo(User::class, 'added_by'); }
}
