<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'SmashZone - Đặt sân cầu lông')</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}?v=4">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary: #08b96b;
            --success: #08b96b;
            --danger: #ef4444;
            --warning: #f59e0b;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .navbar {
            background: linear-gradient(135deg, #082c3e 0%, #086052 100%);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            display: inline-flex;
            align-items: center;
            gap: 0.65rem;
        }

        .navbar-brand-logo {
            width: 180px;
            height: 58px;
            border-radius: 10px;
            object-fit: contain;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.25);
        }

        .navbar-brand > span {
            display: none;
        }
        
        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
        }
        
        .btn-primary:hover {
            background-color: #079957;
            border-color: #079957;
        }
        
        .badge-available {
            background-color: var(--success);
        }
        
        .badge-booked {
            background-color: var(--danger);
        }
        
        .badge-hold {
            background-color: var(--warning);
        }
        
        .badge-maintenance {
            background-color: #6b7280;
        }
        
        .court-card {
            transition: transform 0.3s, box-shadow 0.3s;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .court-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.15);
        }
        
        .court-image {
            height: 200px;
            object-fit: cover;
        }
        
        .banner-carousel {
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .banner-image {
            height: 400px;
            object-fit: cover;
        }
        
        .time-slot-btn {
            min-width: 120px;
            border-radius: 6px;
            transition: all 0.3s;
        }
        
        .time-slot-btn.available {
            background-color: #f0f9ff;
            border: 2px solid var(--primary);
            color: var(--primary);
        }
        
        .time-slot-btn.available:hover {
            background-color: var(--primary);
            color: white;
        }
        
        .time-slot-btn.selected {
            background-color: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .time-slot-btn:disabled {
            background-color: #e5e7eb;
            border-color: #d1d5db;
            color: #9ca3af;
            cursor: not-allowed;
        }
        
        .booking-summary {
            background-color: #f9fafb;
            border-radius: 8px;
            padding: 20px;
        }
        
        .summary-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .summary-total {
            display: flex;
            justify-content: space-between;
            padding: 15px 0;
            font-weight: 700;
            font-size: 1.1rem;
            color: var(--primary);
            border-top: 2px solid var(--primary);
        }
        
        .alert-message {
            border-radius: 6px;
            margin-bottom: 20px;
        }
        
        footer {
            background-color: #1f2937;
            color: white;
            padding: 40px 0 20px;
            margin-top: 60px;
        }
        
        .form-control, .form-select {
            border-radius: 6px;
            border: 1px solid #d1d5db;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(8, 185, 107, 0.2);
        }
        
        .rating {
            color: #fbbf24;
        }
        
        .review-card {
            background-color: #f9fafb;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
        }
    </style>
    @stack('styles')
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="/">
                <img class="navbar-brand-logo" src="{{ asset('images/logo.png') }}?v=4" alt="Logo SmashZone">
                <span>SmashZone</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="/">Trang chủ</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/courts">Danh sách sân</a>
                    </li>
                    @auth
                    @if((Auth::user()->role ?: 'CUSTOMER') === 'CUSTOMER')
                    <li class="nav-item">
                        <a class="nav-link" href="/bookings">Đặt sân của tôi</a>
                    </li>
                    @elseif(Auth::user()->role === 'EMPLOYEE')
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('employee.dashboard') }}">Tổng quan</a>
                    </li>
                    @if(Auth::user()->hasPermission('refunds.manage'))
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('employee.refund-requests.index') }}">Xử lý hoàn tiền</a>
                    </li>
                    @endif
                    @elseif(Auth::user()->role === 'ADMIN')
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.dashboard') }}">Quản trị hệ thống</a>
                    </li>
                    @endif
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i> {{ Auth::user()->name }}
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="navbarDropdown">
                            <li><a class="dropdown-item" href="/profile">Hồ sơ</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form action="/logout" method="POST" style="display:inline;">
                                    @csrf
                                    <button type="submit" class="dropdown-item">Đăng xuất</button>
                                </form>
                            </li>
                        </ul>
                    </li>
                    @else
                    <li class="nav-item">
                        <a class="nav-link" href="/login">Đăng nhập</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/register">Đăng ký</a>
                    </li>
                    @endauth
                </ul>
            </div>
        </div>
    </nav>

    <!-- Messages -->
    <div class="container mt-4">
        @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <strong>Lỗi:</strong>
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        @endif

        @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        @endif

        @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        @endif
    </div>

    <!-- Content -->
    <main class="container my-4">
        @yield('content')
    </main>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <h5>SmashZone</h5>
                    <p>Nền tảng đặt sân cầu lông hàng đầu tại Việt Nam</p>
                </div>
                <div class="col-md-4 mb-4">
                    <h5>Liên hệ</h5>
                    <p>
                        Email: info@smashzone.vn<br>
                        Điện thoại: (84) 0123 456 789
                    </p>
                </div>
                <div class="col-md-4 mb-4">
                    <h5>Theo dõi</h5>
                    <a href="#" class="text-white me-3"><i class="bi bi-facebook"></i></a>
                    <a href="#" class="text-white me-3"><i class="bi bi-twitter"></i></a>
                    <a href="#" class="text-white"><i class="bi bi-instagram"></i></a>
                </div>
            </div>
            <hr>
            <div class="text-center">
                <p class="mb-0">&copy; 2024 SmashZone. Tất cả quyền được bảo lưu.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ asset('js/status-labels.js') }}?v=2"></script>
    @stack('scripts')
</body>
</html>
