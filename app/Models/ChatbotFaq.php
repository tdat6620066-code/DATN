<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatbotFaq extends Model
{
    protected $fillable = ['category', 'question_hash', 'question', 'answer', 'keywords', 'priority', 'active'];
    protected $casts = ['keywords' => 'array', 'priority' => 'integer', 'active' => 'boolean'];
}
