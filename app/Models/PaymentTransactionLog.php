<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentTransactionLog extends Model
{
    protected $fillable = ['payment_id', 'actor_id', 'action', 'old_status', 'new_status', 'amount', 'note', 'metadata'];

    protected $casts = ['amount' => 'decimal:2', 'metadata' => 'array'];

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }
}
