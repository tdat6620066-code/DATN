<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class EquipmentLoan extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['returned_at' => 'datetime'];
    public function equipment() { return $this->belongsTo(Equipment::class); }
    public function booking() { return $this->belongsTo(Booking::class); }
}
