<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourtAmenity extends Model
{
    protected $fillable = ['court_id', 'amenity_id'];
    public $timestamps = false;

    public function court()
    {
        return $this->belongsTo(Court::class);
    }

    public function amenity()
    {
        return $this->belongsTo(Amenity::class);
    }
}
