@extends('layouts.employee')
@section('title', 'Tổng quan Staff — SmashZone')
@section('page_heading', 'Tổng quan vận hành')
@section('content')
<header class="sz-staff-heading"><div><h1>Chào {{ auth()->user()->name }}!</h1><p>Theo dõi lịch sân và phục vụ khách hàng hôm nay.</p></div><a href="{{ route('employee.schedule') }}" class="btn btn-primary"><i class="bi bi-calendar-week me-2" aria-hidden="true"></i>Mở lịch sân</a></header>
<p class="small text-muted mb-3">{{ today()->format('d/m/Y') }} · Dữ liệu tại thời điểm tải trang</p>
<section class="sz-staff-stats" aria-label="Tình hình vận hành hôm nay">
@foreach(['today_bookings'=>['calendar2-check','Booking hôm nay'], 'checked_in'=>['person-check','Khách đã check-in'], 'playing_courts'=>['activity','Sân đang chơi'], 'upcoming_bookings'=>['clock','Booking sắp tới'], 'open_incidents'=>['exclamation-triangle','Sự cố cần xử lý']] as $key=>[$icon,$label])
<article class="sz-staff-stat"><i class="bi bi-{{ $icon }}" aria-hidden="true"></i><strong>{{ $statistics[$key] }}</strong><span>{{ $label }}</span></article>
@endforeach
</section>
<section class="sz-staff-panel"><div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3"><h2 class="mb-0">Lịch chơi hôm nay</h2><a href="{{ route('employee.schedule') }}">Xem toàn bộ lịch</a></div>
@forelse($todayBookings as $booking)
    @php([$label,$tone] = match($booking->status) {'PENDING_PAYMENT'=>['Giữ chỗ','warning'],'CONFIRMED'=>['Đã đặt','info'],'CHECKED_IN'=>['Đang chơi','success'],'COMPLETED'=>['Hoàn thành','success'],'CANCELLED'=>['Đã hủy','danger'],'EXPIRED'=>['Hết hạn','secondary'],default=>[$booking->status,'secondary']})
    <article class="sz-staff-booking"><div><strong>{{ $booking->booking_code }}</strong><small>{{ $booking->user?->name }}</small></div><div>@foreach($booking->bookingDetails as $detail)<div>{{ $detail->court?->name }}<small>{{ substr($detail->timeSlot?->start_time,0,5) }} – {{ substr($detail->timeSlot?->end_time,0,5) }}</small></div>@endforeach</div><div><x-status-badge :label="$label" :tone="$tone"/></div><div>@if(auth()->user()->hasPermission('bookings.view'))<a class="btn btn-outline-primary btn-sm" href="{{ route('employee.bookings.show',$booking) }}">Xem chi tiết</a>@endif</div></article>
@empty<x-empty-state icon="bi-calendar2" title="Chưa có booking hôm nay" description="Các lịch đặt trong ngày sẽ xuất hiện tại đây."/>@endforelse
</section>
@if(auth()->user()->hasPermission('bookings.view'))<div id="staff-checkin">@include('employee.partials.scan')</div>@endif
@endsection
