<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Equipment extends Model
{
    protected $table = 'equipment';
    protected $guarded = ['id'];
    public function loans() { return $this->hasMany(EquipmentLoan::class); }
}
