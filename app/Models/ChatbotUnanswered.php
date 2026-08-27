<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatbotUnanswered extends Model
{
    protected $table = 'chatbot_unanswered';

    protected $fillable = ['chatbot_log_id', 'question', 'intent', 'occurrences', 'status', 'admin_note'];
}
