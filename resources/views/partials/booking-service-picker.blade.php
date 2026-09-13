@php
    $bookingServices = \App\Models\ServiceItem::where('is_active', true)->where('price', '>', 0)->orderBy('name')->get();
@endphp
<section class="card p-3 my-3" style="max-width:1200px;margin-left:auto;margin-right:auto" aria-label="Chọn dịch vụ thêm" data-booking-services>
    <h3 class="h5">Dịch vụ thêm (không bắt buộc)</h3>
    <p class="small text-muted">Dịch vụ đã chọn được cộng vào tiền sân và thanh toán một lần. Dịch vụ phát sinh tại sân do nhân viên thêm và thu riêng.</p>
    @forelse($bookingServices as $index => $item)
        <div class="d-flex justify-content-between align-items-center gap-3 border-bottom py-2">
            <label for="booking-service-{{ $item->id }}">
                <strong>{{ $item->name }}</strong><br>
                <span class="small">{{ number_format($item->price, 0, ',', '.') }}đ · {{ $item->stock === null ? 'Có sẵn' : ($item->stock > 0 ? 'Còn '.$item->stock : 'Hết hàng') }}</span>
            </label>
            <input type="hidden" form="bookingForm" name="services[{{ $index }}][service_item_id]" value="{{ $item->id }}">
            <input id="booking-service-{{ $item->id }}" form="bookingForm" class="form-control" style="width:90px" type="number"
                name="services[{{ $index }}][quantity]" min="0" max="{{ min($item->stock ?? 1000, 1000) }}"
                value="{{ collect(old('services', []))->firstWhere('service_item_id', $item->id)['quantity'] ?? 0 }}"
                data-price="{{ $item->price }}" {{ $item->stock === 0 ? 'readonly' : '' }}>
        </div>
    @empty
        <p class="small text-muted">Hiện chưa có dịch vụ thêm.</p>
    @endforelse
    <div class="mt-3 fw-bold">Tiền dịch vụ: <span data-booking-service-total aria-live="polite">0đ</span></div>
    @foreach($errors->get('services.*') as $messages)
        @foreach($messages as $message)<p class="text-danger small">{{ $message }}</p>@endforeach
    @endforeach
</section>
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
