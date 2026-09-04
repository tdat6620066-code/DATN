<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiDemandForecast extends Model
{
    protected $fillable = ['court_id', 'time_slot_id', 'forecast_date', 'occupancy_rate', 'predicted_bookings', 'demand_level', 'recommendations', 'generated_at'];
    protected $casts = ['forecast_date' => 'date', 'occupancy_rate' => 'float', 'recommendations' => 'array', 'generated_at' => 'datetime'];
}
