<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Đăng ký - SmashZone</title>

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

                    <h2 class="text-center fw-bold mb-2">
                        Tạo tài khoản
                    </h2>

                    <p class="text-center text-muted mb-4">
                        Đăng ký tài khoản SmashZone
                    </p>

                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form
                        action="{{ route('register.store') }}"
                        method="POST"
                    >

                        @csrf

                        <div class="mb-3">
                            <label class="form-label">
                                Họ tên
                            </label>

                            <input
                                type="text"
                                name="name"
                                class="form-control"
                                value="{{ old('name') }}"
                                placeholder="Nguyễn Văn A"
                                required
                            >
                        </div>

                        <div class="mb-3">
                            <label class="form-label">
                                Email
                            </label>

                            <input
                                type="email"
                                name="email"
                                class="form-control"
                                value="{{ old('email') }}"
                                placeholder="example@gmail.com"
                                required
                            >
                        </div>

                        <div class="mb-3">
                            <label class="form-label">
                                Số điện thoại
                            </label>

                            <input
                                type="text"
                                name="phone"
                                class="form-control"
                                value="{{ old('phone') }}"
                                placeholder="0987654321"
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
                                placeholder="Tối thiểu 8 ký tự"
                                required
                            >
                        </div>

                        <div class="mb-3">
                            <label class="form-label">
                                Xác nhận mật khẩu
                            </label>

                            <input
                                type="password"
                                name="password_confirmation"
                                class="form-control"
                                required
                            >
                        </div>

                        <div class="form-check mb-3">

                            <input
                                class="form-check-input"
                                type="checkbox"
                                name="terms"
                                value="1"
                                id="terms"
                                {{ old('terms') ? 'checked' : '' }}
                            >

                            <label
                                class="form-check-label"
                                for="terms"
                            >
                                Tôi đồng ý với
                                <a href="#">
                                    điều khoản sử dụng
                                </a>
                            </label>

                        </div>

                        <button
                            type="submit"
                            class="btn btn-primary w-100"
                        >
                            Đăng ký
                        </button>

                    </form>

                    <div class="text-center my-3">
                        <span class="text-muted">
                            Hoặc
                        </span>
                    </div>

                    <a
                        href="{{ route('google.redirect') }}"
                        class="btn btn-outline-dark w-100"
                    >
                        Đăng ký bằng Google
                    </a>

                    <div class="text-center mt-4">

                        Đã có tài khoản?

                        <a href="{{ route('login') }}">
                            Đăng nhập
                        </a>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>

</body>
</html>