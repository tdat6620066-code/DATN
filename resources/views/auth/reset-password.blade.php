@extends('layouts.auth')
@section('title', 'Đặt lại mật khẩu - SmashZone')
@section('content')
<header class="auth-heading"><small>KHÔI PHỤC MẬT KHẨU</small><h2>Đặt lại mật khẩu</h2><p>Tạo mật khẩu mới cho tài khoản của bạn.</p></header>
<form action="{{ route('password.update') }}" method="POST">@csrf
<input type="hidden" name="token" value="{{ $token }}">
<div class="auth-field"><label for="email">Địa chỉ email</label><div class="auth-input-wrap"><i class="bi bi-envelope"></i><input class="@error('email') invalid @enderror" id="email" name="email" type="email" value="{{ old('email', $email) }}" placeholder="name@example.com" required autofocus></div>@error('email')<div class="field-error">{{ $message }}</div>@enderror</div>
<div class="auth-field"><label for="password">Mật khẩu mới</label><div class="auth-input-wrap"><i class="bi bi-lock"></i><input class="@error('password') invalid @enderror" id="password" name="password" type="password" placeholder="Tối thiểu 8 ký tự" required></div>@error('password')<div class="field-error">{{ $message }}</div>@enderror</div>
<div class="auth-field"><label for="password_confirmation">Xác nhận mật khẩu</label><div class="auth-input-wrap"><i class="bi bi-shield-lock"></i><input id="password_confirmation" name="password_confirmation" type="password" placeholder="Nhập lại mật khẩu mới" required></div></div>
<button class="auth-submit" type="submit">Đặt lại mật khẩu <i class="bi bi-arrow-right"></i></button>
</form><p class="auth-switch">Đã nhớ mật khẩu? <a href="{{ route('login') }}">Quay lại đăng nhập</a></p>
@endsection