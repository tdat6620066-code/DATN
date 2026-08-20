@extends('layouts.auth')
@section('title', 'Xác thực tài khoản - SmashZone')
@section('content')
    <header class="auth-heading"><small>KÍCH HOẠT TÀI KHOẢN</small>
        <h2>Nhập mã xác thực</h2>
        <p>Chúng tôi đã gửi một mã gồm 4 chữ số đến <strong>{{ $email }}</strong>.</p>
    </header>
    <p class="auth-verify-hint"><i class="bi bi-envelope-check"></i> Vui lòng kiểm tra hộp thư đến (kể cả Spam) và nhập mã
        để kích hoạt tài khoản.</p>
    @if(session('success'))
        <div class="auth-alert auth-alert-success">{{ session('success') }}</div>
    @endif
    <form action="{{ route('verification.code.verify') }}" method="POST">@csrf
        <div class="auth-field"><label for="code">Mã xác thực</label>
            <div class="auth-input-wrap"><i class="bi bi-shield-check"></i><input
                    style="letter-spacing:18px;font-weight:900;text-align:center;font-size:22px;"
                    class="@error('code') invalid @enderror" id="code" name="code" type="text" inputmode="numeric"
                    maxlength="4" pattern="[0-9]{4}" placeholder="••••" required autofocus></div>@error('code')
                    <div class="field-error">{{ $message }}</div>@enderror
        </div>
        <button class="auth-submit" type="submit">Kích hoạt tài khoản <i class="bi bi-arrow-right"></i></button>
    </form>
    <form action="{{ route('verification.code.resend') }}" method="POST">@csrf
        <button type="submit" class="auth-link-btn" style="margin-top:16px;">Gửi lại mã xác thực</button>
    </form>
    <p class="auth-switch">Đã có tài khoản? <a href="{{ route('login') }}">Đăng nhập</a></p>
@endsection