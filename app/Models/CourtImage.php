<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CourtImage extends Model
{
    protected $fillable = ['court_id', 'image', 'is_primary', 'sort_order'];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function court()
    {
        return $this->belongsTo(Court::class);
    }

    public function getUrlAttribute(): string
    {
        return Str::startsWith($this->image, ['http://', 'https://'])
            ? $this->image
            : Storage::disk('public')->url($this->image);
    }
}
