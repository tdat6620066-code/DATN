@extends('layouts.admin')
@section('title','Chi tiết booking - SmashZone')
@section('page_heading','Chi tiết booking')
@push('styles')<style>.detail-head{margin-bottom:20px}.detail-head a{color:#009959;font-weight:800;text-decoration:none}.d-grid2{display:grid;grid-template-columns:1.2fr .8fr;gap:20px}.cardx{margin-bottom:20px;padding:22px;border:1px solid #dfe8e4;border-radius:16px;background:#fff}.cardx h2{font-size:15px;font-weight:800}.info{display:grid;grid-template-columns:150px 1fr}.info dt,.info dd{padding:10px 0;border-bottom:1px solid #edf2ef;font-size:12px}.formx label{display:block;margin:10px 0 5px;font-size:10px;font-weight:900}.formx select,.formx textarea{width:100%;border:1px solid #dce5e1;border-radius:8px;padding:9px}.save,.cancel{margin-top:12px;border:0;border-radius:8px;padding:10px 13px;font-weight:800}.save{background:#13c976;color:#063421}.cancel{background:#fee8e6;color:#b93b34}.audit{padding:12px 0;border-bottom:1px solid #edf2ef;font-size:11px}.audit strong,.audit small{display:block}@media(max-width:850px){.d-grid2{grid-template-columns:1fr}}</style>@endpush
@section('content')<x-admin.workspace>
@include('partials.detail-styles')
<div class="sz-detail">
@php
    $fixedGroup = $booking->fixedBooking;
    $deadline = $fixedGroup && $fixedGroup->status !== 'LEGACY' ? $fixedGroup->expires_at : $booking->hold_expires_at;
    $holdEnded = $booking->status === 'PENDING_PAYMENT'
        && (($deadline && $deadline->lte(now())) || ($fixedGroup && in_array($fixedGroup->status, ['EXPIRED', 'PAYMENT_FAILED'])));
    $displayStatus = $holdEnded ? 'Hết hạn giữ chỗ' : $booking->status;
@endphp
<div class="detail-head"><a href="{{ route('admin.bookings.index') }}">← Danh sách booking</a><x-admin.page-heading>{{ $booking->booking_code }}</x-admin.page-heading></div>
<div class="d-grid2"><div>
<section class="cardx"><h2>Thông tin booking</h2><dl class="info"><dt>Khách hàng</dt><dd>{{ $booking->user->name }} · {{ $booking->user->email }}</dd><dt>Trạng thái</dt><dd>{{ $displayStatus }}</dd><dt>Thanh toán</dt><dd>{{ $booking->payment_status }} · {{ number_format($booking->total_amount) }}đ</dd><dt>Ghi chú</dt><dd>{{ $booking->note ?: '—' }}</dd>@foreach($booking->bookingDetails as $detail)<dt>Sân / lịch</dt><dd>{{ $detail->court->name }} · {{ $detail->booking_date->format('d/m/Y') }} · {{ $detail->timeSlot->name }} · {{ number_format($detail->price) }}đ</dd>@endforeach</dl></section>
@include('partials.special-refunds')
@include('partials.incident-resolutions')
<section class="cardx"><h2>Lịch sử thao tác</h2>
@forelse($booking->auditLogs->sortByDesc('created_at') as $log)
@include('partials.booking-audit', ['log' => $log])
@empty<p class="text-muted small mb-0">Chưa có lịch sử thao tác.</p>@endforelse
</section>
</div><aside>
<section class="cardx"><h2>Điều chỉnh booking</h2>@if(!$holdEnded && !in_array($booking->status,['COMPLETED','CANCELLED','EXPIRED']))<form class="formx" method="POST" action="{{ route('admin.bookings.update',$booking) }}">@csrf @method('PUT')<label>TRẠNG THÁI</label><select name="status">@foreach(['PENDING_PAYMENT','CONFIRMED','CHECKED_IN','COMPLETED'] as $status)<option value="{{ $status }}" @selected($booking->status===$status)>{{ $status }}</option>@endforeach</select><label>GHI CHÚ</label><textarea name="note" rows="3">{{ $booking->note }}</textarea><label>LÝ DO THAY ĐỔI *</label><textarea name="reason" rows="3" required></textarea><button class="save">Lưu thay đổi</button></form>@else<p>Booking đã kết thúc.</p>@endif</section>
@if(!$holdEnded && $booking->status === 'PENDING_PAYMENT' && $booking->payment_status !== 'PAID' && $booking->payment?->status !== 'PAID')
@foreach($booking->bookingDetails as $detail)<section class="cardx"><h2>Chuyển sân: {{ $detail->timeSlot->name }}</h2><form class="formx" method="POST" action="{{ route('admin.bookings.change-court',[$booking,$detail]) }}">@csrf @method('PUT')<label>SÂN MỚI</label><select name="court_id" required>@foreach($courts as $court)<option value="{{ $court->id }}" @disabled($court->id===$detail->court_id)>{{ $court->name }}</option>@endforeach</select><label>LÝ DO *</label><textarea name="reason" rows="2" required></textarea><button class="save">Chuyển sân và báo khách</button></form></section>@endforeach
<section class="cardx"><h2>Hủy / từ chối booking</h2><form class="formx" method="POST" action="{{ route('admin.bookings.cancel',$booking) }}">@csrf @method('PUT')<label>LÝ DO *</label><textarea name="reason" rows="3" required></textarea><button class="cancel">Hủy booking</button></form></section>
@endif
</aside></div>
</div>
@include('partials.service-orders')
@include('admin.bookings.reschedule')
</x-admin.workspace>@endsection
