@if(!auth()->check() || (auth()->user()->role ?: 'CUSTOMER') === 'CUSTOMER')
<nav class="customer-bottom-nav" aria-label="Điều hướng nhanh trên điện thoại">
@foreach([['home','house','Trang chủ'],['courts.index','grid','Tìm sân'],['bookings.index','calendar2-check','Booking'],['notifications.index','bell','Thông báo'],['profile','person','Tài khoản']] as [$destination,$icon,$label])
<a href="{{ route($destination) }}" @if(request()->routeIs($destination)) aria-current="page" @endif><i class="bi bi-{{ $icon }}" aria-hidden="true"></i><span>{{ $label }}</span></a>
@endforeach
</nav>
@endif
