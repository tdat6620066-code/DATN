<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingAuditLog extends Model
{
    protected $fillable = ['booking_id', 'actor_id', 'action', 'old_values', 'new_values', 'reason', 'ip_address', 'user_agent'];

    protected $casts = ['old_values' => 'array', 'new_values' => 'array'];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
