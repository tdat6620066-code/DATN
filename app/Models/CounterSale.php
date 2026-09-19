<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class CounterSale extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['total' => 'decimal:2'];
    public function lines() { return $this->hasMany(CounterSaleLine::class); }
    public function employee() { return $this->belongsTo(User::class, 'employee_id'); }
}
