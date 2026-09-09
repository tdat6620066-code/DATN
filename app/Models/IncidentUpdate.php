<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IncidentUpdate extends Model
{
    protected $fillable = ['incident_id', 'actor_id', 'status', 'note', 'event_type'];

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
