@php
    $bookingServices = \App\Models\ServiceItem::where('is_active', true)->where('price', '>', 0)->orderBy('name')->get();
@endphp
<style>
    .booking-extras{max-width:1200px;margin:16px auto;border:1px solid #dce4e8;border-radius:12px;background:#fff;color:#142c3b;overflow:hidden;font-size:14px}
    .booking-extras summary{display:flex;align-items:center;gap:12px;cursor:pointer;padding:14px 18px;list-style:none}
    .booking-extras summary::-webkit-details-marker{display:none}
    .booking-extras summary::after{content:'+';font-size:22px;line-height:1;color:#587383}
    .booking-extras[open] summary::after{content:'−'}
    .booking-extras__heading{font-size:15px;font-weight:700}
    .booking-extras__optional{font-size:12px;color:#71818c;font-weight:400;margin-left:6px}
    .booking-extras__total{margin-left:auto;white-space:nowrap;font-weight:600;color:#087b60}
    .booking-extras__body{padding:0 18px 14px;border-top:1px solid #edf1f3}
    .booking-extras__hint{margin:10px 0;color:#71818c;font-size:12px}
    .booking-extras__grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));column-gap:28px}
    .booking-extras__row{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 0;border-bottom:1px solid #edf1f3}
    .booking-extras__row label{margin:0;min-width:0;font-size:13px}
    .booking-extras__meta{display:block;margin-top:3px;font-size:12px;color:#71818c}
    .booking-extras__row input[type=number]{box-sizing:border-box;width:64px;height:34px;flex-shrink:0;border:1px solid #d5dfe5;border-radius:7px;padding:5px 8px;background:#fff;color:#142c3b;font:inherit}
    .booking-extras__row input:focus{outline:2px solid #0ca678;outline-offset:2px}
    .booking-extras__row input[readonly]{background:#f3f5f6;color:#85929a}
    @media(max-width:600px){.booking-extras__grid{grid-template-columns:1fr}.booking-extras summary{padding:12px;gap:8px}.booking-extras__body{padding:0 12px 12px}.booking-extras__optional{display:block;margin:3px 0 0}}
</style>
<details class="booking-extras" aria-label="Chọn dịch vụ thêm" data-booking-services @if(collect(old('services', []))->sum('quantity') > 0 || $errors->has('services.*')) open @endif>
    <summary>
        <span class="booking-extras__heading">Dịch vụ thêm <span class="booking-extras__optional">Không bắt buộc</span></span>
        <span class="booking-extras__total" data-booking-service-total aria-live="polite">0đ</span>
    </summary>
    <div class="booking-extras__body">
    <p class="booking-extras__hint">Chọn số lượng. Tiền dịch vụ được cộng vào tiền sân.</p>
    <div class="booking-extras__grid">
    @forelse($bookingServices as $index => $item)
        <div class="booking-extras__row">
            <label for="booking-service-{{ $item->id }}">
                <strong>{{ $item->name }}</strong>
                <span class="booking-extras__meta">{{ number_format($item->price, 0, ',', '.') }}đ · {{ $item->stock === null ? 'Có sẵn' : ($item->stock > 0 ? 'Còn '.$item->stock : 'Hết hàng') }}</span>
            </label>
            <input type="hidden" form="bookingForm" name="services[{{ $index }}][service_item_id]" value="{{ $item->id }}">
            <input id="booking-service-{{ $item->id }}" form="bookingForm" type="number"
                name="services[{{ $index }}][quantity]" min="0" max="{{ min($item->stock ?? 1000, 1000) }}"
                value="{{ collect(old('services', []))->firstWhere('service_item_id', $item->id)['quantity'] ?? 0 }}"
                data-price="{{ $item->price }}" {{ $item->stock === 0 ? 'readonly' : '' }}>
        </div>
    @empty
        <p class="small text-muted">Hiện chưa có dịch vụ thêm.</p>
    @endforelse
    </div>
    @foreach($errors->get('services.*') as $messages)
        @foreach($messages as $message)<p class="text-danger small">{{ $message }}</p>@endforeach
    @endforeach
    </div>
</details>
<script>
(() => {
    const picker = document.querySelector('[data-booking-services]');
    const update = () => {
        const total = [...picker.querySelectorAll('[data-price]')].reduce((sum, input) => sum + Number(input.dataset.price) * Math.max(0, Number(input.value) || 0), 0);
        picker.querySelector('[data-booking-service-total]').textContent = total.toLocaleString('vi-VN') + 'đ';
        window.bookingServiceTotal = total;
        document.dispatchEvent(new Event('booking-services-changed'));
    };
    picker.addEventListener('input', update);
    update();
})();
</script>
