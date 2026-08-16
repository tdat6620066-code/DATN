<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    protected $fillable = [
        'user_id', 'court_id', 'booking_id', 'rating', 'content', 'status'
    ];

    protected $casts = [
        'rating' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function court()
    {
        return $this->belongsTo(Court::class);
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function images()
    {
        return $this->hasMany(ReviewImage::class);
    }
}
