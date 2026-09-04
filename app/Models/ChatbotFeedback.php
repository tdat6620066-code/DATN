<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatbotFeedback extends Model
{
    protected $table = 'chatbot_feedback';

    protected $fillable = ['chatbot_log_id', 'user_id', 'rating', 'comment'];

    public function chatbotLog(): BelongsTo
    {
        return $this->belongsTo(ChatbotLog::class);
    }
}
