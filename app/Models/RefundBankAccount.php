<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RefundBankAccount extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['bank_name', 'account_number', 'account_name'];

    protected $casts = ['bank_name' => 'encrypted', 'account_number' => 'encrypted', 'account_name' => 'encrypted', 'confirmed_at' => 'datetime'];

    public function refundRequest()
    {
        return $this->belongsTo(RefundRequest::class);
    }
}
