<?php

namespace App\Http\Controllers;

use App\Models\Voucher;

class PromotionController extends Controller
{
    /** Display vouchers that customers can currently apply. */
    public function index()
    {
        $vouchers = Voucher::query()
            ->where('status', 'ACTIVE')
            ->where('start_at', '<=', now())
            ->where('end_at', '>=', now())
            ->where(fn ($query) => $query->whereNull('usage_limit')->orWhereColumn('used_count', '<', 'usage_limit'))
            ->orderBy('end_at')
            ->paginate(12);

        return view('promotions.index', compact('vouchers'));
    }
}
