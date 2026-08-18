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
