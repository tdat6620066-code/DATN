<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
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

        /* Dropdown tài khoản hiển thị khi hover */
        .nav-item.dropdown:hover .dropdown-menu {
            display: block;
            margin-top: 0;
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
    @include('partials.site-header')

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

    @include('partials.site-footer')

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ asset('js/status-labels.js') }}?v=2"></script>
    @stack('scripts')
</body>
</html>
