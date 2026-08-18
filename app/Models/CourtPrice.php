<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class CourtPrice extends Model
{
    protected $fillable = [
        'court_id', 'time_slot_id', 'price', 'day_type', 'is_peak', 'effective_from', 'effective_to', 'status'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'is_peak' => 'boolean',
    ];

    public function court()
    {
        return $this->belongsTo(Court::class);
    }

    public function timeSlot()
    {
        return $this->belongsTo(TimeSlot::class);
    }

    public function isEffective(Carbon $date)
    {
        return $date->between($this->effective_from, $this->effective_to ?? now());
    }
}
