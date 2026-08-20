@extends('layouts.employee')

@section('title', 'Tổng quan nhân viên - SmashZone')
@section('page_heading', 'Tổng quan vận hành')

@push('styles')
<style>
body{background:#f4f7f6}.employee-dashboard{color:#123044}.employee-hero{position:relative;overflow:hidden;padding:30px 34px;border-radius:22px;background:linear-gradient(125deg,#092c3f,#075e55);color:#fff;box-shadow:0 18px 45px rgba(10,57,60,.16)}.employee-hero:after{content:'';position:absolute;width:250px;height:250px;right:-55px;top:-105px;border-radius:50%;background:rgba(17,225,135,.16)}.employee-hero small{color:#7af3b6;font-weight:800;letter-spacing:1.2px}.employee-hero h1{margin:8px 0 5px;font-size:30px;font-weight:800}.employee-hero p{margin:0;color:#cbdcde}.employee-date{position:absolute;right:30px;bottom:29px;padding:9px 13px;border:1px solid rgba(255,255,255,.2);border-radius:10px;background:rgba(255,255,255,.08);font-size:13px;font-weight:700}.dashboard-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin:22px 0}.dashboard-stat{display:flex;align-items:center;gap:14px;padding:20px;border:1px solid #e1e9e5;border-radius:16px;background:#fff}.stat-icon{display:grid;place-items:center;flex:0 0 46px;height:46px;border-radius:13px;background:#e2f8ed;color:#05a963;font-size:21px}.dashboard-stat:nth-child(2) .stat-icon{background:#e2effa;color:#1479be}.dashboard-stat:nth-child(3) .stat-icon{background:#fff1dc;color:#d77a00}.dashboard-stat:nth-child(4) .stat-icon{background:#eee9ff;color:#6b4bdb}.dashboard-stat strong{display:block;font-size:26px;line-height:1;font-weight:800}.dashboard-stat span{display:block;margin-top:6px;color:#72838b;font-size:12px;font-weight:700}.dashboard-grid{display:grid;grid-template-columns:1.65fr 1fr;gap:20px}.dashboard-card{overflow:hidden;border:1px solid #e1e9e5;border-radius:17px;background:#fff}.dashboard-card-head{display:flex;align-items:center;justify-content:space-between;padding:20px 22px;border-bottom:1px solid #edf1ef}.dashboard-card-head h2{margin:0;font-size:17px;font-weight:800}.dashboard-card-head a{color:#009a59;font-size:12px;font-weight:800;text-decoration:none}.booking-row{display:grid;grid-template-columns:1.25fr 1.1fr .8fr auto;gap:14px;align-items:center;padding:16px 22px;border-bottom:1px solid #f0f3f1}.booking-row:last-child{border:0}.booking-customer strong,.booking-customer small{display:block}.booking-customer small,.booking-court small{color:#849198;font-size:11px}.booking-court{font-size:13px;font-weight:700}.status-chip{display:inline-flex;width:max-content;padding:6px 9px;border-radius:100px;background:#e7f7ef;color:#00864d;font-size:10px;font-weight:800}.status-chip.checked{background:#e4effa;color:#116da8}.status-chip.pending{background:#fff0d8;color:#b76700}.checkout-btn{border:0;border-radius:8px;background:#0b3041;color:#fff;padding:8px 11px;font-size:11px;font-weight:800}.refund-item{padding:16px 20px;border-bottom:1px solid #f0f3f1}.refund-item:last-child{border:0}.refund-top{display:flex;justify-content:space-between;gap:12px}.refund-top strong{font-size:13px}.refund-top span{color:#00a561;font-size:13px;font-weight:800}.refund-item p{margin:8px 0;color:#718087;font-size:12px;line-height:1.5}.refund-item a{color:#0b6e8b;font-size:11px;font-weight:800;text-decoration:none}.empty-state{padding:38px;text-align:center;color:#87959b}.quick-actions{display:flex;gap:10px;margin-top:20px}.quick-action{display:inline-flex;align-items:center;gap:8px;padding:11px 15px;border-radius:10px;background:#fff;border:1px solid #dae5df;color:#163847;font-size:12px;font-weight:800;text-decoration:none}.quick-action:hover{border-color:#09b76b;color:#00874e}@media(max-width:991px){.dashboard-stats{grid-template-columns:repeat(2,1fr)}.dashboard-grid{grid-template-columns:1fr}.employee-date{position:static;display:inline-block;margin-top:15px}}@media(max-width:600px){.dashboard-stats{grid-template-columns:1fr}.booking-row{grid-template-columns:1fr 1fr}.booking-row>*:last-child{grid-column:span 2}.employee-hero{padding:24px}.quick-actions{flex-wrap:wrap}}
</style>
@endpush

@section('content')
<div class="employee-dashboard">
    <section class="employee-hero">
        <small>TRUNG TÂM VẬN HÀNH</small>
        <h1>Chào {{ Auth::user()->name }}!</h1>
        <p>Theo dõi hoạt động sân và xử lý yêu cầu của khách hàng.</p>
        <div class="employee-date"><i class="bi bi-calendar3 me-2"></i>{{ now()->translatedFormat('l, d/m/Y') }}</div>
    </section>

    <section class="dashboard-stats">
        <article class="dashboard-stat"><i class="stat-icon bi bi-calendar2-check"></i><div><strong>{{ $statistics['today_bookings'] }}</strong><span>Booking hôm nay</span></div></article>
        <article class="dashboard-stat"><i class="stat-icon bi bi-person-check"></i><div><strong>{{ $statistics['checked_in'] }}</strong><span>Khách đang chơi</span></div></article>
        <article class="dashboard-stat"><i class="stat-icon bi bi-arrow-counterclockwise"></i><div><strong>{{ $statistics['pending_refunds'] }}</strong><span>Yêu cầu chờ xử lý</span></div></article>
        <article class="dashboard-stat"><i class="stat-icon bi bi-grid-3x3-gap"></i><div><strong>{{ $statistics['available_courts'] }}</strong><span>Sân sẵn sàng</span></div></article>
    </section>

    <div class="dashboard-grid">
        <section class="dashboard-card">
            <header class="dashboard-card-head"><h2>Lịch sân hôm nay</h2><span class="text-muted small">{{ now()->format('d/m/Y') }}</span></header>
            @forelse($todayBookings as $booking)
                @php($detail = $booking->bookingDetails->first())
                <div class="booking-row">
                    <div class="booking-customer"><strong>{{ $booking->user->name }}</strong><small>{{ $booking->booking_code }}</small></div>
                    <div class="booking-court">{{ $detail?->court?->name ?? '—' }}<small>{{ $detail?->timeSlot?->name ?? 'Chưa có giờ' }}</small></div>
                    <span class="status-chip {{ $booking->status === 'CHECKED_IN' ? 'checked' : '' }}">{{ $booking->status }}</span>
                    <div>@if($booking->status === 'CHECKED_IN' && Auth::user()->hasPermission('bookings.checkout'))<form method="POST" action="{{ route('employee.bookings.complete', $booking) }}">@csrf<button class="checkout-btn" onclick="return confirm('Xác nhận khách kết thúc sử dụng sân?')">Check-out</button></form>@else<span class="text-muted small">—</span>@endif</div>
                </div>
            @empty
                <div class="empty-state"><i class="bi bi-calendar-x fs-3 d-block mb-2"></i>Chưa có booking hôm nay.</div>
            @endforelse
        </section>

        <section class="dashboard-card">
            <header class="dashboard-card-head"><h2>Hoàn tiền cần xử lý</h2>@if(Auth::user()->hasPermission('refunds.manage'))<a href="{{ route('employee.refund-requests.index') }}">Xem tất cả →</a>@endif</header>
            @forelse($refundRequests as $item)
                <div class="refund-item"><div class="refund-top"><strong>{{ $item->requester->name }}</strong><span>{{ number_format($item->amount) }}đ</span></div><p>{{ Str::limit($item->reason, 75) }}</p>@if(Auth::user()->hasPermission('refunds.manage'))<a href="{{ route('employee.refund-requests.show', $item) }}">Mở yêu cầu #{{ $item->id }} →</a>@endif</div>
            @empty
                <div class="empty-state"><i class="bi bi-check2-circle fs-3 d-block mb-2"></i>Không có yêu cầu tồn đọng.</div>
            @endforelse
        </section>
    </div>

    <div class="quick-actions">@if(Auth::user()->hasPermission('refunds.manage'))<a class="quick-action" href="{{ route('employee.refund-requests.index') }}"><i class="bi bi-cash-coin"></i>Quản lý hoàn tiền</a>@endif @if(Auth::user()->hasPermission('bookings.view'))<a class="quick-action" href="{{ route('employee.bookings.index') }}"><i class="bi bi-calendar3"></i>Lịch sân & đơn đặt</a>@endif @if(Auth::user()->hasPermission('courts.status.manage'))<a class="quick-action" href="{{ route('employee.courts.index') }}"><i class="bi bi-grid"></i>Trạng thái sân</a>@endif</div>
</div>
@endsection
