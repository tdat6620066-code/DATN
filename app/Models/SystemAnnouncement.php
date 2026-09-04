<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemAnnouncement extends Model
{
    protected $fillable = ['created_by', 'title', 'content', 'audience', 'target_type', 'target_user_ids', 'court_id', 'area', 'action_url', 'status', 'scheduled_at', 'sent_at'];
    protected $casts = ['target_user_ids' => 'array', 'scheduled_at' => 'datetime', 'sent_at' => 'datetime'];
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function court() { return $this->belongsTo(Court::class); }
    public function notifications() { return $this->hasMany(Notification::class, 'announcement_id'); }
}
