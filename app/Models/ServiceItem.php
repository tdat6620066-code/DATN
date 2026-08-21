<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceItem extends Model
{
    protected $fillable = ['code', 'name', 'category', 'price', 'stock', 'is_active'];
    protected $casts = ['price' => 'decimal:2', 'is_active' => 'boolean'];
}
