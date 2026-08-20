<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemAnnouncement extends Model
{
    protected $fillable = ['created_by', 'title', 'content', 'audience', 'status', 'scheduled_at', 'sent_at'];
    protected $casts = ['scheduled_at' => 'datetime', 'sent_at' => 'datetime'];
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
