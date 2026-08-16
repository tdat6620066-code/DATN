<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TimeSlot extends Model
{
    protected $fillable = ['name', 'start_time', 'end_time', 'duration', 'status'];

    public function courtPrices()
    {
        return $this->hasMany(CourtPrice::class);
    }

    public function bookingDetails()
    {
        return $this->hasMany(BookingDetail::class);
    }
}
