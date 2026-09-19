@props(['active' => 'overview'])
@if((auth()->user()->role ?: 'CUSTOMER') === 'CUSTOMER')
@once
@push('styles') @vite(['resources/css/customer-dashboard.css', 'resources/js/customer-dashboard.js']) @endpush
@endonce
<div class="sz-customer-shell">
    <div class="sz-customer-mobile"><button class="btn btn-outline-primary w-100" type="button" data-bs-toggle="offcanvas" data-bs-target="#customer-menu" aria-controls="customer-menu"><i class="bi bi-layout-sidebar me-2" aria-hidden="true"></i>Menu tài khoản</button></div>
    <aside class="offcanvas-lg offcanvas-start sz-customer-sidebar" tabindex="-1" id="customer-menu" aria-labelledby="customer-menu-title">
        <div class="offcanvas-header"><h2 class="offcanvas-title h5" id="customer-menu-title">Tài khoản SmashZone</h2><button class="btn-close" type="button" data-bs-dismiss="offcanvas" data-bs-target="#customer-menu" aria-label="Đóng menu"></button></div>
        <div class="offcanvas-body">
            <div class="sz-customer-identity"><span class="sz-customer-avatar">@if(auth()->user()->avatar)<img src="{{ asset('storage/'.auth()->user()->avatar) }}" alt="">@else{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}@endif</span><strong>{{ auth()->user()->name }}</strong><span class="small text-muted">Thành viên SmashZone</span></div>
            @php($items = [
                ['overview', 'grid', 'Tổng quan', route('profile')],
                ['history', 'calendar2-check', 'Booking của tôi', route('bookings.index')],
                ['fixed', 'calendar-week', 'Đặt sân cố định', route('profile', ['section' => 'fixed'])],
                ['notifications', 'bell', 'Thông báo', route('notifications.index')],
                ['reviews', 'star', 'Đánh giá', route('profile', ['section' => 'reviews'])],
                ['favorites', 'heart', 'Sân yêu thích', route('favorites.index')],
                ['support', 'headset', 'Hỗ trợ', route('profile', ['section' => 'support'])],
                ['account', 'person', 'Hồ sơ', route('profile', ['section' => 'account'])],
                ['password', 'shield-lock', 'Đổi mật khẩu', route('password.change')],
            ])
            <nav class="sz-customer-nav" aria-label="Tài khoản khách hàng">@foreach($items as [$key, $icon, $label, $url])<a href="{{ $url }}" class="{{ $active === $key ? 'active' : '' }}" @if($active === $key) aria-current="page" @endif><i class="bi bi-{{ $icon }}" aria-hidden="true"></i>{{ $label }}</a>@endforeach</nav>
            <a class="btn btn-primary w-100 mt-4" href="{{ route('bookings.create') }}"><i class="bi bi-plus-lg me-2" aria-hidden="true"></i>Đặt sân mới</a>
        </div>
    </aside>
    <div class="sz-customer-content">{{ $slot }}</div>
</div>
@else {{ $slot }} @endif
