<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StatusDefinition extends Model
{
    protected $fillable = ['group_code', 'status_code', 'label_vi', 'description_vi'];
}
