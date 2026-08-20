@extends('layouts.auth')
@section('title', 'Quên mật khẩu - SmashZone')
@section('content')
    <header class="auth-heading"><small>KHÔI PHỤC TÀI KHOẢN</small>
        <h2>Quên mật khẩu</h2>
        <p>Nhập Email của bạn, chúng tôi sẽ gửi liên kết đặt lại mật khẩu.</p>
    </header>
    <form action="{{ route('password.email') }}" method="POST">@csrf
        <div class="auth-field"><label for="email">Địa chỉ email</label>
            <div class="auth-input-wrap"><i class="bi bi-envelope"></i><input class="@error('email') invalid @enderror"
                    id="email" name="email" type="email" value="{{ old('email') }}" placeholder="name@example.com" required
                    autofocus></div>@error('email')
                    <div class="field-error">{{ $message }}</div>@enderror
        </div>
        <button class="auth-submit" type="submit">Gửi liên kết <i class="bi bi-arrow-right"></i></button>
    </form>
    <p class="auth-switch">Đã nhớ mật khẩu? <a href="{{ route('login') }}">Quay lại đăng nhập</a></p>
@endsection