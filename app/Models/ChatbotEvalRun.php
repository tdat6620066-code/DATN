<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatbotEvalRun extends Model
{
    protected $fillable = [
        'version', 'mode', 'status', 'total', 'passed', 'quality_score',
        'category_scores', 'failures', 'duration_ms', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'quality_score' => 'float',
            'category_scores' => 'array',
            'failures' => 'array',
            'completed_at' => 'datetime',
        ];
    }
}
