<!DOCTYPE html>
<html lang="vi">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Xác thực Email - SmashZone</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

</head>

<body class="bg-light">

<div class="container py-5">

    <div class="row justify-content-center">

        <div class="col-md-6">

            <div class="card border-0 shadow">

                <div class="card-body p-5 text-center">

                    <h3 class="fw-bold mb-3">
                        Xác thực Email
                    </h3>

                    <p class="text-muted">

                        Chúng tôi đã gửi một liên kết xác thực
                        đến:

                    </p>

                    <strong>
                        {{ auth()->user()->email }}
                    </strong>

                    <p class="text-muted mt-3">

                        Vui lòng kiểm tra hộp thư và nhấn
                        vào liên kết xác thực.

                    </p>

                    @if (session('success'))

                        <div class="alert alert-success">
                            {{ session('success') }}
                        </div>

                    @endif

                    <form
                        method="POST"
                        action="{{ route('verification.send') }}"
                    >

                        @csrf

                        <button
                            type="submit"
                            class="btn btn-primary"
                        >
                            Gửi lại Email xác thực
                        </button>

                    </form>

                    <form
                        method="POST"
                        action="{{ route('logout') }}"
                        class="mt-3"
                    >

                        @csrf

                        <button
                            type="submit"
                            class="btn btn-link"
                        >
                            Đăng xuất
                        </button>

                    </form>

                </div>

            </div>

        </div>

    </div>

</div>

</body>

</html>