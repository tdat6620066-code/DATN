<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiReviewAnalysis extends Model
{
    protected $fillable = ['review_id', 'sentiment', 'confidence', 'topics', 'summary', 'model_version'];
    protected $casts = ['confidence' => 'float', 'topics' => 'array'];
}
