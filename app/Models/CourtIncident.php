<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourtIncident extends Model
{
    protected $fillable = ['incident_code', 'court_id', 'reported_by', 'type', 'severity', 'description', 'images', 'status', 'resolution_note', 'resolved_at'];
    protected $casts = ['images' => 'array', 'resolved_at' => 'datetime'];
    public function court() { return $this->belongsTo(Court::class); }
    public function reporter() { return $this->belongsTo(User::class, 'reported_by'); }
}
