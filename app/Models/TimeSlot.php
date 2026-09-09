<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TimeSlot extends Model
{
    protected $fillable = ['name', 'start_time', 'end_time', 'duration', 'status'];

    public static function areConsecutive(iterable $slots): bool
    {
        $previous = null;
        foreach (collect($slots)->sortBy('start_time') as $slot) {
            if ($previous && substr($previous->end_time, 0, 5) !== substr($slot->start_time, 0, 5)) {
                return false;
            }
            $previous = $slot;
        }

        return true;
    }

    public function courtPrices()
    {
        return $this->hasMany(CourtPrice::class);
    }

    public function bookingDetails()
    {
        return $this->hasMany(BookingDetail::class);
    }
}
