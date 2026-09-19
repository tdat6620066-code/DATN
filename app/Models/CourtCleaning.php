<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class CourtCleaning extends Model
{
    protected $guarded = ['id'];
    public function court() { return $this->belongsTo(Court::class); }
    public function employee() { return $this->belongsTo(User::class, 'employee_id'); }
}
