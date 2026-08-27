<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatbotKnowledge extends Model
{
    protected $table = 'chatbot_knowledge';
    protected $fillable = ['intent', 'title', 'category', 'keywords', 'answer', 'priority', 'active', 'sync_status', 'sync_error', 'synced_at', 'updated_by'];
    protected $casts = ['keywords' => 'array', 'priority' => 'integer', 'active' => 'boolean', 'synced_at' => 'datetime'];

    public function documents()
    {
        return $this->hasMany(ChatbotDocument::class, 'source_id')->where('source_type', 'knowledge')->orderBy('chunk_index');
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
