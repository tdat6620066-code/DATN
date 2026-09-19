<!DOCTYPE html>
<html lang="vi"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>@yield('title','Quản trị - SmashZone')</title><link rel="icon" href="{{ asset('images/logo.png') }}?v=4">
@stack('styles') @include('partials.brand-theme')
<link rel="stylesheet" href="{{ asset('css/admin-ui.css') }}?v={{ filemtime(public_path('css/admin-ui.css')) }}">
</head><body class="sz-layout sz-layout--admin"><a class="sz-skip-link" href="#sz-main">Đến nội dung chính</a>
<aside class="admin-sidebar offcanvas-lg offcanvas-start" tabindex="-1" id="adminSidebar" aria-label="Điều hướng quản trị">
<div class="d-flex align-items-center justify-content-between"><a class="admin-logo" href="{{ route('admin.dashboard') }}"><img src="{{ asset('images/logo.png') }}" alt="SmashZone"><span>ADMIN WORKSPACE</span></a><button class="btn-close d-lg-none" type="button" data-bs-dismiss="offcanvas" data-bs-target="#adminSidebar" aria-label="Đóng menu"></button></div>
@include('admin.partials.navigation')
<div class="admin-user"><strong>{{ auth()->user()->name }}</strong><small>Quản trị viên</small><form method="POST" action="{{ route('logout') }}">@csrf<button class="btn btn-outline-secondary btn-sm mt-2"><i class="bi bi-box-arrow-right me-2"></i>Đăng xuất</button></form></div></aside>
<div class="admin-content"><header class="admin-topbar"><button class="btn btn-light d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminSidebar" aria-controls="adminSidebar" aria-label="Mở menu"><i class="bi bi-list fs-4"></i></button>
<form class="admin-search" method="GET" action="{{ route('admin.bookings.index') }}" role="search"><i class="bi bi-search" aria-hidden="true"></i><input type="search" name="search" aria-label="Tìm booking, khách hàng" placeholder="Tìm mã booking, tên khách…"><button type="submit" class="btn btn-light btn-sm">Tìm</button></form>
<div class="admin-top-actions">@include('partials.notification-dropdown')<a class="admin-profile" href="{{ route('admin.users.edit', auth()->user()) }}"><span>{{ Str::upper(Str::substr(auth()->user()->name,0,1)) }}</span><strong>{{ auth()->user()->name }}</strong></a></div>
</header><main class="admin-main" id="sz-main" tabindex="-1"><div class="admin-breadcrumb">SmashZone <span>/</span> @yield('page_heading','Quản trị hệ thống')</div><x-admin.feedback />@yield('content')</main></div>
<x-admin.confirmation /><script src="{{ asset('js/status-labels.js') }}?v=2"></script><script defer src="{{ asset('js/admin-ui.js') }}?v={{ filemtime(public_path('js/admin-ui.js')) }}"></script>@stack('scripts')
</body></html>
