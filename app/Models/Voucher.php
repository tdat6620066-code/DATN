<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Voucher extends Model
{
    protected $fillable = [
        'code', 'name', 'discount_type', 'discount_value', 'min_order_amount',
        'max_discount', 'start_at', 'end_at', 'usage_limit', 'used_count', 'status'
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'min_order_amount' => 'decimal:2',
        'max_discount' => 'decimal:2',
        'start_at' => 'datetime',
        'end_at' => 'datetime',
    ];

    public function isValid()
    {
        $now = now();
        return $this->status === 'ACTIVE'
            && $this->start_at <= $now
            && (!$this->end_at || $this->end_at >= $now)
            && (!$this->usage_limit || $this->used_count < $this->usage_limit);
    }

    public function calculateDiscount($amount)
    {
        if ($amount < $this->min_order_amount) {
            return 0;
        }

        $discount = 0;
        if ($this->discount_type === 'FIXED') {
            $discount = $this->discount_value;
        } elseif ($this->discount_type === 'PERCENTAGE') {
            $discount = ($amount * $this->discount_value) / 100;
        }

        if ($this->max_discount) {
            $discount = min($discount, $this->max_discount);
        }

        return $discount;
    }
}
