@php
    $scCurrentRole = Auth::user()?->role;
    $scPrimaryRoute = $scCurrentRole === 'ADMIN'
        ? route('admin.dashboard')
        : ($scCurrentRole === 'EMPLOYEE' ? route('employee.dashboard') : route('courts.index'));
    $scPrimaryLabel = $scCurrentRole === 'ADMIN'
        ? 'Vào quản trị'
        : ($scCurrentRole === 'EMPLOYEE' ? 'Vào vận hành' : 'Đặt sân ngay');
@endphp
<style>
    .sc-nav {
        position: sticky;
        top: 0;
        left: 0;
        right: 0;
        z-index: 900;
        background: #fff;
        border-bottom: 1px solid #d8e0da;
        box-shadow: none;
    }
    .sc-container { width: min(1180px, calc(100% - 32px)); margin-inline: auto; }
    .sc-nav-inner { display: flex; align-items: center; justify-content: space-between; gap: 24px; padding: 13px 0; }
    .sc-brand { display: flex; align-items: center; gap: 10px; color: #081527; font-weight: 800; font-size: 21px; letter-spacing: -.5px; text-decoration: none; }
    .sc-brand img { height: 44px; border-radius: 10px; }
    .sc-nav-links { display: flex; gap: 28px; }
    .sc-nav-links a { color: #58708b; font-size: 14px; font-weight: 600; text-decoration: none; transition: color .2s; }
    .sc-nav-links a:hover, .sc-nav-links a.sc-active { color: #08b95b; }
    .sc-nav-actions { display: flex; align-items: center; gap: 12px; }
    .sc-btn-pill {
        display: inline-flex; align-items: center; justify-content: center; gap: 8px;
        padding: 11px 20px; border-radius: 999px; font-weight: 700; font-size: 14px;
        border: 0; cursor: pointer; transition: all .2s; white-space: nowrap; text-decoration: none;
    }
    .sc-btn-primary { background: #08dc6b; color: #081527; }
    .sc-btn-primary:hover { background: #00c960; color: #081527; transform: translateY(-1px); }
    .sc-nav-user { color: #081527; font-size: 14px; font-weight: 600; text-decoration: none; }
    .sc-user-menu { position: relative; }
    .sc-user-trigger {
        display: inline-flex; align-items: center; gap: 7px;
        color: #081527; font-size: 14px; font-weight: 600;
        background: none; border: 0; cursor: pointer; padding: 4px 0;
    }
    .sc-user-trigger:hover { color: #08b95b; }
    .sc-user-trigger .caret { font-size: 11px; transition: transform .2s; }
    .sc-user-menu:hover .sc-user-trigger .caret { transform: rotate(180deg); }
    .sc-user-dropdown {
        position: absolute; top: 100%; right: 0; min-width: 220px;
        padding: 8px; background: #fff; border-radius: 12px;
        box-shadow: 0 16px 40px rgba(2, 24, 35, .22);
        opacity: 0; visibility: hidden; transform: translateY(8px);
        transition: opacity .2s, transform .2s, visibility .2s; z-index: 60;
    }
    .sc-user-menu:hover .sc-user-dropdown { opacity: 1; visibility: visible; transform: translateY(0); }
    .sc-user-dropdown a, .sc-user-dropdown button {
        display: flex; align-items: center; gap: 10px; width: 100%;
        padding: 10px 12px; border: 0; background: none; border-radius: 9px;
        color: #1f2937; font-size: 13px; font-weight: 600; text-align: left; cursor: pointer; text-decoration: none;
    }
    .sc-user-dropdown a:hover, .sc-user-dropdown button:hover { background: #f1f5f9; color: #0b8a5a; }
    .sc-user-dropdown i { color: #64748b; width: 16px; text-align: center; }
    .sc-user-dropdown .dropdown-divider { height: 1px; background: #e5e7eb; margin: 6px 0; }
    @media (max-width: 991px) { .sc-nav-links { display: none; } }
    @media (max-width: 640px) {
        .sc-nav-user { display: none; }
        .sc-btn-pill { padding: 11px 14px; font-size: 12px; }
        .sc-brand { font-size: 19px; }
    }
</style>
<header class="sc-nav">
    <div class="sc-container sc-nav-inner">
        <a class="sc-brand" href="{{ route('home') }}">
            <img src="{{ asset('images/logo.png') }}" alt="SmashZone logo">
        </a>
        <nav class="sc-nav-links">
            <a href="{{ route('home') }}" class="{{ request()->routeIs('home') ? 'sc-active' : '' }}">Trang chủ</a>
            <a href="{{ route('courts.index') }}" class="{{ request()->routeIs('courts.*') ? 'sc-active' : '' }}">Sân cầu lông</a>
            <a href="{{ route('bookings.index') }}" class="{{ request()->routeIs('bookings.index') ? 'sc-active' : '' }}">Đặt lịch</a>
            <a href="{{ route('home') }}#offers">Khuyến mãi</a>
            <a href="{{ route('home') }}#news">Tin tức</a>
            <a href="{{ route('home') }}#why">Giới thiệu</a>
        </nav>
        <div class="sc-nav-actions">
            @auth
                <div class="sc-user-menu">
                    <button type="button" class="sc-user-trigger">
                        <i class="bi bi-person-circle"></i> {{ Str::limit(Auth::user()->name, 16) }}
                        <i class="bi bi-chevron-down caret"></i>
                    </button>
                    <div class="sc-user-dropdown">
                        <a href="{{ route('profile') }}"><i class="bi bi-person"></i> Thông tin tài khoản</a>
                        <!-- @if((Auth::user()->role ?: 'CUSTOMER') === 'CUSTOMER')
                            <a href="{{ route('bookings.index') }}"><i class="bi bi-calendar2-check"></i> Đặt sân của tôi</a>
                        @endif -->
                        <div class="dropdown-divider"></div>
                        <form action="{{ route('logout') }}" method="POST" style="margin:0;">
                            @csrf
                            <button type="submit"><i class="bi bi-box-arrow-right"></i> Đăng xuất</button>
                        </form>
                    </div>
                </div>
            @else
                <a class="sc-nav-user" href="{{ route('login') }}">Đăng nhập</a>
            @endauth
            <a class="sc-btn-pill sc-btn-primary" href="{{ $scPrimaryRoute }}">{{ $scPrimaryLabel }}</a>
        </div>
    </div>
</header>
