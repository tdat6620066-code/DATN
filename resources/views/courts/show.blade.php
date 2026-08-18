<<<<<<< HEAD
```blade
@extends('layouts.app')

@section('title', $court->name . ' - SmashZone')

@section('content')

<div class="container py-5">

    {{-- =========================================================
         FLASH MESSAGE
    ========================================================== --}}

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            {{ session('success') }}

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
            ></button>
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
            {{ session('error') }}

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
            ></button>
        </div>
    @endif


    {{-- =========================================================
         VALIDATION ERRORS
    ========================================================== --}}

    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">

            <strong>Có lỗi xảy ra:</strong>

            <ul class="mb-0 mt-2">

                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach

            </ul>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
            ></button>

        </div>
    @endif


    {{-- =========================================================
         BREADCRUMB
    ========================================================== --}}

    <nav aria-label="breadcrumb" class="mb-4">

        <ol class="breadcrumb">

            <li class="breadcrumb-item">
                <a
                    href="{{ route('home') }}"
                    class="text-decoration-none"
                >
                    Trang chủ
                </a>
            </li>

            <li class="breadcrumb-item">
                <a
                    href="{{ route('courts.index') }}"
                    class="text-decoration-none"
                >
                    Danh sách sân
                </a>
            </li>

            <li
                class="breadcrumb-item active"
                aria-current="page"
            >
                {{ $court->name }}
            </li>

        </ol>

    </nav>


    {{-- =========================================================
         COURT DETAIL
    ========================================================== --}}

    <div class="row g-4">


        {{-- =====================================================
             IMAGE
        ====================================================== --}}

        <div class="col-lg-7">

            <div class="card border-0 shadow-sm overflow-hidden">

                @if (!empty($court->image))

                    <img
                        src="{{ asset('storage/' . $court->image) }}"
                        alt="{{ $court->name }}"
                        class="w-100"
                        style="
                            height: 450px;
                            object-fit: cover;
                        "
                    >

                @elseif (!empty($court->thumbnail))

                    <img
                        src="{{ $court->thumbnail }}"
                        alt="{{ $court->name }}"
                        class="w-100"
                        style="
                            height: 450px;
                            object-fit: cover;
                        "
                    >

                @else

                    <div
                        class="bg-light d-flex align-items-center justify-content-center"
                        style="height:450px;"
                    >

                        <div class="text-center text-muted">

                            <div
                                style="font-size:60px;"
                            >
                                🏸
                            </div>

                            <p class="mb-0">
                                Chưa có hình ảnh sân
                            </p>

                        </div>

                    </div>

                @endif

            </div>

        </div>


        {{-- =====================================================
             INFORMATION
        ====================================================== --}}

        <div class="col-lg-5">

            <div class="card border-0 shadow-sm h-100">

                <div class="card-body p-4">


                    {{-- =================================================
                         STATUS
                    ================================================== --}}

                    @if (
                        isset($court->status)
                        && (
                            $court->status === 'ACTIVE'
                            || $court->status === 1
                            || $court->status === 'active'
                        )
                    )

                        <span class="badge bg-success mb-3">
                            Đang hoạt động
                        </span>

                    @elseif (isset($court->status))

                        <span class="badge bg-secondary mb-3">
                            Tạm ngưng
                        </span>

                    @else

                        <span class="badge bg-success mb-3">
                            Đang hoạt động
                        </span>

                    @endif


                    {{-- =================================================
                         COURT NAME
                    ================================================== --}}

                    <h1 class="fw-bold mb-3">

                        {{ $court->name }}

                    </h1>


                    {{-- =================================================
                         PRICE
                    ================================================== --}}

                    @if (isset($court->price))

                        <div class="mb-4">

                            <span
                                class="text-danger fw-bold"
                                style="font-size:28px;"
                            >

                                {{ number_format($court->price, 0, ',', '.') }}

                                đ

                            </span>

                            <span class="text-muted">
                                / giờ
                            </span>

                        </div>

                    @endif


                    {{-- =================================================
                         LOCATION
                    ================================================== --}}

                    @if (!empty($court->address))

                        <div class="d-flex mb-3">

                            <div
                                class="me-3"
                                style="font-size:22px;"
                            >
                                📍
                            </div>

                            <div>

                                <div class="fw-semibold">
                                    Địa chỉ
                                </div>

                                <div class="text-muted">
                                    {{ $court->address }}
                                </div>

                            </div>

                        </div>

                    @endif


                    {{-- =================================================
                         DESCRIPTION
                    ================================================== --}}

                    @if (!empty($court->description))

                        <div class="mb-4">

                            <h5 class="fw-bold">
                                Giới thiệu
                            </h5>

                            <p class="text-muted mb-0">

                                {{ $court->description }}

                            </p>

                        </div>

                    @endif


                    <hr>


                    {{-- =================================================
                         ACTION BUTTONS
                    ================================================== --}}

                    <div class="d-grid gap-2 mt-4">


                        {{-- =============================================
                             BOOKING
                        ============================================== --}}

                        @auth

                            @if (
                                !isset($court->status)
                                || $court->status === 'ACTIVE'
                                || $court->status === 1
                                || $court->status === 'active'
                            )

                                <a
                                    href="{{ url('/booking/create?court_id=' . $court->id) }}"
                                    class="btn btn-primary btn-lg"
                                >

                                    🏸
                                    Đặt sân ngay

                                </a>

                            @else

                                <button
                                    type="button"
                                    class="btn btn-secondary btn-lg"
                                    disabled
                                >

                                    Sân hiện không hoạt động

                                </button>

                            @endif

                        @else

                            <a
                                href="{{ route('login') }}"
                                class="btn btn-primary btn-lg"
                            >

                                🏸
                                Đăng nhập để đặt sân

                            </a>

                        @endauth


                        {{-- =============================================
                             FAVORITE
                        ============================================== --}}

                        @auth

                            @php

                                $isFavorite = auth()
                                    ->user()
                                    ->favorites()
                                    ->where(
                                        'court_id',
                                        $court->id
                                    )
                                    ->exists();

                            @endphp


                            @if ($isFavorite)

                                <form
                                    method="POST"
                                    action="{{ route(
                                        'favorites.destroy',
                                        $court
                                    ) }}"
                                >

                                    @csrf

                                    @method('DELETE')

                                    <button
                                        type="submit"
                                        class="btn btn-outline-danger btn-lg w-100"
                                    >

                                        ♥
                                        Bỏ yêu thích

                                    </button>

                                </form>

                            @else

                                <form
                                    method="POST"
                                    action="{{ route(
                                        'favorites.store',
                                        $court
                                    ) }}"
                                >

                                    @csrf

                                    <button
                                        type="submit"
                                        class="btn btn-outline-danger btn-lg w-100"
                                    >

                                        ♡
                                        Thêm vào yêu thích

                                    </button>

                                </form>

                            @endif

                        @else

                            <a
                                href="{{ route('login') }}"
                                class="btn btn-outline-danger btn-lg"
                            >

                                ♡
                                Đăng nhập để yêu thích

                            </a>

                        @endauth

                    </div>

                </div>

            </div>

        </div>

    </div>


    {{-- =========================================================
         ADDITIONAL INFORMATION
    ========================================================== --}}

    <div class="row mt-5">

        <div class="col-12">

            <div class="card border-0 shadow-sm">

                <div class="card-body p-4">

                    <h3 class="fw-bold mb-4">
                        Thông tin sân
                    </h3>

                    <div class="row g-4">


                        {{-- COURT ID --}}

                        <div class="col-md-4">

                            <div class="border rounded p-3 h-100">

                                <small class="text-muted">
                                    Mã sân
                                </small>

                                <div class="fw-bold mt-1">
                                    #{{ $court->id }}
                                </div>

                            </div>

                        </div>


                        {{-- PRICE --}}

                        @if (isset($court->price))

                            <div class="col-md-4">

                                <div class="border rounded p-3 h-100">

                                    <small class="text-muted">
                                        Giá thuê
                                    </small>

                                    <div
                                        class="fw-bold text-danger mt-1"
                                    >

                                        {{ number_format(
                                            $court->price,
                                            0,
                                            ',',
                                            '.'
                                        ) }}

                                        đ / giờ

                                    </div>

                                </div>

                            </div>

                        @endif


                        {{-- STATUS --}}

                        <div class="col-md-4">

                            <div class="border rounded p-3 h-100">

                                <small class="text-muted">
                                    Trạng thái
                                </small>

                                <div class="fw-bold mt-1">

                                    @if (
                                        !isset($court->status)
                                        || $court->status === 'ACTIVE'
                                        || $court->status === 1
                                        || $court->status === 'active'
                                    )

                                        <span class="text-success">
                                            Đang hoạt động
                                        </span>

                                    @else

                                        <span class="text-secondary">
                                            Tạm ngưng
                                        </span>

                                    @endif

                                </div>

                            </div>

                        </div>


                        {{-- ADDRESS --}}

                        @if (!empty($court->address))

                            <div class="col-md-12">

                                <div class="border rounded p-3">

                                    <small class="text-muted">
                                        Địa chỉ sân
                                    </small>

                                    <div class="mt-1">

                                        📍
                                        {{ $court->address }}

                                    </div>

                                </div>

                            </div>

                        @endif

                    </div>

                </div>

            </div>

        </div>

    </div>


    {{-- =========================================================
         BACK BUTTON
    ========================================================== --}}

    <div class="mt-4">

        <a
            href="{{ route('courts.index') }}"
            class="btn btn-outline-secondary"
        >

            ← Quay lại danh sách sân

        </a>

    </div>

</div>

@endsection


{{-- =============================================================
     CUSTOM CSS
============================================================= --}}

@push('styles')

<style>

    .card {
        border-radius: 16px;
    }

    .btn {
        border-radius: 10px;
    }

    .breadcrumb a {
        color: #0d6efd;
    }

    .breadcrumb-item.active {
        color: #6c757d;
    }

</style>

@endpush
```
=======
@extends('layouts.app')

@section('title', $court->name . ' - Đặt sân')

@push('styles')
<style>
    body:has(.court-schedule-page) { background: #f4f8f0; color: #102030; }
    body:has(.court-schedule-page) .navbar, body:has(.court-schedule-page) footer, body:has(.court-schedule-page) .container.mt-4:not(main) { display: none; }
    body:has(.court-schedule-page) main.container { max-width: none; margin: 0 !important; padding: 0; }
    .court-schedule-page { min-height: 100vh; font-family: "Segoe UI", sans-serif; }
    .schedule-topbar { height: 82px; display: flex; align-items: center; gap: 24px; padding: 0 clamp(20px, 6.5vw, 125px); background: #fff; border-bottom: 1px solid #d6ddd3; }
    .schedule-topbar h1 { margin: 0; font-size: 24px; font-weight: 750; }.back-link { color: #102030; font-size: 27px; line-height: 1; text-decoration: none; }.info-button { margin-left: auto; border: 0; background: transparent; color: #102030; font-size: 21px; }
    .schedule-calendar { padding: 26px 20px 22px; border-bottom: 1px solid #d6ddd3; }.calendar-heading { display: flex; align-items: center; justify-content: space-between; margin: 0 5px 10px; color: #00d95a; font-weight: 700; font-size: 18px; }.choose-date { color: #00b84c; background: transparent; border: 0; font-weight: 600; }
    .date-strip { display: flex; gap: 10px; overflow-x: auto; padding-bottom: 4px; scrollbar-width: thin; }.date-card { flex: 0 0 80px; height: 100px; display: flex; flex-direction: column; justify-content: center; align-items: center; gap: 3px; text-decoration: none; border: 1px solid #d4ded3; border-radius: 16px; color: #112338; background: #fff; }.date-card:hover { border-color: #00d95a; color: #112338; }.date-card.is-selected { color: #fff; background: #08df60; border-color: #08df60; box-shadow: 0 4px 8px rgba(0, 217, 90, .2); }.date-card small { color: #55708c; font-size: 12px; font-weight: 700; text-transform: uppercase; }.date-card.is-selected small { color: #fff; }.date-card strong { font-size: 25px; line-height: 1; }.date-card em { font-style: normal; color: #8d9cac; font-size: 12px; }.date-card.is-selected em { color: #fff; }
    .schedule-heading { padding: 28px 20px 62px; }.schedule-heading h2 { margin: 0 0 8px; font-size: 23px; font-weight: 750; }.schedule-heading p { margin: 0; color: #58708b; font-size: 17px; }.schedule-error { display: inline-block; margin-top: 16px; padding: 10px 14px; border-radius: 8px; color: #a92d2d; background: #fff0f0; }
    .schedule-grid-wrap { overflow: auto; background: #fff; border-top: 1px solid #d7dfd7; }.schedule-grid { width: max-content; min-width: 100%; border-collapse: separate; border-spacing: 0; }.schedule-grid th, .schedule-grid td { min-width: 100px; height: 70px; padding: 0; border-right: 1px solid #dce4dc; border-bottom: 1px solid #dce4dc; text-align: center; }.schedule-grid thead th { height: 60px; vertical-align: bottom; padding-bottom: 10px; color: #55708c; font-size: 13px; background: #fff; position: sticky; top: 0; z-index: 3; }.schedule-grid thead th:first-child, .court-label { min-width: 120px; position: sticky; left: 0; z-index: 4; background: #fff; }.court-label { font-weight: 700; color: #162335; }
    .slot { border: 0; width: 100%; height: 100%; font-weight: 700; background: #eff9ee; color: #00d95a; transition: .15s ease; }.slot:hover:not(:disabled) { background: #dff7df; }.slot.is-selected { color: #fff; background: #fbbc13; }.slot.is-booked { color: #fff; background: #ef6464; cursor: not-allowed; }.slot.is-hold { color: #765200; background: #ffe7a4; cursor: not-allowed; }.slot.is-maintenance, .slot.is-unpriced { color: #94a09a; background: #edf0ee; cursor: not-allowed; }
    .schedule-legend { display: flex; gap: 26px; align-items: center; flex-wrap: wrap; padding: 24px 40px; background: #f4f8f0; color: #58708b; font-size: 16px; }.legend-item { display: flex; align-items: center; gap: 8px; }.legend-dot { width: 20px; height: 20px; border-radius: 5px; }.legend-dot.available { background: #eff9ee; border: 1px solid #d8e4d7; }.legend-dot.selected { background: #fbbc13; }.legend-dot.booked { background: #ef6464; }.legend-dot.locked { background: #edf0ee; }
    .selection-bar { position: fixed; right: 20px; bottom: 20px; z-index: 20; display: none; align-items: center; gap: 16px; padding: 13px 16px; border-radius: 12px; color: #fff; background: #102030; box-shadow: 0 8px 25px rgba(0,0,0,.2); }.selection-bar.visible { display: flex; }.booking-continue { border: 0; border-radius: 7px; padding: 8px 13px; color: #063c1e; background: #08df60; font-weight: 700; text-decoration: none; }
    @media (max-width: 576px) { .schedule-topbar { padding: 0 20px; height: 66px; gap: 16px; }.schedule-topbar h1 { font-size: 19px; }.schedule-heading { padding-bottom: 35px; }.schedule-heading p { font-size: 14px; }.date-card { flex-basis: 70px; height: 90px; }.schedule-grid th, .schedule-grid td { min-width: 82px; }.schedule-grid thead th:first-child, .court-label { min-width: 92px; } }
</style>
@endpush

@section('content')
@php($days = ['CN', 'Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7'])
<div class="court-schedule-page">
    <header class="schedule-topbar"><a class="back-link" href="{{ route('courts.index') }}" aria-label="Quay lại"><i class="bi bi-chevron-left"></i></a><h1>{{ $court->name }}</h1><button class="info-button" type="button" title="Thông tin sân" data-bs-toggle="modal" data-bs-target="#courtInfoModal"><i class="bi bi-info-circle"></i></button></header>
    <section class="schedule-calendar">
        <div class="calendar-heading"><span>Tháng {{ $selectedDate->month }}, {{ $selectedDate->year }}</span><button class="choose-date" type="button" onclick="document.getElementById('datePicker').showPicker()"><i class="bi bi-calendar3 me-2"></i>Chọn ngày</button><input id="datePicker" type="date" min="{{ now()->toDateString() }}" max="{{ now()->addDays(config('booking.max_days', 30))->toDateString() }}" value="{{ $selectedDate->toDateString() }}" hidden onchange="window.location='{{ route('courts.show', $court) }}?booking_date='+this.value"></div>
        <div class="date-strip">@foreach($dateRange as $date)<a href="{{ route('courts.show', $court) }}?booking_date={{ $date->toDateString() }}" class="date-card {{ $date->isSameDay($selectedDate) ? 'is-selected' : '' }}"><small>{{ $days[$date->dayOfWeek] }}</small><strong>{{ $date->day }}</strong><em>T{{ $date->month }}</em></a>@endforeach</div>
    </section>
    <section class="schedule-heading"><h2>Lịch sân cầu lông</h2><p>Chọn các khung giờ còn trống để đặt sân. Vui lòng liên hệ {{ $court->phone ?? 'hotline' }} nếu cần hỗ trợ.</p>
        @if ($errors->any() || session('error'))<div class="schedule-error">{{ session('error') ?: $errors->first() }}</div>@endif
    </section>
    <form method="POST" action="{{ route('bookings.store') }}" id="bookingForm">@csrf<input type="hidden" name="court_id" value="{{ $court->id }}"><input type="hidden" name="booking_date" value="{{ $selectedDate->toDateString() }}"><div id="selectedSlots"></div>
        <div class="schedule-grid-wrap"><table class="schedule-grid"><thead><tr><th>Sân</th>@foreach($timeSlots as $slot)<th>{{ \Carbon\Carbon::parse($slot->start_time)->format('H:i') }}</th>@endforeach</tr></thead><tbody><tr><th class="court-label">{{ $court->name }}</th>@foreach($timeSlots as $slot)@php($item = $availability[$slot->id])@php($status = $item['status'])@php($class = match($status) { 'BOOKED' => 'is-booked', 'HOLD' => 'is-hold', 'MAINTENANCE' => 'is-maintenance', default => ($item['price'] > 0 ? '' : 'is-unpriced') })<td><button type="button" class="slot {{ $class }}" data-slot="{{ $slot->id }}" data-price="{{ $item['price'] }}" {{ $status !== 'AVAILABLE' || $item['price'] <= 0 ? 'disabled' : '' }} title="{{ $slot->name }}">{{ $item['price'] > 0 ? number_format($item['price'] / 1000, 0) . 'k' : '—' }}</button></td>@endforeach</tr></tbody></table></div>
        <div class="schedule-legend"><span class="legend-item"><i class="legend-dot selected"></i>Đã chọn</span><span class="legend-item"><i class="legend-dot available"></i>Trống</span><span class="legend-item"><i class="legend-dot booked"></i>Đã đặt</span><span class="legend-item"><i class="legend-dot locked"></i>Khóa</span></div>
    </form>
    <div class="selection-bar" id="selectionBar"><span id="selectionSummary"></span><button type="submit" form="bookingForm" class="booking-continue">@auth Tiếp tục @else Đăng nhập để đặt @endauth</button></div>
</div>
<div class="modal fade" id="courtInfoModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">{{ $court->name }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><p>{{ $court->description }}</p><p class="mb-1"><strong>Địa chỉ:</strong> {{ $court->address ?? 'Đang cập nhật' }}</p><p class="mb-0"><strong>Liên hệ:</strong> {{ $court->phone ?? 'Đang cập nhật' }}</p></div></div></div></div>
@endsection

@push('scripts')
<script>
    const selected = new Map(), slotsContainer = document.getElementById('selectedSlots'), selectionBar = document.getElementById('selectionBar');
    document.querySelectorAll('.slot:not(:disabled)').forEach(button => button.addEventListener('click', () => {
        const id = button.dataset.slot;
        selected.has(id) ? (selected.delete(id), button.classList.remove('is-selected')) : (selected.set(id, Number(button.dataset.price)), button.classList.add('is-selected'));
        slotsContainer.innerHTML = [...selected.keys()].map(id => `<input type="hidden" name="time_slot_ids[]" value="${id}">`).join('');
        const total = [...selected.values()].reduce((sum, price) => sum + price, 0);
        document.getElementById('selectionSummary').textContent = `${selected.size} khung giờ · ${total.toLocaleString('vi-VN')}đ`;
        selectionBar.classList.toggle('visible', selected.size > 0);
    }));
</script>
@endpush
>>>>>>> 9790fd584874111b4e4d91e45e981ae25b3deaae
