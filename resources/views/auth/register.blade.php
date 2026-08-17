@extends('layouts.auth')
@section('title', 'Đăng ký - SmashZone')
@section('content')
<header class="auth-heading"><small>BẮT ĐẦU CÙNG SMASHZONE</small><h2>Tạo tài khoản</h2><p>Đăng ký để đặt sân và quản lý lịch chơi của bạn.</p></header>
<form action="{{ route('register') }}" method="POST">@csrf
<div class="auth-field"><label for="name">Họ và tên</label><div class="auth-input-wrap"><i class="bi bi-person"></i><input class="@error('name') invalid @enderror" id="name" name="name" value="{{ old('name') }}" placeholder="Nguyễn Văn A" required autofocus></div>@error('name')<div class="field-error">{{ $message }}</div>@enderror</div>
<div class="auth-field"><label for="email">Địa chỉ email</label><div class="auth-input-wrap"><i class="bi bi-envelope"></i><input class="@error('email') invalid @enderror" id="email" name="email" type="email" value="{{ old('email') }}" placeholder="name@example.com" required></div>@error('email')<div class="field-error">{{ $message }}</div>@enderror</div>
<div class="auth-field"><label for="password">Mật khẩu</label><div class="auth-input-wrap"><i class="bi bi-lock"></i><input class="@error('password') invalid @enderror" id="password" name="password" type="password" placeholder="Tối thiểu 8 ký tự" required></div>@error('password')<div class="field-error">{{ $message }}</div>@enderror</div>
<div class="auth-field"><label for="password_confirmation">Xác nhận mật khẩu</label><div class="auth-input-wrap"><i class="bi bi-shield-lock"></i><input id="password_confirmation" name="password_confirmation" type="password" placeholder="Nhập lại mật khẩu" required></div></div>
<button class="auth-submit" type="submit">Tạo tài khoản <i class="bi bi-arrow-right"></i></button>
</form><p class="auth-switch">Đã có tài khoản? <a href="{{ route('login') }}">Đăng nhập</a></p>
@endsection
