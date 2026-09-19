@php
    $totalMinutes = $booking->bookingDetails->sum(fn ($detail) => (int) ($detail->timeSlot->duration ?? 0));
    $paymentLabel = match($booking->payment?->status ?? $booking->payment_status) {
        'PAID' => 'Đã thanh toán', 'FAILED' => 'Thanh toán thất bại',
        'REFUNDED' => 'Đã hoàn tiền', 'PARTIALLY_REFUNDED' => 'Đã hoàn tiền một phần',
        default => 'Chờ thanh toán',
    };
@endphp
<div class="sz-checkout">
    <header class="sz-checkout-heading">
        <a href="{{ route('bookings.index') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> Đơn đặt sân của tôi</a>
        <h1>Kiểm tra &amp; thanh toán</h1>
        <p>Kiểm tra lịch chơi, dịch vụ và tổng tiền trước khi xác nhận.</p>
        <div class="d-flex flex-wrap gap-2 align-items-center"><strong>{{ $booking->booking_code }}</strong><span class="small">Đơn: <x-status-badge :label="$status['label']" tone="warning"/></span><span class="small">Thanh toán: <x-status-badge :label="$paymentLabel" :tone="($booking->payment?->status === 'FAILED') ? 'danger' : 'info'"/></span></div>
    </header>
    <x-booking-progress :step="4"/>
    @if($booking->hold_expires_at)
        <div class="sz-hold"><i class="bi bi-clock-history me-2" aria-hidden="true"></i>Khung giờ đang được giữ. Vui lòng thanh toán trong <strong id="hold-countdown" data-hold-deadline="{{ $booking->hold_expires_at->getTimestampMs() }}" data-server-now="{{ now()->getTimestampMs() }}">{{ max(0, (int) now()->diffInSeconds($booking->hold_expires_at, false)) }} giây</strong>.</div>
    @endif
    <div id="checkout-feedback" class="alert alert-danger" role="alert" hidden></div>
    <div class="sz-checkout-grid">
        <div>
            <section class="sz-checkout-panel">
                <h2><i class="bi bi-person me-2" aria-hidden="true"></i>Thông tin khách hàng</h2>
                <div class="row g-3">
                    <div class="col-sm-6"><label for="checkout-name" class="form-label">Họ và tên</label><input id="checkout-name" class="form-control" value="{{ $booking->user->name }}" readonly autocomplete="name"></div>
                    <div class="col-sm-6"><label for="checkout-phone" class="form-label">Số điện thoại</label><input id="checkout-phone" class="form-control" value="{{ $booking->user->phone }}" readonly type="tel" autocomplete="tel"></div>
                    <div class="col-12"><label for="checkout-email" class="form-label">Email</label><input id="checkout-email" class="form-control" value="{{ $booking->user->email }}" readonly type="email" autocomplete="email"></div>
                </div>
                <p class="form-text mb-0">Thông tin từ tài khoản của bạn.</p>
            </section>
            <section class="sz-checkout-panel">
                <h2><i class="bi bi-calendar2-check me-2" aria-hidden="true"></i>Lịch chơi của bạn</h2>
                @forelse($booking->bookingDetails as $detail)
                    <div class="sz-booking-line"><div><strong>{{ $detail->court->name }}</strong><p>{{ $detail->booking_date->format('d/m/Y') }} · {{ substr($detail->timeSlot->start_time, 0, 5) }} – {{ substr($detail->timeSlot->end_time, 0, 5) }}</p></div><strong>{{ number_format($detail->subtotal, 0, ',', '.') }}đ</strong></div>
                @empty <p class="text-muted">Chưa có lịch sân.</p> @endforelse
                <p class="small text-muted mt-3 mb-0">Tổng thời lượng: <strong>{{ $totalMinutes }} phút</strong> · {{ $booking->bookingDetails->count() }} khung giờ</p>
            </section>
            <section class="sz-checkout-panel">
                <h2><i class="bi bi-bag-check me-2" aria-hidden="true"></i>Dịch vụ đã chọn</h2>
                @forelse($includedServices as $line)
                    <div class="sz-booking-line"><div><strong>{{ $line->item?->name ?? 'Dịch vụ' }}</strong><p>Số lượng: {{ $line->quantity }} · Đơn giá: {{ number_format($line->unit_price, 0, ',', '.') }}đ</p></div><strong>{{ number_format($line->subtotal, 0, ',', '.') }}đ</strong></div>
                @empty <p class="text-muted mb-0">Bạn không chọn thêm dịch vụ.</p> @endforelse
            </section>
            <section class="sz-checkout-panel">
                <h2><i class="bi bi-ticket-perforated me-2" aria-hidden="true"></i>Khuyến mãi</h2>
                @if($discount > 0)
                    <div class="alert alert-success mb-2">Đã áp dụng giảm giá {{ number_format($discount, 0, ',', '.') }}đ cho đơn này.</div>
                @else <p class="mb-2">Đơn này chưa áp dụng mã khuyến mãi.</p> @endif
                <p class="small text-muted mb-0">Mã khuyến mãi được nhập và áp dụng ở bước chọn lịch, trước khi tạo đơn giữ chỗ. Giá của đơn hiện tại đã được xác nhận.</p>
            </section>
            <section class="sz-checkout-panel">
                <h2><i class="bi bi-credit-card me-2" aria-hidden="true"></i>Phương thức thanh toán</h2>
                <label class="sz-payment-option"><input class="form-check-input m-0" type="radio" name="payment-display" checked aria-label="VNPay"><span><strong>VNPay</strong><small>Bạn sẽ được chuyển đến VNPay để hoàn tất giao dịch.</small></span></label>
                <form id="checkout-note-form" action="{{ route('bookings.update-note', $booking) }}" method="POST" data-checkout-form class="mt-4">
                    @csrf
                    <label class="form-label" for="checkout-note">Ghi chú cho sân <span class="text-muted">(không bắt buộc)</span></label>
                    <textarea id="checkout-note" name="note" rows="3" maxlength="1000" class="form-control @error('note') is-invalid @enderror" aria-describedby="note-help @error('note') note-error @enderror">{{ old('note', $booking->note) }}</textarea>
                    <div class="form-text" id="note-help">Tối đa 1.000 ký tự.</div>
                    @error('note')<div id="note-error" class="invalid-feedback">{{ $message }}</div>@enderror
                    <label class="sz-confirmation" id="confirm-booking"><input type="checkbox" class="form-check-input" required data-confirm-booking><span>Tôi đã kiểm tra sân, ngày, giờ, dịch vụ và tổng tiền của đơn đặt sân.</span></label>
                </form>
            </section>
        </div>
        <aside class="sz-checkout-panel sz-order-summary" aria-labelledby="order-summary-title">
            <h2 id="order-summary-title">Tóm tắt đơn hàng</h2>
            <p class="small text-muted">Mã đơn: {{ $booking->booking_code }}</p>
            <dl>
                <div><dt>Tiền sân</dt><dd>{{ number_format($booking->bookingDetails->sum('subtotal'), 0, ',', '.') }}đ</dd></div>
                <div><dt>Dịch vụ</dt><dd>{{ number_format($includedServices->sum('subtotal'), 0, ',', '.') }}đ</dd></div>
                <div><dt>Tạm tính</dt><dd>{{ number_format($subtotal, 0, ',', '.') }}đ</dd></div>
                <div><dt>Giảm giá</dt><dd class="text-success">−{{ number_format($discount, 0, ',', '.') }}đ</dd></div>
            </dl>
            <div class="sz-order-total"><span>TỔNG THANH TOÁN</span><strong data-server-total>{{ number_format($total, 0, ',', '.') }}đ</strong></div>
            <p class="small"><a href="#confirm-booking">Xác nhận thông tin</a> để bật nút thanh toán.</p>
            <button class="btn btn-primary w-100 py-3" type="submit" form="checkout-note-form" data-payment-submit><span class="spinner-border spinner-border-sm me-2" aria-hidden="true" hidden></span><span data-submit-label>Xác nhận &amp; Thanh toán</span></button>
            <p class="small text-muted mt-3 mb-0"><i class="bi bi-shield-check me-1" aria-hidden="true"></i>Đơn chỉ được xác nhận sau khi hệ thống nhận kết quả thanh toán thành công.</p>
            <a class="btn btn-link w-100 mt-2" href="{{ route('bookings.show', $booking) }}">Kiểm tra lại trạng thái</a>
            <details class="mt-3"><summary>Mã QR của đơn đặt sân</summary><div class="sz-booking-qr mt-3" role="img" aria-label="Mã QR tra cứu booking {{ $booking->booking_code }}">{!! app(\App\Services\QRCodeService::class)->generateQRCode($booking) !!}</div><p class="small text-muted">QR dùng để tra cứu đơn. Check-in vẫn yêu cầu thanh toán và đúng thời gian theo hệ thống.</p></details>
        </aside>
    </div>
</div>
