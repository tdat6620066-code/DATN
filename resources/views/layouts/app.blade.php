<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>@yield('title', 'SmashZone')</title>

    {{-- Bootstrap 5 --}}
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >
</head>

<body>

    {{-- NAVBAR --}}
    <nav class="navbar navbar-expand-lg bg-white shadow-sm">

        <div class="container">

            {{-- Logo --}}
            <a
                class="navbar-brand fw-bold"
                href="{{ route('home') }}"
            >
                SmashZone
            </a>


            {{-- Mobile button --}}
            <button
                class="navbar-toggler"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#mainNavbar"
            >
                <span class="navbar-toggler-icon"></span>
            </button>


            {{-- Navbar content --}}
            <div
                class="collapse navbar-collapse"
                id="mainNavbar"
            >

                {{-- Menu --}}
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">

                    <li class="nav-item">
                        <a
                            class="nav-link"
                            href="{{ route('home') }}"
                        >
                            Trang chủ
                        </a>
                    </li>

                    <li class="nav-item">
                        <a
                            class="nav-link"
                            href="#"
                        >
                            Danh sách sân
                        </a>
                    </li>

                    <li class="nav-item">
                        <a
                            class="nav-link"
                            href="#"
                        >
                            Giới thiệu
                        </a>
                    </li>

                </ul>


                {{-- Tài khoản --}}
                <div class="d-flex align-items-center gap-2">

                    @auth

                        <span class="text-muted">
                            Xin chào,
                            <strong>
                                {{ Auth::user()->name }}
                            </strong>
                        </span>

                        {{-- Đăng xuất --}}
                        <form
                            action="{{ route('logout') }}"
                            method="POST"
                            class="d-inline"
                        >

                            @csrf

                            <button
                                type="submit"
                                class="btn btn-outline-danger"
                            >
                                Đăng xuất
                            </button>

                        </form>

                    @else

                        <a
                            href="{{ route('login') }}"
                            class="btn btn-outline-primary"
                        >
                            Đăng nhập
                        </a>

                        <a
                            href="{{ route('register') }}"
                            class="btn btn-primary"
                        >
                            Đăng ký
                        </a>

                    @endauth

                </div>

            </div>

        </div>

    </nav>


    {{-- Thông báo --}}
    <div class="container mt-3">

        @if(session('success'))

            <div class="alert alert-success alert-dismissible fade show">

                {{ session('success') }}

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                ></button>

            </div>

        @endif

        @if(session('error'))

            <div class="alert alert-danger alert-dismissible fade show">

                {{ session('error') }}

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                ></button>

            </div>

        @endif

    </div>


    {{-- Nội dung từng trang --}}
    @yield('content')


    {{-- Bootstrap 5 --}}
    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
    </script>

</body>

</html>