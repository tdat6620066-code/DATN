@php
    $orders = $booking->serviceOrders()->where('status', '!=', 'CANCELLED')->with('payment')->get();
    $paidServices = $orders->filter(fn ($order) => in_array($order->payment?->status, ['PAID', 'PARTIALLY_REFUNDED'], true))->sum(fn ($order) => $order->payment->amount);
    $needsDelivery = $orders->whereIn('status', ['PENDING', 'PAID'])->isNotEmpty();
    $loans = \App\Models\EquipmentLoan::where('booking_id', $booking->id)->whereNull('returned_at')->with('equipment')->get();
    $missingRentals = $rentals->filter(fn ($line) => $line->returned_quantity < $line->quantity);
    $checkoutBlocked = $loans->isNotEmpty() || $missingRentals->isNotEmpty()
        || !in_array($booking->payment_status, ['PAID', 'PARTIALLY_REFUNDED'], true)
        || !in_array($booking->payment?->status, ['PAID', 'PARTIALLY_REFUNDED'], true)
        || ($due > 0 && !$actor->hasPermission('payments.counter'))
        || ($needsDelivery && !$actor->hasPermission('services.manage'));
@endphp
<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#venue-checkout">Kiểm tra & Check-out</button>
<div class="modal fade" id="venue-checkout" tabindex="-1" aria-labelledby="venue-checkout-title" aria-hidden="true">
<div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered"><div class="modal-content">
<div class="modal-header"><h2 class="modal-title fs-5" id="venue-checkout-title">Xác nhận Check-out</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button></div>
<div class="modal-body">
<div class="bg-light rounded-3 p-3 mb-3"><strong>{{ $booking->booking_code }}</strong><p class="mb-1">Khách: {{ $booking->user?->name }}</p>
@foreach($booking->bookingDetails->where('status', '!=', 'CANCELLED') as $checkoutDetail)
<p class="small mb-1">{{ $checkoutDetail->court?->name }} · {{ $checkoutDetail->booking_date->format('d/m/Y') }} · {{ substr($checkoutDetail->timeSlot?->start_time, 0, 5) }} – {{ substr($checkoutDetail->timeSlot?->end_time, 0, 5) }}</p>
@endforeach</div>
<dl class="row g-2">
<dt class="col-8">Tiền sân (đơn hiện tại)</dt><dd class="col-4 text-end">{{ number_format($booking->total_amount, 0, ',', '.') }}đ</dd>
<dt class="col-8">Dịch vụ đã thanh toán</dt><dd class="col-4 text-end">{{ number_format($paidServices, 0, ',', '.') }}đ</dd>
<dt class="col-8">Dịch vụ phát sinh chưa thu</dt><dd class="col-4 text-end">{{ number_format($due, 0, ',', '.') }}đ</dd>
</dl>
<div class="alert alert-info d-flex justify-content-between gap-2"><strong>Khoản dịch vụ chưa thanh toán</strong><strong>{{ number_format($due, 0, ',', '.') }}đ</strong></div>
<h3 class="h6">Đồ thuê cần trả</h3>
@forelse($missingRentals as $rental)<p>{{ $rental->item?->name }} · Còn {{ $rental->quantity - $rental->returned_quantity }}</p>@empty
@if($loans->isEmpty())<p class="text-muted">Không có đồ thuê cần trả.</p>@endif
@endforelse
@foreach($loans as $loan)<p>{{ $loan->equipment?->name }} · Chưa trả</p>@endforeach
@if($checkoutBlocked)<div class="alert alert-warning" role="alert">Chưa đủ điều kiện check-out. Hoàn tất thanh toán tiền sân, trả đồ thuê và xử lý dịch vụ với nhân viên có quyền trước khi tiếp tục.</div>@endif
<form method="POST" action="{{ route('operations.checkout', $booking) }}" data-venue-submit>@csrf
@if($due > 0 && $actor->hasPermission('payments.counter'))
<input type="hidden" name="amount" value="{{ $due }}">
<label class="d-flex gap-2 mb-3"><input class="form-check-input flex-shrink-0" type="checkbox" required> Tôi xác nhận đã thu {{ number_format($due, 0, ',', '.') }}đ tiền mặt cho dịch vụ phát sinh.</label>
@endif
@if($needsDelivery && $actor->hasPermission('services.manage'))<label class="d-flex gap-2 mb-3"><input class="form-check-input flex-shrink-0" type="checkbox" name="confirm_services_received" value="1" required> Khách đã nhận tất cả dịch vụ trong đơn.</label>@endif
<p class="small text-muted">Xác nhận sẽ kết thúc lượt chơi này. Hệ thống kiểm tra lại điều kiện trả sân khi gửi.</p>
<div class="d-flex flex-wrap justify-content-end gap-2"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Quay lại</button><button class="btn btn-primary" @disabled($checkoutBlocked)>Hoàn tất Check-out</button></div>
</form>
</div></div></div></div>
