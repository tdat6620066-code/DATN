@props(['group'])
@php
    $expired = $group->expires_at && $group->expires_at->lte(now());
    [$label, $tone] = match($group->status) {
        'AWAITING_PAYMENT' => $expired ? ['Hết hạn giữ chỗ', 'secondary'] : ['Chờ xác nhận', 'warning'],
        'ACTIVE' => ['Đã xác nhận', 'info'], 'COMPLETED' => ['Hoàn thành', 'success'],
        'EXPIRED' => ['Đã hết hạn', 'secondary'], 'PAYMENT_FAILED' => ['Thanh toán thất bại', 'danger'],
        default => ['Lịch cố định', 'secondary'],
    };
    $canPay = $group->user_id === auth()->id() && $group->status === 'AWAITING_PAYMENT' && !$expired && !in_array($group->payment?->status, ['PAID', 'REFUNDED', 'PARTIALLY_REFUNDED']);
@endphp
<article class="sz-customer-booking" data-fixed-booking="{{ $group->id }}">
    <header><div><span class="small text-muted">Lịch cố định</span><br><strong>{{ $group->code }}</strong></div><x-status-badge :label="$label" :tone="$tone"/></header>
    <p>{{ $group->bookings_count ?? $group->bookings->count() }} buổi @if(!empty($group->definition['start_date']) && !empty($group->definition['end_date'])) · {{ \Carbon\Carbon::parse($group->definition['start_date'])->format('d/m/Y') }} – {{ \Carbon\Carbon::parse($group->definition['end_date'])->format('d/m/Y') }} @endif</p>
    <p class="small text-muted">Thanh toán: {{ match($group->payment?->status) { 'PAID'=>'Đã thanh toán','REFUNDED'=>'Đã hoàn tiền','PARTIALLY_REFUNDED'=>'Đã hoàn tiền một phần','FAILED'=>'Thất bại',default=>'Chờ thanh toán' } }}</p>
    <footer><div class="sz-customer-money"><small>Tổng tiền lịch cố định</small>{{ number_format($group->total_price ?? $group->bookings()->sum('total_amount'), 0, ',', '.') }}đ</div><div class="sz-customer-actions"><a href="{{ route('bookings.fixed.show', $group) }}" class="btn btn-outline-primary btn-sm">Xem chi tiết lịch</a>@if($canPay)<a href="{{ route('bookings.fixed.show', $group) }}" class="btn btn-primary btn-sm" data-booking-action="pay" @if($group->expires_at) data-payment-deadline="{{ $group->expires_at->getTimestampMs() }}" data-server-now="{{ now()->getTimestampMs() }}" @endif>Thanh toán</a>@endif</div></footer>
</article>
