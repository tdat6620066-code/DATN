@extends('layouts.app')
@section('title', 'Booking của tôi — SmashZone')
@section('content')
<x-customer-shell active="history">
    <header class="sz-dashboard-heading"><div><h1>Booking của tôi</h1><p>Theo dõi lịch chơi, thanh toán và trạng thái đặt sân.</p></div><a class="btn btn-primary" href="{{ route('bookings.create') }}">Đặt sân mới</a></header>
    <form class="sz-customer-filter" method="GET" action="{{ route('bookings.index') }}"><div><label class="form-label" for="booking-status">Trạng thái booking</label><select class="form-select" id="booking-status" name="status"><option value="">Tất cả trạng thái</option>@foreach(['PENDING_PAYMENT'=>'Chờ xác nhận','CONFIRMED'=>'Đã xác nhận','CHECKED_IN'=>'Đang chơi','COMPLETED'=>'Hoàn thành','CANCELLED'=>'Đã hủy','EXPIRED'=>'Đã hết hạn','NO_SHOW'=>'Không đến sân'] as $value=>$label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach</select></div><button class="btn btn-primary" type="submit">Lọc booking</button><a class="btn btn-outline-secondary" href="{{ route('bookings.index') }}">Đặt lại bộ lọc</a></form>
    <p class="small text-muted">{{ $bookings->total() }} đơn hoặc lịch cố định @if(request('status')) phù hợp bộ lọc @endif</p>
    <div class="customer-booking-collection">
    @forelse($bookings as $booking)
        @if($booking->fixedBooking) <x-customer-fixed-card :group="$booking->fixedBooking"/> @else <x-customer-booking-card :booking="$booking"/> @endif
    @empty<x-empty-state icon="bi-calendar2" title="Chưa có booking phù hợp" description="Thử thay đổi bộ lọc hoặc đặt sân cho buổi chơi mới."/>@endforelse
    </div>
    {{ $bookings->links() }}
</x-customer-shell>
@endsection
