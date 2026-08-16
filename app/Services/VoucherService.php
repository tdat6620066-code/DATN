<?php

namespace App\Services;

use App\Models\Voucher;

class VoucherService
{
    /**
     * Validate and apply voucher code
     */
    public function validateAndApply($voucherCode, $amount)
    {
        if (!$voucherCode) {
            return [
                'valid' => false,
                'message' => 'Vui lòng nhập mã voucher',
                'discount' => 0
            ];
        }

        $voucher = Voucher::where('code', $voucherCode)->first();

        if (!$voucher) {
            return [
                'valid' => false,
                'message' => 'Mã voucher không tồn tại',
                'discount' => 0
            ];
        }

        if (!$voucher->isValid()) {
            return [
                'valid' => false,
                'message' => 'Mã voucher đã hết hạn hoặc không còn hiệu lực',
                'discount' => 0
            ];
        }

        $discount = $voucher->calculateDiscount($amount);

        return [
            'valid' => true,
            'voucher_id' => $voucher->id,
            'code' => $voucher->code,
            'discount' => $discount,
            'message' => "Áp dụng voucher thành công. Giảm " . number_format($discount, 0) . "đ"
        ];
    }

    /**
     * Calculate discount amount
     */
    public function calculateDiscount($voucherId, $amount)
    {
        $voucher = Voucher::find($voucherId);

        if (!$voucher || !$voucher->isValid()) {
            return 0;
        }

        return $voucher->calculateDiscount($amount);
    }

    /**
     * Increment voucher usage
     */
    public function incrementUsage($voucherId)
    {
        $voucher = Voucher::find($voucherId);
        
        if ($voucher) {
            $voucher->increment('used_count');
        }
    }
}
