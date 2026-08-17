<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Court extends Model
{
    protected $fillable = [
        'code', 'name', 'court_type_id', 'description', 'address',
        'map_url', 'phone', 'opening_time', 'closing_time', 'status', 'availability_status',
        'operational_status', 'status_reason', 'status_updated_at', 'is_featured'
    ];

    protected $casts = [
        'is_featured' => 'boolean',
        'status_updated_at' => 'datetime',
    ];

    public function courtType()
    {
        return $this->belongsTo(CourtType::class);
    }

    public function amenities()
    {
        return $this->belongsToMany(Amenity::class, 'court_amenities');
    }

    public function images()
    {
        return $this->hasMany(CourtImage::class);
    }

    public function prices()
    {
        return $this->hasMany(CourtPrice::class);
    }

    public function bookingDetails()
    {
        return $this->hasMany(BookingDetail::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function maintenanceSchedules()
    {
        return $this->hasMany(MaintenanceSchedule::class);
    }

    public function getPrimaryImage()
    {
        return $this->images()->where('is_primary', true)->first();
    }

    public function getAverageRating()
    {
        return $this->reviews()
            ->where('status', 'APPROVED')
            ->avg('rating');
    }

    public function getReviewCount()
    {
        return $this->reviews()
            ->where('status', 'APPROVED')
            ->count();
    }
}
