<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaintenanceSchedule extends Model
{
    protected $fillable = [
        'court_id', 'maintenance_date', 'start_date', 'end_date', 'start_time', 'end_time', 'reason', 'status'
    ];

    protected $casts = [
        'maintenance_date' => 'date',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function court()
    {
        return $this->belongsTo(Court::class);
    }
}
