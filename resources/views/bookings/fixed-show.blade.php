@extends('layouts.app')
@section('title', 'Lịch cố định '.$fixedBooking->code)
@section('content')
@include('partials.detail-styles')
@php
    $statuses = ['PENDING_PAYMENT' => 'Chờ thanh toán', 'CONFIRMED' => 'Đã xác nhận', 'CHECKED_IN' => 'Đang chơi', 'COMPLETED' => 'Hoàn thành', 'CANCELLED' => 'Đã hủy', 'EXPIRED' => 'Hết hạn'];
    $bookings = $fixedBooking->bookings->keyBy('id');
    $shownBookings = [];
@endphp
<div class="sz-detail py-4">
    <a href="{{ route('bookings.index') }}" class="small text-decoration-none">← Đơn đặt sân của tôi</a>
    <header class="my-4"><h1 class="h3">Lịch cố định</h1><p class="text-muted mb-0">{{ $fixedBooking->code }} · {{ \Carbon\Carbon::parse($fixedBooking->definition['start_date'])->format('d/m/Y') }} – {{ \Carbon\Carbon::parse($fixedBooking->definition['end_date'])->format('d/m/Y') }}</p></header>
    <div class="row g-3 mb-4">
        <div class="col-sm-4"><section class="card p-3 h-100"><span class="small text-muted">Buổi đã tạo</span><strong class="fs-4">{{ $bookings->count() }}</strong></section></div>
        <div class="col-sm-4"><section class="card p-3 h-100"><span class="small text-muted">Khung giờ bỏ qua</span><strong class="fs-4">{{ collect($fixedBooking->occurrences)->where('choice', 'skip')->count() }}</strong></section></div>
        <div class="col-sm-4"><section class="card p-3 h-100"><span class="small text-muted">Tổng giá trị khi đặt</span><strong class="fs-4">{{ number_format($bookings->sum('total_amount'), 0, ',', '.') }}đ</strong></section></div>
    </div>
    @if($fixedBooking->status !== 'LEGACY')
    <section class="card p-4 mb-4">
        <h2 class="h5">Thanh toán toàn bộ lịch cố định</h2>
        <p>{{ ['AWAITING_PAYMENT' => 'Chờ thanh toán', 'PAYMENT_FAILED' => 'Thanh toán thất bại', 'ACTIVE' => 'Đã thanh toán', 'COMPLETED' => 'Hoàn thành', 'EXPIRED' => 'Hết hạn giữ chỗ'][$fixedBooking->status] ?? $fixedBooking->status }}</p>
        <strong class="fs-3">{{ number_format($fixedBooking->total_price, 0, ',', '.') }}đ</strong>
        @if($fixedBooking->status === 'AWAITING_PAYMENT')
        <p class="mt-3">Giữ chỗ đến {{ $fixedBooking->expires_at->format('H:i:s d/m/Y') }}. Một giao dịch xác nhận tất cả {{ $bookings->count() }} buổi.</p>
        <form method="POST" action="{{ route('bookings.fixed.pay', $fixedBooking) }}">@csrf
            <button class="btn btn-success">{{ (float) $fixedBooking->total_price > 0 ? 'Thanh toán VNPay '.number_format($fixedBooking->total_price, 0, ',', '.').'đ' : 'Xác nhận lịch miễn phí' }}</button>
        </form>
        @elseif(in_array($fixedBooking->status, ['EXPIRED', 'PAYMENT_FAILED']))
        <p>Chỗ đã được giải phóng. Vui lòng chọn và kiểm tra lịch lại.</p>
        <a href="{{ route('bookings.create-recurring') }}" class="btn btn-outline-success">Đặt lại lịch</a>
        @endif
    </section>
    @else
    <p class="text-muted small">Lịch cũ sử dụng thanh toán riêng từng buổi.</p>
    @endif
    <section class="card rounded-3 overflow-hidden">
    @foreach($fixedBooking->occurrences as $occurrence)
        @php($booking = $bookings->get($occurrence['booking_id'] ?? null))
        @continue($booking && in_array($booking->id, $shownBookings))
        @if($booking) @php($shownBookings[] = $booking->id)
            <p class="small"><a href="{{ route('bookings.qr', $booking) }}">Mã QR riêng của buổi {{ $booking->booking_code }}</a></p>
        @endif
        <div class="p-3 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                @if($booking)
                    @php($detail = $booking->bookingDetails->first())
                    <strong>{{ $detail->booking_date->format('d/m/Y') }} · {{ $detail->court->name }}</strong>
                    <div class="small text-muted mt-1">{{ $booking->bookingDetails->sortBy(fn ($row) => $row->timeSlot->start_time)->map(fn ($row) => $row->timeSlot->name)->join(' · ') }}</div><div class="small text-muted">{{ $booking->booking_code }}</div>
                    @if($occurrence['choice'] !== 'original')<div class="small text-success mt-1">Đã chọn {{ $occurrence['choice'] === 'court' ? 'sân' : 'giờ' }} thay thế khi đặt</div>@endif
                @else
                    <strong>{{ \Carbon\Carbon::parse($occurrence['original']['booking_date'])->format('d/m/Y') }} · {{ $occurrence['original']['court_name'] }}</strong>
                    <div class="small text-muted mt-1">{{ $occurrence['original']['time_slot'] }} · Đã bỏ qua · Không tính tiền</div>
                @endif
            </div>
            @if($booking)
            <div class="d-flex align-items-center flex-wrap gap-3"><div class="text-end"><strong>{{ number_format($booking->total_amount, 0, ',', '.') }}đ</strong><div class="small text-muted">{{ $statuses[$booking->status] ?? $booking->status }}</div>@if($booking->refunds()->where('refunds.status', 'COMPLETED')->sum('refunds.amount') > 0)<small class="text-success">Đã hoàn {{ number_format($booking->refunds()->where('refunds.status', 'COMPLETED')->sum('refunds.amount'), 0, ',', '.') }}đ</small>@endif</div><a class="btn btn-sm btn-outline-success" href="{{ route('bookings.show', $booking) }}">{{ $fixedBooking->status === 'LEGACY' && $booking->status === 'PENDING_PAYMENT' ? 'Thanh toán buổi này' : 'Chi tiết buổi' }}</a></div>
            @endif
        </div>
    @endforeach
    </section>
</div>
@endsection
