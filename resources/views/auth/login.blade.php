@extends('layouts.auth')
@section('title', 'Đăng nhập - SmashZone')
@section('content')
<header class="auth-heading"><small>CHÀO MỪNG TRỞ LẠI</small><h2>Đăng nhập</h2><p>Nhập thông tin tài khoản để tiếp tục sử dụng SmashZone.</p></header>
<form action="{{ route('login') }}" method="POST">@csrf
<div class="auth-field"><label for="email">Địa chỉ email</label><div class="auth-input-wrap"><i class="bi bi-envelope"></i><input class="@error('email') invalid @enderror" id="email" name="email" type="email" value="{{ old('email') }}" placeholder="name@example.com" required autofocus></div>@error('email')<div class="field-error">{{ $message }}</div>@enderror</div>
<div class="auth-field"><label for="password">Mật khẩu</label><div class="auth-input-wrap"><i class="bi bi-lock"></i><input class="@error('password') invalid @enderror" id="password" name="password" type="password" placeholder="Nhập mật khẩu" required></div>@error('password')<div class="field-error">{{ $message }}</div>@enderror</div>
<div class="auth-options"><label><input type="checkbox" name="remember">Ghi nhớ đăng nhập</label></div>
<button class="auth-submit" type="submit">Đăng nhập <i class="bi bi-arrow-right"></i></button>
</form><p class="auth-switch">Chưa có tài khoản? <a href="{{ route('register') }}">Đăng ký ngay</a></p>
@endsection
