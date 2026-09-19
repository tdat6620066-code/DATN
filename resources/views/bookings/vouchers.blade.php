@extends('layouts.app')

@section('title', 'Chọn ưu đãi - SmashZone')

@section('content')
<div class="container py-5" style="max-width: 860px;">
    <a href="{{ route('bookings.show', $booking) }}" class="text-decoration-none text-secondary">← Quay lại thanh toán</a>
    <div class="d-flex justify-content-between align-items-end mt-3 mb-4">
        <div><h1 class="h3 mb-1">Chọn ưu đãi</h1><p class="text-secondary mb-0">Các voucher hiện có do SmashZone phát hành.</p></div>
        <strong>Tạm tính: {{ number_format($booking->subtotal, 0, ',', '.') }} ₫</strong>
    </div>

    @forelse($vouchers as $voucher)
        @php($eligible = $voucher->applicable_discount > 0)
        <article class="card mb-3 {{ $eligible ? '' : 'opacity-75' }}">
            <div class="card-body d-flex flex-column flex-md-row align-items-md-center gap-3">
                <div class="rounded-3 px-3 py-2 text-center" style="min-width: 110px; background: #e8fff0; color: #087a3b;">
                    <strong class="d-block">{{ $voucher->discount_type === 'PERCENTAGE' ? rtrim(rtrim($voucher->discount_value, '0'), '.') . '%' : number_format($voucher->discount_value, 0, ',', '.') . ' ₫' }}</strong>
                    <small>GIẢM GIÁ</small>
                </div>
                <div class="flex-grow-1">
                    <h2 class="h5 mb-1">{{ $voucher->name }}</h2>
                    <div class="text-secondary small">Mã: <strong>{{ $voucher->code }}</strong> · Đơn tối thiểu {{ number_format($voucher->min_order_amount, 0, ',', '.') }} ₫</div>
                    @if($voucher->max_discount)<div class="text-secondary small">Giảm tối đa {{ number_format($voucher->max_discount, 0, ',', '.') }} ₫</div>@endif
                    @if($voucher->conditions)<div class="text-secondary small mt-1">{{ $voucher->conditions }}</div>@endif
                    @if($eligible)<div class="text-success small mt-1">Bạn được giảm {{ number_format($voucher->applicable_discount, 0, ',', '.') }} ₫</div>@else<div class="text-danger small mt-1">Chưa đạt giá trị đơn tối thiểu để áp dụng</div>@endif
                </div>
                @if($eligible)
                    <form method="POST" action="{{ route('bookings.vouchers.apply', [$booking, $voucher]) }}">@csrf<button class="btn btn-success" type="submit">{{ $booking->voucher_id === $voucher->id ? 'Đang áp dụng' : 'Áp dụng' }}</button></form>
                @endif
            </div>
        </article>
    @empty
        <div class="card"><div class="card-body text-center py-5 text-secondary">Hiện chưa có voucher khả dụng.</div></div>
    @endforelse
</div>
@endsection
