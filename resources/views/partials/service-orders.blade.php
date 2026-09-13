@php
    $serviceStaff = in_array(auth()->user()->role, ['ADMIN', 'EMPLOYEE']);
    $canAddServices = $serviceStaff && $booking->status === 'CHECKED_IN' && auth()->user()->hasPermission('services.manage');
    $serviceOrders = $booking->serviceOrders()->with(['items.item', 'payment'])->latest()->get();
    $serviceCatalogue = $canAddServices ? \App\Models\ServiceItem::where('is_active', true)->orderBy('name')->get() : collect();
@endphp
<section id="booking-services" class="card staff-card p-4 my-3" aria-label="Dịch vụ của booking">
    <h2 class="h5">Dịch vụ {{ $serviceStaff ? 'phát sinh' : 'thêm cho buổi chơi' }}</h2>
    <p class="small text-muted">Dịch vụ chọn lúc đặt sân đã gộp vào tổng thanh toán. Dịch vụ phát sinh tại sân do nhân viên thêm và thu riêng.</p>
    @if($canAddServices)
    <details @if($errors->has('items') || session('error')) open @endif>
        <summary class="text-primary small fw-semibold py-2">Chọn dịch vụ</summary>
        <form method="POST" action="{{ route('service-orders.store', $booking) }}" class="sz-service-form">
            @csrf<input type="hidden" name="request_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
            @forelse($serviceCatalogue as $index => $item)
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 border-bottom py-3">
                <div><label for="service-qty-{{ $booking->id }}-{{ $item->id }}" class="fw-semibold">{{ $item->name }}</label><div class="small text-muted">{{ number_format($item->price, 0, ',', '.') }}đ · {{ $item->stock === null ? 'Có sẵn' : 'Còn '.$item->stock }}</div></div>
                <input type="hidden" name="items[{{ $index }}][service_item_id]" value="{{ $item->id }}">
                <div class="input-group input-group-sm" style="width:140px">
                    <button type="button" class="btn btn-outline-secondary" data-quantity-step="-1" aria-label="Giảm {{ $item->name }}">−</button>
                    <input id="service-qty-{{ $booking->id }}-{{ $item->id }}" type="number" name="items[{{ $index }}][quantity]" value="0" min="0" max="{{ min($item->stock ?? 1000, 1000) }}" data-service-price="{{ $item->price }}" class="form-control text-center">
                    <button type="button" class="btn btn-outline-secondary" data-quantity-step="1" aria-label="Tăng {{ $item->name }}">+</button>
                </div>
            </div>
            @empty<p class="small text-muted">Chưa có dịch vụ để chọn.</p>@endforelse
            @if($serviceCatalogue->isNotEmpty())
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3"><strong>Tổng phát sinh: <span data-service-total aria-live="polite">0đ</span></strong><button class="btn btn-primary" type="submit">Thêm dịch vụ</button></div>
            <p class="small text-muted mt-2 mb-0">Giữ số lượng trong 15 phút để thanh toán. Quá hạn sẽ trả lại tồn kho.</p>
            @endif
        </form>
    </details>
    @elseif(! $serviceStaff && $booking->status === 'CHECKED_IN')
        <p class="small">Bạn đang sử dụng sân. Vui lòng nhờ Staff thêm dịch vụ.</p>
    @endif
    @foreach($serviceOrders as $order)
        <article class="border-top pt-3 mt-3">
            <div class="d-flex justify-content-between flex-wrap gap-2"><strong>Dịch vụ #{{ $order->id }} · {{ number_format($order->payment->amount, 0, ',', '.') }}đ</strong><span class="small">{{ ['PENDING'=>'Chờ thanh toán', 'PAID'=>'Đã trả tiền · Chờ giao', 'DELIVERED'=>'Đã giao', 'CANCELLED'=>'Đã hủy'][$order->status] ?? $order->status }}</span></div>
            <p class="small text-muted mb-2">{{ $order->source === 'at_court' ? 'Staff thêm tại sân' : 'Khách chọn trước giờ chơi' }} · {{ $order->created_at->format('d/m/Y H:i') }}</p>
            @foreach($order->items as $line)<div class="small d-flex justify-content-between gap-2 py-1"><span>{{ $line->item->name }} × {{ $line->quantity }}</span><span>{{ number_format($line->subtotal, 0, ',', '.') }}đ</span></div>@endforeach
            @if($order->status === 'PENDING' && $order->expires_at->isFuture())
            <div class="d-flex flex-wrap gap-2 mt-3">
                @if($booking->payment_status === 'PAID' && ($serviceStaff || $order->source !== 'at_court'))
                <form method="POST" action="{{ route('service-orders.pay', $order) }}">@csrf<button class="btn btn-sm btn-primary">Thanh toán VNPay</button></form>
                @elseif($booking->payment_status !== 'PAID')<p class="small">Vui lòng thanh toán đơn đặt sân trước khi thanh toán dịch vụ.</p>
                @else<p class="small">Vui lòng thanh toán dịch vụ phát sinh với nhân viên tại sân.</p>@endif
                @if($serviceStaff && auth()->user()->hasPermission('payments.counter'))
                <form method="POST" action="{{ route('service-orders.cash', $order) }}">@csrf<input type="hidden" name="amount" value="{{ $order->payment->amount }}"><button class="btn btn-sm btn-outline-primary">Xác nhận đã thu {{ number_format($order->payment->amount, 0, ',', '.') }}đ tiền mặt</button></form>
                @endif
                @if($serviceStaff && auth()->user()->hasPermission('services.manage'))
                <form method="POST" action="{{ route('service-orders.cancel', $order) }}">@csrf<button class="btn btn-sm btn-outline-danger">Hủy khoản chưa trả</button></form>
                @endif
            </div>
            @elseif($order->status === 'PENDING')<p class="small text-muted mt-2">Đã hết hạn thanh toán; khoản này đang chờ giải phóng tồn kho.</p>
            @elseif($order->status === 'PAID' && $serviceStaff && auth()->user()->hasPermission('services.manage') && $booking->status === 'CHECKED_IN')
                <form method="POST" action="{{ route('service-orders.deliver', $order) }}" class="mt-3">@csrf<button class="btn btn-sm btn-primary">Xác nhận đã giao dịch vụ</button></form>
            @endif
        </article>
    @endforeach
    @foreach($booking->services()->whereNull('service_order_id')->with('item')->get() as $legacy)
        <p class="small text-muted mt-3">{{ $legacy->item->name }} × {{ $legacy->quantity }} · {{ number_format($legacy->subtotal, 0, ',', '.') }}đ · Đã gộp vào tiền đặt sân{{ $legacy->stock_released_at ? ' · Đã hủy' : '' }}.</p>
    @endforeach
</section>
@once
@push('scripts')
<script>
document.querySelectorAll('.sz-service-form').forEach(form => {
    const update = () => {
        const total = [...form.querySelectorAll('[data-service-price]')].reduce((sum, field) => sum + Number(field.dataset.servicePrice) * Math.max(0, Number(field.value) || 0), 0);
        form.querySelector('[data-service-total]').textContent = total.toLocaleString('vi-VN') + 'đ';
    };
    form.querySelectorAll('[data-quantity-step]').forEach(button => button.addEventListener('click', () => {
        const field = button.parentElement.querySelector('input');
        field.value = Math.max(0, Math.min(Number(field.max), (Number(field.value) || 0) + Number(button.dataset.quantityStep)));
        update();
    }));
    form.addEventListener('input', update);
});
</script>
@endpush
@endonce
