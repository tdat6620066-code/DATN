@forelse($methods as $method => $amount)
<div class="small text-nowrap mb-1"><span class="badge bg-light text-dark border">{{ ['CASH' => 'Tiền mặt', 'VNPAY' => 'VNPay', 'BANK_TRANSFER' => 'Chuyển khoản ngân hàng', 'MOMO' => 'MoMo', 'UNKNOWN' => 'Chưa xác định'][strtoupper($method)] ?? $method }}</span> <span>{{ number_format($amount, 0, ',', '.') }}đ</span></div>
@empty
<span class="text-muted">—</span>
@endforelse
