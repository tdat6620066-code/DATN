<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Refund extends Model
{
    protected $fillable = ['refund_request_id', 'payment_id', 'refund_code', 'amount', 'status', 'processed_at'];

    protected $casts = ['amount' => 'decimal:2', 'processed_at' => 'datetime'];

    public function refundRequest()
    {
        return $this->belongsTo(RefundRequest::class);
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }
}
