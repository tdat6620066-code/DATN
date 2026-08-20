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

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
        rel="stylesheet"
    >

    <style>
        :root {
            --brand: #0ea36b;
            --brand-dark: #0b8a5a;
            --brand-soft: #e8f9f1;
            --ink: #0f172a;
            --muted: #64748b;
            --line: #e2e8f0;
            --navy: #082635;
        }

        body {
            background: #f2f6f4;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
        }

        .profile-shell {
            width: min(1180px, calc(100% - 32px));
            margin: 28px auto 0;
        }

        .profile-layout {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 26px;
            align-items: start;
        }

        .profile-sidebar {
            position: sticky;
            top: 90px;
            background: var(--navy);
            color: #fff;
            border-radius: 16px;
            padding: 22px 16px;
            box-shadow: 0 16px 40px rgba(8, 38, 53, .18);
        }

        .profile-avatar {
            display: grid;
            place-items: center;
            width: 74px;
            height: 74px;
            margin: 0 auto 12px;
            border-radius: 50%;
            background: var(--brand);
            color: #fff;
            font-size: 28px;
            font-weight: 800;
            overflow: hidden;
        }

        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-name {
            text-align: center;
            font-weight: 700;
            font-size: 17px;
            margin-bottom: 2px;
        }

        .profile-email {
            text-align: center;
            color: #9db4ae;
            font-size: 12px;
            margin-bottom: 18px;
            word-break: break-word;
        }

        .profile-nav {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .profile-nav a,
        .profile-nav button {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 11px 12px;
            border: 0;
            border-radius: 10px;
            background: transparent;
            color: #d5e2e5;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            text-align: left;
            cursor: pointer;
            transition: .2s;
        }

        .profile-nav a:hover,
        .profile-nav button:hover {
            background: rgba(255, 255, 255, .12);
            color: #fff;
        }

        .profile-nav a.active {
            background: var(--brand);
            color: #fff;
        }

        .profile-nav i {
            width: 18px;
            text-align: center;
            color: #5eead4;
        }

        .profile-nav a.active i {
            color: #fff;
        }

        .profile-panel {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 10px 30px rgba(8, 38, 53, .06);
        }

        .panel-heading {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            font-size: 18px;
            font-weight: 800;
            color: var(--ink);
        }

        .panel-heading i {
            color: var(--brand);
        }

        .filter-bar {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr auto;
            gap: 12px;
            margin-bottom: 18px;
            align-items: end;
        }

        .filter-item label {
            display: block;
            margin-bottom: 5px;
            color: var(--muted);
            font-size: 11px;
            font-weight: 700;
        }

        .filter-item input,
        .filter-item select {
            width: 100%;
            height: 40px;
            padding: 0 11px;
            border: 1px solid var(--line);
            border-radius: 9px;
            background: #fbfdfc;
            color: var(--ink);
            font-size: 13px;
            outline: none;
        }

        .filter-item input:focus,
        .filter-item select:focus {
            border-color: var(--brand);
            box-shadow: 0 0 0 3px rgba(14, 163, 107, .12);
            background: #fff;
        }

        .filter-bar .btn-filter {
            height: 40px;
            border: 0;
            border-radius: 9px;
            background: var(--brand);
            color: #fff;
            font-weight: 700;
            padding: 0 16px;
            cursor: pointer;
        }

        .filter-bar .btn-filter:hover {
            background: var(--brand-dark);
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
        }

        .status-badge.pending { background: #fef3c7; color: #b45309; }
        .status-badge.confirmed { background: #e0f2fe; color: #0369a1; }
        .status-badge.checked_in { background: #e0f2fe; color: #0369a1; }
        .status-badge.completed { background: #dcfce7; color: #15803d; }
        .status-badge.cancelled { background: #fee2e2; color: #b91c1c; }
        .status-badge.expired { background: #e2e8f0; color: #475569; }

        .booking-history-item {
            padding: 16px;
            border: 1px solid var(--line);
            border-radius: 12px;
            margin-bottom: 14px;
            background: #fff;
        }

        .booking-history-item:hover {
            border-color: #bfe8d4;
            background: #fbfefc;
        }

        .bh-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 10px;
        }

        .bh-code {
            font-family: Consolas, Monaco, monospace;
            font-weight: 700;
            color: var(--ink);
        }

        .bh-meta {
            color: var(--muted);
            font-size: 12px;
        }

        .bh-courts {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 12px;
        }

        .bh-court-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            border-radius: 8px;
            background: var(--brand-soft);
            color: #15803d;
            font-size: 12px;
            font-weight: 600;
        }

        .bh-bottom {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .bh-total {
            font-weight: 800;
            color: var(--brand-dark);
        }

        .bh-link {
            color: var(--brand);
            font-size: 12px;
            font-weight: 700;
            text-decoration: none;
        }

        .bh-link:hover {
            color: var(--brand-dark);
        }

        .empty-state {
            text-align: center;
            padding: 40px 16px;
            color: var(--muted);
        }

        .empty-state i {
            display: block;
            font-size: 40px;
            color: var(--brand);
            margin-bottom: 12px;
        }

        @media (max-width: 820px) {
            .profile-layout { grid-template-columns: 1fr; }
            .profile-sidebar { position: static; }
            .filter-bar { grid-template-columns: 1fr 1fr; }
        }

        @media (max-width: 560px) {
            .filter-bar { grid-template-columns: 1fr; }
        }
    </style>

</head>

<body>

@include('partials.site-header')

<div class="profile-shell">

    <div class="profile-layout">

        <aside class="profile-sidebar">
            <div class="profile-avatar">
                @if ($user->avatar)
                    <img src="{{ asset('storage/' . $user->avatar) }}" alt="{{ $user->name }}">
                @else
                    {{ strtoupper(substr($user->name, 0, 1)) }}
                @endif
            </div>
            <div class="profile-name">{{ $user->name }}</div>
            <div class="profile-email">{{ $user->email }}</div>

            <nav class="profile-nav">
                <a href="#account" class="active" data-target="account">
                    <i class="bi bi-person"></i> Thông tin tài khoản
                </a>
                <a href="#history" data-target="history">
                    <i class="bi bi-clock-history"></i> Lịch sử đặt sân
                </a>
                <a href="#password" data-target="password">
                    <i class="bi bi-shield-lock"></i> Đổi mật khẩu
                </a>
            </nav>
        </aside>

        <section class="profile-panel">

            @if (session('success'))
                <div class="alert alert-success">
                    {{ session('success') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <div id="account">
                <div class="panel-heading">
                    <i class="bi bi-person-fill"></i> Thông tin tài khoản
                </div>

                <form
                    method="POST"
                    action="{{ route('profile.update') }}"
                    enctype="multipart/form-data"
                >

                    @csrf

                    @method('PUT')

                    <div class="mb-3">
                        <label class="form-label">Ảnh đại diện</label>
                        <input
                            type="file"
                            name="avatar"
                            class="form-control"
                        >
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Họ tên</label>
                        <input
                            type="text"
                            name="name"
                            class="form-control"
                            value="{{ old('name', $user->name) }}"
                            required
                        >
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input
                            type="email"
                            class="form-control"
                            value="{{ $user->email }}"
                            disabled
                        >
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Số điện thoại</label>
                        <input
                            type="text"
                            name="phone"
                            class="form-control"
                            value="{{ old('phone', $user->phone) }}"
                            required
                        >
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Địa chỉ</label>
                        <textarea
                            name="address"
                            class="form-control"
                            rows="3"
                        >{{ old('address', $user->address) }}</textarea>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        Lưu thay đổi
                    </button>

                </form>
            </div>

            <div id="history" style="display: none;">
                <div class="panel-heading">
                    <i class="bi bi-clock-history"></i> Lịch sử đặt sân
                </div>

                <form method="GET" action="{{ route('profile') }}#history" class="filter-bar">
                    <div class="filter-item">
                        <label for="status">Trạng thái</label>
                        <select name="status" id="status">
                            <option value="">Tất cả trạng thái</option>
                            <option value="PENDING_PAYMENT" @selected($filters['status'] === 'PENDING_PAYMENT')>Chờ thanh toán</option>
                            <option value="CONFIRMED" @selected($filters['status'] === 'CONFIRMED')>Đã xác nhận</option>
                            <option value="CHECKED_IN" @selected($filters['status'] === 'CHECKED_IN')>Đã nhận sân</option>
                            <option value="COMPLETED" @selected($filters['status'] === 'COMPLETED')>Đã hoàn thành</option>
                            <option value="CANCELLED" @selected($filters['status'] === 'CANCELLED')>Đã hủy</option>
                            <option value="EXPIRED" @selected($filters['status'] === 'EXPIRED')>Đã hết hạn</option>
                        </select>
                    </div>
                    <div class="filter-item">
                        <label for="date_from">Từ ngày</label>
                        <input type="date" name="date_from" id="date_from" value="{{ $filters['date_from'] }}">
                    </div>
                    <div class="filter-item">
                        <label for="date_to">Đến ngày</label>
                        <input type="date" name="date_to" id="date_to" value="{{ $filters['date_to'] }}">
                    </div>
                    <button type="submit" class="btn-filter">
                        <i class="bi bi-funnel"></i> Lọc
                    </button>
                </form>

                @php
                    $statusMap = [
                        'PENDING_PAYMENT' => ['pending', 'Chờ thanh toán'],
                        'CONFIRMED' => ['confirmed', 'Đã xác nhận'],
                        'CHECKED_IN' => ['checked_in', 'Đã nhận sân'],
                        'COMPLETED' => ['completed', 'Đã hoàn thành'],
                        'CANCELLED' => ['cancelled', 'Đã hủy'],
                        'EXPIRED' => ['expired', 'Đã hết hạn'],
                    ];
                @endphp

                @if ($bookings->count() > 0)
                    @foreach ($bookings as $booking)
                        @php
                            [$sbClass, $sbText] = $statusMap[$booking->status] ?? ['expired', $booking->status];
                            $bhSubtotal = $booking->bookingDetails->sum('subtotal');
                            $bhDiscount = $booking->discount ?? 0;
                            $bhTotal = $bhSubtotal - $bhDiscount;
                        @endphp
                        <article class="booking-history-item">
                            <div class="bh-top">
                                <div>
                                    <div class="bh-code">{{ $booking->booking_code }}</div>
                                    <div class="bh-meta">
                                        <i class="bi bi-calendar3"></i> {{ $booking->created_at->format('d/m/Y H:i') }}
                                    </div>
                                </div>
                                <span class="status-badge {{ $sbClass }}">{{ $sbText }}</span>
                            </div>

                            <div class="bh-courts">
                                @foreach ($booking->bookingDetails as $detail)
                                    <span class="bh-court-chip">
                                        <i class="bi bi-dribbble"></i>
                                        {{ $detail->court->name }} ·
                                        {{ $detail->booking_date->format('d/m/Y') }} ·
                                        {{ $detail->timeSlot->name }}
                                    </span>
                                @endforeach
                            </div>

                            <div class="bh-bottom">
                                <span class="bh-total">
                                    {{ number_format($bhTotal, 0, ',', '.') }} VNĐ
                                </span>
                                <a class="bh-link" href="{{ route('bookings.show', $booking) }}?from=profile">
                                    Xem chi tiết <i class="bi bi-arrow-right"></i>
                                </a>
                            </div>
                        </article>
                    @endforeach

                    <div class="d-flex justify-content-center mt-4">
                        {{ $bookings->links() }}
                    </div>
                @else
                    <div class="empty-state">
                        <i class="bi bi-inbox"></i>
                        <h5>Không có đơn đặt sân nào</h5>
                        <p class="mb-0">Hãy đặt sân ngay để bắt đầu trải nghiệm.</p>
                    </div>
                @endif
            </div>

            <div id="password" style="display: none;">
                <div class="panel-heading">
                    <i class="bi bi-shield-lock"></i> Đổi mật khẩu
                </div>

                <form method="POST" action="{{ route('password.change.update') }}">
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label class="form-label">Mật khẩu hiện tại</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Mật khẩu mới</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Xác nhận mật khẩu mới</label>
                        <input type="password" name="password_confirmation" class="form-control" required>
                    </div>

                    <button type="submit" class="btn btn-primary">Đổi mật khẩu</button>
                </form>
            </div>

        </section>

    </div>

</div>

@include('partials.site-footer')

<script>
    const navLinks = document.querySelectorAll('.profile-nav a[data-target]');
    const panels = {
        account: document.getElementById('account'),
        history: document.getElementById('history'),
        password: document.getElementById('password')
    };

    function activateProfileTab(target) {
        if (!panels[target]) return;

        navLinks.forEach(l => {
            l.classList.toggle('active', l.dataset.target === target);
        });

        Object.keys(panels).forEach(key => {
            panels[key].style.display = (key === target) ? 'block' : 'none';
        });
    }

    navLinks.forEach(link => {
        link.addEventListener('click', (event) => {
            event.preventDefault();
            history.pushState(null, '', link.getAttribute('href'));
            activateProfileTab(link.dataset.target);
        });
    });

    const initialHash = window.location.hash.replace('#', '');
    activateProfileTab(initialHash || 'account');
</script>

</body>

</html>