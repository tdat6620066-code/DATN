<<<<<<< HEAD
<!DOCTYPE html>
<html lang="vi">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Đăng nhập - SmashZone</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

</head>

<body class="bg-light">

<div class="container py-5">

    <div class="row justify-content-center">

        <div class="col-md-6 col-lg-5">

            <div class="card border-0 shadow">

                <div class="card-body p-4">

                    <h2 class="text-center fw-bold">
                        Đăng nhập
                    </h2>

                    <p class="text-center text-muted mb-4">
                        Chào mừng bạn trở lại SmashZone
                    </p>

                    @if ($errors->any())

                        <div class="alert alert-danger">

                            @foreach ($errors->all() as $error)

                                <div>
                                    {{ $error }}
                                </div>

                            @endforeach

                        </div>

                    @endif

                    @if (session('success'))

                        <div class="alert alert-success">
                            {{ session('success') }}
                        </div>

                    @endif

                    <form
                        method="POST"
                        action="{{ route('login.store') }}"
                    >

                        @csrf

                        <div class="mb-3">

                            <label class="form-label">
                                Email hoặc số điện thoại
                            </label>

                            <input
                                type="text"
                                name="login"
                                class="form-control"
                                value="{{ old('login') }}"
                                required
                            >

                        </div>

                        <div class="mb-3">

                            <label class="form-label">
                                Mật khẩu
                            </label>

                            <input
                                type="password"
                                name="password"
                                class="form-control"
                                required
                            >

                        </div>

                        <div
                            class="d-flex justify-content-between mb-3"
                        >

                            <div class="form-check">

                                <input
                                    type="checkbox"
                                    name="remember"
                                    value="1"
                                    class="form-check-input"
                                    id="remember"
                                >

                                <label
                                    class="form-check-label"
                                    for="remember"
                                >
                                    Ghi nhớ đăng nhập
                                </label>

                            </div>

                            <a
                                href="{{ route('password.request') }}"
                            >
                                Quên mật khẩu?
                            </a>

                        </div>

                        <button
                            type="submit"
                            class="btn btn-primary w-100"
                        >
                            Đăng nhập
                        </button>

                    </form>

                    <div class="text-center my-3">
                        Hoặc
                    </div>

                    <a
                        href="{{ route('google.redirect') }}"
                        class="btn btn-outline-dark w-100"
                    >
                        Đăng nhập bằng Google
                    </a>

                    <div class="text-center mt-4">

                        Chưa có tài khoản?

                        <a href="{{ route('register') }}">
                            Đăng ký ngay
                        </a>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>

</body>
</html>
=======
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
>>>>>>> 9790fd584874111b4e4d91e45e981ae25b3deaae
