<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiInteraction extends Model
{
    protected $fillable = ['user_id', 'type', 'input', 'context', 'output', 'status', 'latency_ms'];
    protected $casts = ['context' => 'array', 'output' => 'array'];
}
