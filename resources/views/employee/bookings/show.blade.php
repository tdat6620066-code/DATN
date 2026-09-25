@extends('layouts.employee')
@section('title','Xử lý đơn '.$booking->booking_code)
@section('page_heading','Chi tiết đơn đặt sân')
@section('content')
@include('partials.detail-styles')
<div class="sz-detail">
<div class="d-flex justify-content-between align-items-center mb-3"><div><h2 class="mb-1">{{ $booking->booking_code }}</h2><span class="staff-badge">{{ $booking->status }}</span></div><a href="{{ route('employee.bookings.index') }}" class="staff-button">Quay lại</a></div>
<div class="row g-3"><div class="col-lg-7"><div class="staff-card p-4 mb-3"><h5>Khách hàng & lịch chơi</h5><p><strong>{{ $booking->user->name }}</strong> · {{ $booking->user->phone }} · {{ $booking->user->email }}</p>@foreach($booking->bookingDetails as $d)<div class="border-top py-2">{{ $d->court->name }} — {{ $d->booking_date->format('d/m/Y') }} — {{ substr($d->timeSlot->start_time,0,5) }}–{{ substr($d->timeSlot->end_time,0,5) }}</div>@endforeach</div>
@include('partials.service-orders')
</div>
<div class="col-lg-5"><div class="staff-card p-4 mb-3"><h5>Thanh toán</h5>@include('employee.bookings.payment-summary')@if(!in_array($booking->payment_status, ['PAID','PARTIALLY_REFUNDED','REFUNDED']) && auth()->user()->hasPermission('payments.counter'))<form method="POST" action="{{ route('employee.bookings.payment',$booking) }}">@csrf<select name="payment_method" class="form-select mb-2" required><option value="CASH">Tiền mặt</option><option value="BANK_TRANSFER">Chuyển khoản</option><option value="QR">QR Code</option></select><input name="amount" type="number" value="{{ (float)$booking->total_amount }}" class="form-control mb-2" required><input name="transaction_id" class="form-control mb-2" placeholder="Mã giao dịch (nếu có)"><button class="staff-button staff-button-primary w-100 justify-content-center">Xác nhận thanh toán</button></form>@endif</div>
<div class="staff-card p-4"><h5>Thao tác vận hành</h5>@if($booking->status==='CONFIRMED' && auth()->user()->hasPermission('bookings.checkin'))<form method="POST" action="{{ route('employee.bookings.check-in',$booking) }}">@csrf<button class="staff-button staff-button-primary w-100 justify-content-center">Check-in khách hàng</button></form>@elseif($booking->status==='CHECKED_IN' && auth()->user()->hasPermission('bookings.checkout'))<button type="button" class="btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#venue-checkout">Check-out</button>@else<p class="text-muted mb-0">Không có thao tác phù hợp ở trạng thái hiện tại.</p>@endif</div></div></div>
@include('partials.special-refunds')
@include('partials.booking-operations')
@include('employee.partials.extend')
@include('partials.incident-resolutions')
</div>
@endsection

@push('scripts')
<script defer src="{{ asset('js/booking-payment-summary.js') }}?v={{ filemtime(public_path('js/booking-payment-summary.js')) }}"></script>
@endpush
