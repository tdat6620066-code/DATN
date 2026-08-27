<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatbotDocument extends Model
{
    protected $fillable = ['source_type', 'source_id', 'chunk_index', 'title', 'content', 'metadata', 'embedding', 'content_hash', 'active'];

    protected $casts = ['metadata' => 'array', 'embedding' => 'array', 'active' => 'boolean'];
}
