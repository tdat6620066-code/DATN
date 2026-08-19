@extends('layouts.auth')
@section('title', 'Xác thực Email - SmashZone')
@section('content')
<header class="auth-heading"><small>XÁC THỰC TÀI KHOẢN</small><h2>Xác thực Email</h2><p>Chúng tôi đã gửi một liên kết xác thực đến <strong>{{ auth()->user()->email }}</strong>.</p></header>
<p class="auth-verify-hint"><i class="bi bi-envelope-check"></i> Vui lòng kiểm tra hộp thư đến và nhấn vào liên kết xác thực để kích hoạt tài khoản.</p>
@if(session('success'))
<div class="auth-alert auth-alert-success">{{ session('success') }}</div>
@endif
<form action="{{ route('verification.send') }}" method="POST">@csrf
<button class="auth-submit" type="submit">Gửi lại Email xác thực <i class="bi bi-arrow-right"></i></button>
</form>
<form action="{{ route('logout') }}" method="POST" class="auth-switch">@csrf
<button type="submit" class="auth-link-btn">Đăng xuất và quay lại trang chủ</button>
</form>
<p class="auth-switch">Đã xác thực xong? <a href="{{ route('home') }}">Về trang chủ</a></p>
@endsection