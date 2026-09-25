<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Nhân viên - SmashZone')</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}?v=4">


    <link rel="stylesheet" href="{{ asset('css/staff-layout-base.css') }}?v={{ filemtime(public_path('css/staff-layout-base.css')) }}">
    @stack('styles')
@include('partials.brand-theme')
<link rel="stylesheet" href="{{ asset('css/staff-dashboard.css') }}?v={{ filemtime(public_path('css/staff-dashboard.css')) }}">
<link rel="stylesheet" href="{{ asset('css/workspace-ui.css') }}?v={{ filemtime(public_path('css/workspace-ui.css')) }}">
</head>
<body class="sz-layout sz-layout--employee"><a class="sz-skip-link" href="#sz-main">Đến nội dung chính</a>
<div class="staff-shell">
    <aside class="staff-sidebar offcanvas-lg offcanvas-start" tabindex="-1" aria-label="Điều hướng quản lý" id="staffSidebar"><button type="button" class="btn-close sz-sidebar-close" data-bs-dismiss="offcanvas" data-bs-target="#staffSidebar" aria-label="Đóng menu"></button>
        <a class="staff-logo" href="{{ route('employee.dashboard') }}"><img src="{{ asset('images/logo.png') }}?v=4" alt="SmashZone"></a>
        <div class="staff-section-label">VẬN HÀNH</div>
        @include('employee.partials.navigation')
        <div class="staff-sidebar-footer">
            <div class="staff-profile"><span class="staff-avatar">{{ Str::upper(Str::substr(Auth::user()->name, 0, 1)) }}</span><div><strong>{{ Str::limit(Auth::user()->name, 21) }}</strong><small>{{ Auth::user()->role === 'ADMIN' ? 'Quản trị viên' : 'Nhân viên' }}</small></div></div>
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="staff-logout"><i class="bi bi-box-arrow-left me-2"></i>Đăng xuất</button></form>
        </div>
    @if(auth()->user()->hasPermission('refunds.process'))<nav class="staff-nav"><a href="{{ route('refund-payouts.index') }}">Chi trả hoàn tiền</a></nav>@endif</aside>
    <div class="staff-content">
        <header class="staff-topbar">
            <div class="d-flex align-items-center gap-3"><button class="staff-menu-toggle" type="button" data-bs-toggle="offcanvas" data-bs-target="#staffSidebar" aria-controls="staffSidebar" aria-label="Mở menu"><i class="bi bi-list"></i></button><div class="staff-topbar-title"><strong>@yield('page_heading', 'Khu vực nhân viên')</strong><span>SmashZone Operations</span></div></div>
            <div class="staff-topbar-actions"><a class="staff-home-link btn btn-outline-success btn-sm text-nowrap" href="{{ route('employee.dashboard') }}"><i class="bi bi-speedometer2 me-1" aria-hidden="true"></i>Dashboard</a>@include('partials.notification-dropdown')</div>
        </header>
        <main class="staff-main" id="sz-main" tabindex="-1">
            @if($errors->any())<div class="alert alert-danger staff-alert">{{ $errors->first() }}</div>@endif
            @if(session('success'))<div class="alert alert-success staff-alert">{{ session('success') }}</div>@endif
            @if(session('error'))<div class="alert alert-danger staff-alert">{{ session('error') }}</div>@endif
            @yield('content')
        </main>
    </div>
</div>

<script src="{{ asset('js/status-labels.js') }}?v=2"></script>
<script defer src="{{ asset('js/venue-operations.js') }}?v=1"></script>
@stack('scripts')
</body>
</html>
