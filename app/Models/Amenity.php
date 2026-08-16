<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Amenity extends Model
{
    protected $fillable = ['name', 'description', 'status'];

    public function courts()
    {
        return $this->belongsToMany(Court::class, 'court_amenities');
    }
}
