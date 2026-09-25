@php
    $basePaid = in_array($booking->payment_status, ['PAID','PARTIALLY_REFUNDED','REFUNDED']);
    $included = $booking->services()->whereNull('service_order_id')->with('item')->get();
    $orders = $booking->serviceOrders()->where('status', '!=', 'CANCELLED')->with(['items.item','payment'])->get();
    $extra = $orders->sum(fn($order) => (float) ($order->payment?->amount ?? 0));
    $extraPaid = $orders->filter(fn($order) => in_array($order->payment?->status, ['PAID','PARTIALLY_REFUNDED','REFUNDED']))->sum(fn($order) => (float) $order->payment->amount);
    $paid = ($basePaid ? (float) $booking->total_amount : 0) + $extraPaid;
    $due = ($basePaid || in_array($booking->status, ['CANCELLED','EXPIRED']) ? 0 : (float) $booking->total_amount) + app(\App\Services\BookingOperationsService::class)->amountDue($booking);
    $refunded = $booking->refunds()->where('refunds.status','COMPLETED')->sum('refunds.amount');
    $money = fn($value) => number_format($value, 0, ',', '.').'đ';
@endphp
<div id="booking-payment-summary">
    <dl class="mb-3">
        <div class="d-flex justify-content-between gap-3 py-2 border-bottom"><dt>Tiền sân trước giảm giá</dt><dd>{{ $money($booking->bookingDetails->sum('subtotal')) }}</dd></div>
        @foreach($included as $line)<div class="d-flex justify-content-between gap-3 py-2 border-bottom"><dt class="fw-normal">{{ $line->item?->name }} × {{ $line->quantity }}<small class="d-block text-muted">Gộp trong booking · {{ $basePaid ? 'Đã thanh toán' : 'Chưa thanh toán' }}</small></dt><dd>{{ $money($line->subtotal) }}</dd></div>@endforeach
        <div class="d-flex justify-content-between gap-3 py-2"><dt>Giảm giá booking</dt><dd>−{{ $money($booking->discount) }}</dd></div>
        <div class="d-flex justify-content-between gap-3 py-2 border-bottom"><dt>Tổng booking<small class="d-block text-muted">{{ $basePaid ? 'Đã thanh toán' : $booking->payment_status }}</small></dt><dd>{{ $money($booking->total_amount) }}</dd></div>
    </dl>
    <h6>Dịch vụ phát sinh</h6>
    @forelse($orders as $order)
        <div class="border rounded p-3 mb-2">
            @foreach($order->items as $line)<div class="d-flex justify-content-between gap-2"><span>{{ $line->item?->name }} × {{ $line->quantity }}</span><strong>{{ $money($line->subtotal) }}</strong></div>@endforeach
            <small class="{{ $order->payment?->status === 'PAID' ? 'text-success' : 'text-warning' }}">{{ ['PAID'=>'Đã thanh toán','PENDING'=>'Chưa thanh toán','FAILED'=>'Thanh toán thất bại','REFUNDED'=>'Đã hoàn tiền','PARTIALLY_REFUNDED'=>'Hoàn một phần'][$order->payment?->status] ?? 'Chưa xác nhận' }}</small>
        </div>
    @empty<p class="small text-muted">Chưa có dịch vụ phát sinh.</p>@endforelse
    <div class="border-top pt-3 mt-3">
        <div class="d-flex justify-content-between mb-2"><span>Tổng tiền sân và dịch vụ</span><strong>{{ $money($booking->total_amount + $extra) }}</strong></div>
        <div class="d-flex justify-content-between mb-2 text-success"><span>Đã thanh toán</span><strong>{{ $money($paid) }}</strong></div>
        <div class="d-flex justify-content-between mb-2"><span>Đã hoàn tiền</span><strong>{{ $money($refunded) }}</strong></div>
        <div class="d-flex justify-content-between fs-5"><strong>Còn phải thanh toán</strong><strong>{{ $money($due) }}</strong></div>
    </div>
</div>
