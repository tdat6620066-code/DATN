<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatbotLog extends Model
{
    protected $fillable = [
        'user_id',
        'session_id_hash',
        'question',
        'answer',
        'engine',
        'intent',
        'status',
        'latency_ms',
        'metadata',
        'error_message',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
