@props(['voucher'])

@php
    $discount = $voucher->discount_type === 'PERCENTAGE'
        ? 'Giảm '.$voucher->discount_value.'%'
        : 'Giảm '.number_format($voucher->discount_value, 0, ',', '.').'đ';
@endphp

<article class="voucher-card">
    <div class="voucher-card__top">
        <span class="voucher-card__badge"><i class="bi bi-ticket-perforated"></i> MÃ GIẢM GIÁ</span>
        <strong>{{ $discount }}</strong>
    </div>
    <h2>{{ $voucher->name }}</h2>
    <div class="voucher-card__code">
        <code>{{ $voucher->code }}</code>
        <button type="button" class="copy-voucher" data-code="{{ $voucher->code }}" aria-label="Sao chép mã {{ $voucher->code }}"><i class="bi bi-copy"></i> Sao chép</button>
    </div>
    <dl>
        <div><dt>Hạn dùng</dt><dd>{{ $voucher->end_at->format('d/m/Y H:i') }}</dd></div>
        <div><dt>Đơn tối thiểu</dt><dd>{{ number_format($voucher->min_order_amount, 0, ',', '.') }}đ</dd></div>
        @if($voucher->max_discount)<div><dt>Giảm tối đa</dt><dd>{{ number_format($voucher->max_discount, 0, ',', '.') }}đ</dd></div>@endif
    </dl>
    @if($voucher->conditions)<p class="voucher-card__conditions">{{ $voucher->conditions }}</p>@endif
    <a href="{{ route('courts.index') }}" class="voucher-card__action">Đặt sân ngay <i class="bi bi-arrow-right"></i></a>
</article>
