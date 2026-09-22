<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'SmashZone - Đặt sân cầu lông')</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}?v=4">


    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/customer-layout-base.css') }}?v={{ filemtime(public_path('css/customer-layout-base.css')) }}">
    @stack('styles')
@include('partials.brand-theme')
</head>
<body class="sz-layout sz-layout--customer @yield('body_class')"><a class="sz-skip-link" href="#sz-main">Đến nội dung chính</a>
    @php($isBookingDetailPage = request()->routeIs('bookings.show'))

    @include('partials.site-header')

    <!-- Messages -->
    <div class="container">
        @if ($errors->has('daily_duration_confirmed'))
        @include('partials.daily-duration-confirmation')
        @endif

        @if (collect($errors->getMessages())->except('daily_duration_confirmed')->isNotEmpty())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <strong>Lỗi:</strong>
            <ul class="mb-0">
                @foreach (collect($errors->getMessages())->except('daily_duration_confirmed')->flatten() as $error)
                <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Đóng thông báo lỗi"></button>
        </div>
        @endif

        @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Đóng thông báo thành công"></button>
        </div>
        @endif

        @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Đóng thông báo lỗi"></button>
        </div>
        @endif
    </div>

    <!-- Content -->
    <main id="sz-main" tabindex="-1" class="{{ $isBookingDetailPage ? 'container my-4' : (request()->routeIs('home') ? 'sz-home-main' : 'container my-4') }}">
        @yield('content')
    </main>

    @include('partials.site-footer')
    @include('partials.ai-chatbot')
    @include('partials.customer-mobile-nav')


    <script src="{{ asset('js/status-labels.js') }}?v=2"></script>
    @stack('scripts')
</body>
</html>
