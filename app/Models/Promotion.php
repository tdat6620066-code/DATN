<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Promotion extends Model
{
    protected $fillable = [
        'title', 'description', 'image', 'start_at', 'end_at', 'status'
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
    ];

    public function isActive()
    {
        $now = now();
        return $this->status === 'ACTIVE'
            && $this->start_at <= $now
            && (!$this->end_at || $this->end_at >= $now);
    }
}
