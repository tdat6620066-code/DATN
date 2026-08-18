<!DOCTYPE html>
<html lang="vi">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Tài khoản - SmashZone</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

</head>

<body class="bg-light">

<div class="container py-5">

    <div class="row justify-content-center">

        <div class="col-md-8">

            <div class="card shadow border-0">

                <div class="card-body p-4">

                    <h3 class="fw-bold mb-4">
                        Thông tin cá nhân
                    </h3>

                    @if (session('success'))

                        <div class="alert alert-success">
                            {{ session('success') }}
                        </div>

                    @endif

                    @if ($errors->any())

                        <div class="alert alert-danger">

                            @foreach ($errors->all() as $error)

                                <div>
                                    {{ $error }}
                                </div>

                            @endforeach

                        </div>

                    @endif

                    <form
                        method="POST"
                        action="{{ route('profile.update') }}"
                        enctype="multipart/form-data"
                    >

                        @csrf

                        @method('PUT')

                        <div class="text-center mb-4">

                            @if ($user->avatar)

                                <img
                                    src="{{ asset('storage/' . $user->avatar) }}"
                                    width="100"
                                    height="100"
                                    class="rounded-circle"
                                    style="object-fit: cover;"
                                >

                            @else

                                <div
                                    class="rounded-circle bg-secondary text-white d-inline-flex align-items-center justify-content-center"
                                    style="width:100px;height:100px;"
                                >
                                    {{ strtoupper(substr($user->name, 0, 1)) }}
                                </div>

                            @endif

                        </div>

                        <div class="mb-3">

                            <label class="form-label">
                                Ảnh đại diện
                            </label>

                            <input
                                type="file"
                                name="avatar"
                                class="form-control"
                            >

                        </div>

                        <div class="mb-3">

                            <label class="form-label">
                                Họ tên
                            </label>

                            <input
                                type="text"
                                name="name"
                                class="form-control"
                                value="{{ old('name', $user->name) }}"
                                required
                            >

                        </div>

                        <div class="mb-3">

                            <label class="form-label">
                                Email
                            </label>

                            <input
                                type="email"
                                class="form-control"
                                value="{{ $user->email }}"
                                disabled
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
                                value="{{ old('phone', $user->phone) }}"
                                required
                            >

                        </div>

                        <div class="mb-3">

                            <label class="form-label">
                                Địa chỉ
                            </label>

                            <textarea
                                name="address"
                                class="form-control"
                                rows="3"
                            >{{ old('address', $user->address) }}</textarea>

                        </div>

                        <button
                            type="submit"
                            class="btn btn-primary"
                        >
                            Lưu thay đổi
                        </button>

                        <a
                            href="{{ route('password.change') }}"
                            class="btn btn-outline-secondary"
                        >
                            Đổi mật khẩu
                        </a>

                    </form>

                </div>

            </div>

        </div>

    </div>

</div>

</body>

</html>