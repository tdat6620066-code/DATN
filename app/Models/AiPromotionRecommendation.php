<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiPromotionRecommendation extends Model
{
    protected $fillable = ['user_id', 'segment', 'title', 'reason', 'discount_percent', 'expires_at', 'status'];
    protected $casts = ['expires_at' => 'datetime'];
}
