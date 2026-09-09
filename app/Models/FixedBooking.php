<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FixedBooking extends Model
{
    protected $fillable = ['code', 'user_id', 'confirmation_key', 'definition', 'occurrences', 'status', 'total_price', 'expires_at'];

    protected $casts = ['definition' => 'array', 'occurrences' => 'array', 'total_price' => 'decimal:2', 'expires_at' => 'datetime'];

    public function payment() { return $this->hasOne(Payment::class); }

    public function user() { return $this->belongsTo(User::class); }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }
}
