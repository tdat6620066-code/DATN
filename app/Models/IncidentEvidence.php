<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IncidentEvidence extends Model
{
    protected $table = 'incident_evidences';

    protected $fillable = ['incident_id', 'file_path', 'file_type'];

    public function incident()
    {
        return $this->belongsTo(CourtIncident::class, 'incident_id');
    }
}
