<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Nhân viên - SmashZone')</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}?v=4">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root{--staff-primary:#08b96b;--staff-dark:#092c3e;--staff-muted:#71828b;--staff-border:#dfe8e4;--staff-bg:#f4f7f6}*{box-sizing:border-box}body{margin:0;background:var(--staff-bg);color:#173443;font-family:'Segoe UI',Arial,sans-serif}.staff-shell{min-height:100vh}.staff-sidebar{position:fixed;inset:0 auto 0 0;z-index:30;width:260px;display:flex;flex-direction:column;padding:22px 16px;background:linear-gradient(180deg,#082c3e,#071f2e);color:#fff}.staff-logo{display:block;margin:0 5px 28px}.staff-logo img{display:block;width:205px;height:82px;border-radius:12px;object-fit:contain}.staff-section-label{margin:14px 13px 8px;color:#6f909c;font-size:10px;font-weight:800;letter-spacing:1.2px}.staff-nav{display:flex;flex-direction:column;gap:5px}.staff-nav a{display:flex;align-items:center;gap:12px;padding:12px 13px;border-radius:10px;color:#b9cbd1;font-size:13px;font-weight:700;text-decoration:none;transition:.2s}.staff-nav a i{width:20px;font-size:17px;text-align:center}.staff-nav a:hover,.staff-nav a.active{background:rgba(36,226,137,.13);color:#5af0a5}.staff-nav a.active{box-shadow:inset 3px 0 #27df89}.staff-sidebar-footer{margin-top:auto;padding:14px 10px;border-top:1px solid rgba(255,255,255,.09)}.staff-profile{display:flex;align-items:center;gap:10px}.staff-avatar{display:grid;place-items:center;width:38px;height:38px;border-radius:11px;background:#17bd71;color:#052d20;font-weight:900}.staff-profile strong,.staff-profile small{display:block}.staff-profile strong{font-size:12px}.staff-profile small{margin-top:2px;color:#789ba6;font-size:10px}.staff-logout{width:100%;margin-top:12px;border:0;background:transparent;color:#95aeb6;text-align:left;font-size:12px;font-weight:700}.staff-logout:hover{color:#fff}.staff-content{min-height:100vh;margin-left:260px}.staff-topbar{position:sticky;top:0;z-index:20;height:68px;display:flex;align-items:center;justify-content:space-between;padding:0 28px;border-bottom:1px solid var(--staff-border);background:rgba(255,255,255,.92);backdrop-filter:blur(12px)}.staff-topbar-title strong{display:block;font-size:15px}.staff-topbar-title span{color:var(--staff-muted);font-size:11px}.staff-topbar-actions{display:flex;align-items:center;gap:10px}.staff-topbar-actions a{display:grid;place-items:center;width:36px;height:36px;border:1px solid var(--staff-border);border-radius:10px;color:#41606c;text-decoration:none}.staff-main{width:min(1400px,100%);padding:26px 28px 40px}.staff-alert{margin-bottom:18px;border:0;border-radius:12px}.staff-menu-toggle{display:none;border:0;background:none;color:#173443;font-size:23px}.staff-page-title{margin-bottom:22px}.staff-page-title h1{margin:0;font-size:26px;font-weight:800}.staff-page-title p{margin:6px 0 0;color:var(--staff-muted);font-size:13px}.staff-card{overflow:hidden;border:1px solid var(--staff-border);border-radius:16px;background:#fff;box-shadow:0 8px 26px rgba(18,55,49,.05)}.staff-table{margin:0}.staff-table thead th{padding:13px 18px;border:0;background:#f7faf8;color:#688079;font-size:10px;font-weight:800;letter-spacing:.6px;text-transform:uppercase}.staff-table tbody td{padding:15px 18px;border-color:#edf2ef;vertical-align:middle;font-size:13px}.staff-badge{display:inline-flex;padding:6px 9px;border-radius:100px;background:#e6f7ef;color:#008a50;font-size:10px;font-weight:800}.staff-button{display:inline-flex;align-items:center;gap:7px;border:0;border-radius:9px;background:var(--staff-dark);color:#fff;padding:9px 13px;font-size:11px;font-weight:800;text-decoration:none}.staff-button:hover{background:#0d455d;color:#fff}.staff-button-primary{background:var(--staff-primary);color:#073421}.staff-button-primary:hover{background:#22d984;color:#073421}@media(max-width:900px){.staff-sidebar{transform:translateX(-100%);transition:.25s}.staff-sidebar.open{transform:translateX(0)}.staff-content{margin-left:0}.staff-menu-toggle{display:block}.staff-main{padding:20px 16px}.staff-topbar{padding:0 16px}}
    </style>
    @stack('styles')
</head>
<body>
<div class="staff-shell">
    <aside class="staff-sidebar" id="staffSidebar">
        <a class="staff-logo" href="{{ route('employee.dashboard') }}"><img src="{{ asset('images/logo.png') }}?v=4" alt="SmashZone"></a>
        <div class="staff-section-label">VẬN HÀNH</div>
        <nav class="staff-nav">
            @if(Auth::user()->hasPermission('employee.dashboard'))
            <a class="{{ request()->routeIs('employee.dashboard') ? 'active' : '' }}" href="{{ route('employee.dashboard') }}"><i class="bi bi-grid-1x2"></i>Tổng quan</a>
            @endif
            <a href="{{ route('courts.index') }}"><i class="bi bi-calendar3"></i>Lịch sân</a>
            @if(Auth::user()->hasPermission('courts.status.manage'))
            <a class="{{ request()->routeIs('employee.courts.*') ? 'active' : '' }}" href="{{ route('employee.courts.index') }}"><i class="bi bi-columns-gap"></i>Quản lý sân</a>
            @endif
        </nav>
        @if(Auth::user()->hasPermission('refunds.manage'))
        <div class="staff-section-label">KHÁCH HÀNG</div>
        <nav class="staff-nav">
            <a class="{{ request()->routeIs('employee.refund-requests.*') ? 'active' : '' }}" href="{{ route('employee.refund-requests.index') }}"><i class="bi bi-arrow-counterclockwise"></i>Hủy & hoàn tiền</a>
        </nav>
        @endif
        <div class="staff-sidebar-footer">
            <div class="staff-profile"><span class="staff-avatar">{{ Str::upper(Str::substr(Auth::user()->name, 0, 1)) }}</span><div><strong>{{ Str::limit(Auth::user()->name, 21) }}</strong><small>{{ Auth::user()->role === 'ADMIN' ? 'Quản trị viên' : 'Nhân viên' }}</small></div></div>
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="staff-logout"><i class="bi bi-box-arrow-left me-2"></i>Đăng xuất</button></form>
        </div>
    </aside>
    <div class="staff-content">
        <header class="staff-topbar">
            <div class="d-flex align-items-center gap-3"><button class="staff-menu-toggle" type="button" onclick="document.getElementById('staffSidebar').classList.toggle('open')"><i class="bi bi-list"></i></button><div class="staff-topbar-title"><strong>@yield('page_heading', 'Khu vực nhân viên')</strong><span>SmashZone Operations</span></div></div>
            <div class="staff-topbar-actions"><a href="{{ route('home') }}" title="Xem trang khách hàng"><i class="bi bi-box-arrow-up-right"></i></a><a href="#" title="Thông báo"><i class="bi bi-bell"></i></a></div>
        </header>
        <main class="staff-main">
            @if($errors->any())<div class="alert alert-danger staff-alert">{{ $errors->first() }}</div>@endif
            @if(session('success'))<div class="alert alert-success staff-alert">{{ session('success') }}</div>@endif
            @if(session('error'))<div class="alert alert-danger staff-alert">{{ session('error') }}</div>@endif
            @yield('content')
        </main>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="{{ asset('js/status-labels.js') }}?v=2"></script>
@stack('scripts')
</body>
</html>
