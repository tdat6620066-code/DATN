<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourtImage extends Model
{
    protected $fillable = ['court_id', 'image', 'is_primary', 'sort_order'];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function court()
    {
        return $this->belongsTo(Court::class);
    }
}
