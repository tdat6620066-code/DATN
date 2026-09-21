@php
    $scCurrentRole = Auth::user()?->role;
    $scPrimaryRoute = $scCurrentRole === 'ADMIN'
        ? route('admin.dashboard')
        : ($scCurrentRole === 'EMPLOYEE' ? route('employee.dashboard') : route('bookings.create'));
    $scPrimaryLabel = $scCurrentRole === 'ADMIN'
        ? 'Vào quản trị'
        : ($scCurrentRole === 'EMPLOYEE' ? 'Vào vận hành' : 'Đặt sân ngay');
@endphp

<link rel="stylesheet" href="{{ asset('css/customer-navigation.css') }}?v={{ filemtime(public_path('css/customer-navigation.css')) }}">
<link rel="stylesheet" href="{{ asset('css/customer-premium.css') }}?v={{ filemtime(public_path('css/customer-premium.css')) }}">
<link rel="stylesheet" href="{{ asset('css/customer-club.css') }}?v={{ filemtime(public_path('css/customer-club.css')) }}">
<link rel="stylesheet" href="{{ asset('css/customer-pages.css') }}?v={{ filemtime(public_path('css/customer-pages.css')) }}">
<script defer src="{{ asset('js/customer-layout.js') }}?v={{ filemtime(public_path('js/customer-layout.js')) }}"></script>
<header class="sc-nav">
    <div class="sc-container sc-nav-inner">
        <a class="sc-brand" href="{{ route('home') }}">
            <span class="sc-brand-name">SMASH<span>ZONE</span></span>
            <span class="sc-brand-motto">BOOK · PLAY · CONNECT</span>
        </a>
        <button type="button" class="btn btn-outline-primary sz-mobile-toggle" data-bs-toggle="collapse" data-bs-target="#customerNavigation" aria-controls="customerNavigation" aria-expanded="false" aria-label="Mở menu"><i class="bi bi-list" aria-hidden="true"></i></button>
        <nav class="sc-nav-links collapse" id="customerNavigation" aria-label="Điều hướng chính">
            <a href="{{ route('home') }}" class="{{ request()->routeIs('home') ? 'sc-active' : '' }}">Trang chủ</a>
            <a href="{{ route('courts.index') }}" class="{{ request()->routeIs('courts.*') ? 'sc-active' : '' }}">Khám phá sân</a>
            <a href="{{ route('bookings.create') }}" class="{{ request()->routeIs('bookings.create') ? 'sc-active' : '' }}">Đặt sân</a>
            <a href="{{ route('home') }}#offers">Khuyến mãi</a>
            <a href="{{ route('home') }}#services">Dịch vụ</a>
            <a href="{{ route('news.index') }}">Tin tức</a>
        </nav>
        <div class="sc-nav-actions">
            <a class="sc-search-trigger" href="{{ route('courts.index') }}" aria-label="Tìm sân"><i class="bi bi-search" aria-hidden="true"></i></a>
            @auth
                @include('partials.notification-dropdown')
                <div class="sc-user-menu">
                    <button type="button" class="sc-user-trigger" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Mở menu tài khoản">
                        <span class="sc-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span><span class="sc-account-label">Tài khoản</span>
                        <i class="bi bi-chevron-down caret"></i>
                    </button>
                    <div class="sc-user-dropdown dropdown-menu dropdown-menu-end">
                        <div class="sc-user-greeting">Xin chào,<strong>{{ auth()->user()->name }}</strong></div>
                        <a href="{{ route('profile') }}"><i class="bi bi-person"></i> Thông tin tài khoản</a>
                        <a href="{{ route('bookings.index') }}"><i class="bi bi-calendar2-check"></i> Lịch đặt của tôi</a>
                        <a href="{{ route('notifications.index') }}"><i class="bi bi-bell"></i> Thông báo</a>
                        @if((Auth::user()->role ?: 'CUSTOMER') === 'CUSTOMER')
                            <a href="{{ route('notification-settings.edit') }}"><i class="bi bi-sliders"></i> Cài đặt thông báo</a>
                        @endif
                        <div class="dropdown-divider"></div>
                        <form action="{{ route('logout') }}" method="POST" class="m-0">
                            @csrf
                            <button type="submit"><i class="bi bi-box-arrow-right"></i> Đăng xuất</button>
                        </form>
                    </div>
                </div>
            @else
                <a class="sz-notification-trigger" href="{{ route('notifications.index') }}" aria-label="Thông báo" title="Thông báo"><i class="bi bi-bell" aria-hidden="true"></i></a>
                <a class="sc-nav-user" href="{{ route('login') }}"><i class="bi bi-person-circle" aria-hidden="true"></i> Tài khoản</a>
            @endauth
            <a class="sc-btn-pill sc-btn-primary" href="{{ $scPrimaryRoute }}">{{ $scPrimaryLabel }}</a>
        </div>
    </div>
</header>
