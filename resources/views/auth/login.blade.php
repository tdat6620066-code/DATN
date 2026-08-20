@extends('layouts.auth')
@section('title', 'Đăng nhập - SmashZone')
@section('content')
    <header class="auth-heading"><small>CHÀO MỪNG TRỞ LẠI</small>
        <h2>Đăng nhập</h2>
        <p>Nhập Email hoặc số điện thoại để tiếp tục sử dụng SmashZone.</p>
    </header>
    <form action="{{ route('login.store') }}" method="POST">@csrf
        <div class="auth-field"><label for="login">Email hoặc số điện thoại</label>
            <div class="auth-input-wrap"><i class="bi bi-person"></i><input class="@error('login') invalid @enderror"
                    id="login" name="login" value="{{ old('login') }}" placeholder="Email hoặc số điện thoại" required
                    autofocus></div>@error('login')
                    <div class="field-error">{{ $message }}</div>@enderror
        </div>
        <div class="auth-field"><label for="password">Mật khẩu</label>
            <div class="auth-input-wrap"><i class="bi bi-lock"></i><input class="@error('password') invalid @enderror"
                    id="password" name="password" type="password" placeholder="Nhập mật khẩu" required></div>
            @error('password')
            <div class="field-error">{{ $message }}</div>@enderror
        </div>
        <div class="auth-options"><label><input type="checkbox" name="remember" value="1">Ghi nhớ đăng nhập</label><a
                href="{{ route('password.request') }}">Quên mật khẩu?</a></div>
        <button class="auth-submit" type="submit">Đăng nhập <i class="bi bi-arrow-right"></i></button>
    </form>
    <p class="auth-switch">Chưa có tài khoản? <a href="{{ route('register') }}">Đăng ký ngay</a></p>
@endsection