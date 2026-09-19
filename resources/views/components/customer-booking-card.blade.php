@props(['booking'])
@can('view', $booking)
@php
    $group = $booking->fixedBooking;
    $deadline = $group && $group->status !== 'LEGACY' ? $group->expires_at : $booking->hold_expires_at;
    $expired = $deadline && $deadline->lte(now());
    [$label, $tone] = match($booking->status) {
        'PENDING_PAYMENT' => $expired ? ['Hết hạn giữ chỗ', 'secondary'] : ['Chờ xác nhận', 'warning'],
        'CONFIRMED' => ['Đã xác nhận', 'info'], 'CHECKED_IN' => ['Đang chơi', 'success'],
        'COMPLETED' => ['Hoàn thành', 'success'], 'CANCELLED' => ['Đã hủy', 'danger'],
        'EXPIRED' => ['Đã hết hạn', 'secondary'], 'NO_SHOW' => ['Không đến sân', 'secondary'],
        default => [$booking->status, 'secondary'],
    };
    $paymentState = $booking->payment?->status ?? $booking->payment_status;
    [$paymentLabel, $paymentTone] = match($paymentState) {
        'PAID' => ['Đã thanh toán', 'success'], 'FAILED' => ['Thanh toán thất bại', 'danger'],
        'REFUNDED' => ['Đã hoàn tiền', 'secondary'], 'PARTIALLY_REFUNDED' => ['Hoàn tiền một phần', 'info'],
        default => ['Chờ thanh toán', 'warning'],
    };
    $canPay = auth()->user()->can('confirmPayment', $booking) && !$expired
        && !in_array($paymentState, ['PAID', 'REFUNDED', 'PARTIALLY_REFUNDED'])
        && !in_array($booking->payment_status, ['PAID', 'REFUNDED', 'PARTIALLY_REFUNDED'])
        && (!$group || $group->status === 'LEGACY' || $group->status === 'AWAITING_PAYMENT');
    $detailUrl = route('bookings.show', $booking);
    $payUrl = $group && $group->status !== 'LEGACY' ? route('bookings.fixed.show', $group) : $detailUrl;
    $court = $booking->bookingDetails->first()?->court;
    $canRebook = in_array($booking->status, ['COMPLETED', 'CANCELLED', 'EXPIRED', 'NO_SHOW']) && $court?->status === 'ACTIVE' && $court?->operational_status === 'AVAILABLE';
    $ticket = \App\Models\CourtIncident::where('active_booking_id', $booking->id)->where('source', 'CUSTOMER')->where('customer_id', auth()->id())->first();
    $canReport = !\App\Models\CourtIncident::where('active_booking_id', $booking->id)->exists()
        && \App\Models\Payment::forBooking($booking)->where('status', 'PAID')->exists();
@endphp
<article class="sz-customer-booking" data-booking-id="{{ $booking->id }}">
    <header><div><span class="small text-muted">Mã booking</span><br><strong>{{ $booking->booking_code }}</strong>@if($group)<span class="small text-muted d-block">Thuộc lịch cố định {{ $group->code }}</span>@endif</div><x-status-badge :label="$label" :tone="$tone"/></header>
    <div class="sz-customer-slots">@forelse($booking->bookingDetails as $detail)<div><strong>{{ $detail->court?->name ?? 'Sân đang cập nhật' }}</strong><span><i class="bi bi-calendar3 me-1" aria-hidden="true"></i>{{ $detail->booking_date->format('d/m/Y') }}</span><span><i class="bi bi-clock me-1" aria-hidden="true"></i>{{ substr($detail->timeSlot?->start_time, 0, 5) }}–{{ substr($detail->timeSlot?->end_time, 0, 5) }}</span></div>@empty<p class="text-muted mb-0">Chưa có lịch sân.</p>@endforelse</div>
    <div class="small mt-3">Thanh toán: <x-status-badge :label="$paymentLabel" :tone="$paymentTone"/></div>
    <footer><div class="sz-customer-money"><small>Tổng tiền</small>{{ number_format($booking->total_amount, 0, ',', '.') }}đ</div><div class="sz-customer-actions">
        <a class="btn btn-outline-primary btn-sm" href="{{ $detailUrl }}">Xem chi tiết</a>
        @if($canPay)<a class="btn btn-primary btn-sm" data-booking-action="pay" href="{{ $payUrl }}" @if($deadline) data-payment-deadline="{{ $deadline->getTimestampMs() }}" data-server-now="{{ now()->getTimestampMs() }}" @endif>Thanh toán</a>@endif
        @if(!$group && !$expired)
            @can('cancel', $booking)<form method="POST" action="{{ route('bookings.cancel', $booking) }}" data-cancel-booking>@csrf<button type="submit" class="btn btn-outline-danger btn-sm" data-booking-action="cancel">Hủy booking</button></form>@endcan
        @endif
        @if($canRebook)<a class="btn btn-outline-primary btn-sm" data-booking-action="rebook" href="{{ route('courts.show', $court) }}">Đặt lại</a>@endif
        @if($ticket)<a class="btn btn-outline-secondary btn-sm" href="{{ route('incident-tickets.show', $ticket) }}">Theo dõi hỗ trợ</a>@elseif($canReport)<a class="btn btn-outline-secondary btn-sm" data-booking-action="report" href="{{ route('incident-tickets.create', $booking) }}">Báo cáo sự cố</a>@endif
    </div></footer>
</article>
@endcan
