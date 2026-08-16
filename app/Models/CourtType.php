<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourtType extends Model
{
    protected $fillable = ['name', 'description', 'status'];

    public function courts()
    {
        return $this->hasMany(Court::class);
    }
}
