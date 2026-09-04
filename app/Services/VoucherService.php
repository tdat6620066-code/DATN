<?php

namespace App\Services;

use App\Models\Voucher;

class VoucherService
{
    /**
     * Return the valid voucher that produces the largest discount for an amount.
     * The booking flow still validates it again inside the creation transaction.
     */
    public function bestForAmount(float $amount): ?array
    {
        return Voucher::query()
            ->where('status', 'ACTIVE')
            ->where('start_at', '<=', now())
            ->where(fn ($query) => $query->whereNull('end_at')->orWhere('end_at', '>=', now()))
            ->where('min_order_amount', '<=', $amount)
            ->where(fn ($query) => $query->whereNull('usage_limit')->orWhereColumn('used_count', '<', 'usage_limit'))
            ->get()
            ->map(fn (Voucher $voucher) => [
                'code' => $voucher->code,
                'name' => $voucher->name,
                'discount' => (float) $voucher->calculateDiscount($amount),
            ])
            ->sortByDesc('discount')
            ->first(fn (array $voucher) => $voucher['discount'] > 0);
    }

    /**
     * Validate and apply voucher code
     */
    public function validateAndApply($voucherCode, $amount)
    {
        if (! $voucherCode) {
            return [
                'valid' => false,
                'message' => 'Vui lòng nhập mã voucher',
                'discount' => 0,
            ];
        }

        $voucher = Voucher::where('code', $voucherCode)->first();

        if (! $voucher) {
            return [
                'valid' => false,
                'message' => 'Mã voucher không tồn tại',
                'discount' => 0,
            ];
        }

        if (! $voucher->isValid()) {
            return [
                'valid' => false,
                'message' => 'Mã voucher đã hết hạn hoặc không còn hiệu lực',
                'discount' => 0,
            ];
        }

        if ($amount < $voucher->min_order_amount) {
            return [
                'valid' => false,
                'message' => 'Booking chưa đạt giá trị tối thiểu để áp dụng voucher',
                'discount' => 0,
            ];
        }

        $discount = $voucher->calculateDiscount($amount);

        return [
            'valid' => true,
            'voucher_id' => $voucher->id,
            'code' => $voucher->code,
            'discount' => $discount,
            'message' => 'Áp dụng voucher thành công. Giảm '.number_format($discount, 0).'đ',
        ];
    }

    /**
     * Calculate discount amount
     */
    public function calculateDiscount($voucherId, $amount)
    {
        $voucher = Voucher::find($voucherId);

        if (! $voucher || ! $voucher->isValid()) {
            return 0;
        }

        return $voucher->calculateDiscount($amount);
    }

    /**
     * Increment voucher usage
     */
    public function incrementUsage($voucherId)
    {
        Voucher::whereKey($voucherId)
            ->where(function ($query) {
                $query->whereNull('usage_limit')->orWhereColumn('used_count', '<', 'usage_limit');
            })
            ->increment('used_count');
    }
}
