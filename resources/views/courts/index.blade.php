@extends('layouts.app')

@section('title', 'Danh sách sân - SmashZone')

@section('content')
<div class="row">
    <!-- Filters -->
    <div class="col-lg-3 mb-4">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-funnel"></i> Bộ lọc</h5>
            </div>
            <div class="card-body">
                <form action="/courts" method="GET" id="filterForm">
                    <!-- Search -->
                    <div class="mb-4">
                        <label class="form-label"><strong>Tìm kiếm</strong></label>
                        <input type="text" class="form-control" name="keyword" value="{{ request('keyword') }}" placeholder="Tên sân...">
                    </div>

                    <!-- Court Type -->
                    <div class="mb-4">
                        <label class="form-label"><strong>Loại sân</strong></label>
                        <select class="form-select" name="court_type_id">
                            <option value="">-- Tất cả --</option>
                            @foreach($courtTypes ?? [] as $type)
                            <option value="{{ $type->id }}" {{ request('court_type_id') == $type->id ? 'selected' : '' }}>
                                {{ $type->name }}
                            </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Price Range -->
                    <div class="mb-4">
                        <label class="form-label"><strong>Khoảng giá</strong></label>
                        <div class="row">
                            <div class="col-6">
                                <input type="number" class="form-control" name="price_min" value="{{ request('price_min') }}" placeholder="Từ">
                            </div>
                            <div class="col-6">
                                <input type="number" class="form-control" name="price_max" value="{{ request('price_max') }}" placeholder="Đến">
                            </div>
                        </div>
                    </div>

                    <!-- Booking Date -->
                    <div class="mb-4">
                        <label class="form-label"><strong>Ngày đặt</strong></label>
                        <input type="date" class="form-control" name="booking_date" value="{{ request('booking_date') }}">
                    </div>

                    <!-- Sort -->
                    <div class="mb-4">
                        <label class="form-label"><strong>Sắp xếp</strong></label>
                        <select class="form-select" name="sort_by">
                            <option value="">-- Mặc định --</option>
                            <option value="price_asc" {{ request('sort_by') == 'price_asc' ? 'selected' : '' }}>Giá tăng dần</option>
                            <option value="price_desc" {{ request('sort_by') == 'price_desc' ? 'selected' : '' }}>Giá giảm dần</option>
                            <option value="name_asc" {{ request('sort_by') == 'name_asc' ? 'selected' : '' }}>Tên A-Z</option>
                            <option value="name_desc" {{ request('sort_by') == 'name_desc' ? 'selected' : '' }}>Tên Z-A</option>
                            <option value="most_booked" {{ request('sort_by') == 'most_booked' ? 'selected' : '' }}>Đặt nhiều nhất</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 mb-2">
                        <i class="bi bi-search"></i> Lọc
                    </button>
                    <a href="/courts" class="btn btn-outline-secondary w-100">
                        <i class="bi bi-x-circle"></i> Xóa bộ lọc
                    </a>
                </form>
            </div>
        </div>
    </div>

    <!-- Courts List -->
    <div class="col-lg-9">
        <!-- Results Header -->
        <div class="mb-4">
            <h2>
                @if(request('keyword'))
                    Kết quả tìm kiếm: "{{ request('keyword') }}"
                @else
                    Danh sách sân cầu lông
                @endif
            </h2>
            <p class="text-muted">Tìm thấy <strong>{{ $courts->total() }}</strong> sân</p>
        </div>

        <!-- Courts Grid -->
        @if($courts->count() > 0)
        <div class="row">
            @foreach($courts as $court)
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card court-card h-100">
                    <!-- Image -->
                    <div style="position: relative; height: 200px; overflow: hidden;">
                        <img src="{{ $court->images->first()?->image ?? 'https://via.placeholder.com/300x200?text=No+Image' }}" class="card-img-top court-image" alt="{{ $court->name }}">
                        <div style="position: absolute; top: 10px; right: 10px;">
                            <span class="badge bg-primary">{{ $court->courtType->name }}</span>
                        </div>
                    </div>

                    <div class="card-body d-flex flex-column">
                        <!-- Court Info -->
                        <h5 class="card-title">{{ $court->name }}</h5>
                        <p class="card-text small text-muted mb-2">
                            <i class="bi bi-geo-alt"></i> {{ $court->code }}
                        </p>

                        <!-- Rating -->
                        @if($court->reviews->count() > 0)
                        <p class="card-text small mb-2">
                            @for($i = 0; $i < floor($court->getAverageRating()); $i++)
                            <i class="bi bi-star-fill" style="color: #fbbf24;"></i>
                            @endfor
                            <span class="text-muted">({{ $court->getReviewCount() }} đánh giá)</span>
                        </p>
                        @endif

                        <!-- Price -->
                        @if($court->prices->count() > 0)
                        <p class="card-text mb-3">
                            <strong style="color: #6366f1; font-size: 1.1rem;">
                                Từ {{ number_format($court->prices->min('price'), 0, ',', '.') }} VNĐ
                            </strong>
                        </p>
                        @endif

                        <!-- Amenities -->
                        @if($court->amenities->count() > 0)
                        <div class="mb-3">
                            <small class="text-muted d-block mb-2">Tiện ích:</small>
                            @foreach($court->amenities->take(3) as $amenity)
                            <span class="badge bg-light text-dark me-1 mb-1">{{ $amenity->name }}</span>
                            @endforeach
                            @if($court->amenities->count() > 3)
                            <span class="badge bg-light text-dark">+{{ $court->amenities->count() - 3 }}</span>
                            @endif
                        </div>
                        @endif

                        <!-- Action Buttons -->
                        <div class="mt-auto">
                            <a href="/courts/{{ $court->id }}" class="btn btn-primary w-100">
                                <i class="bi bi-info-circle"></i> Chi tiết
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        <!-- Pagination -->
        @if($courts->hasPages())
        <nav aria-label="Page navigation" class="mt-5">
            <ul class="pagination justify-content-center">
                @if($courts->onFirstPage())
                <li class="page-item disabled">
                    <span class="page-link">&laquo; Trước</span>
                </li>
                @else
                <li class="page-item">
                    <a class="page-link" href="{{ $courts->previousPageUrl() }}">&laquo; Trước</a>
                </li>
                @endif

                @foreach($courts->getUrlRange(1, $courts->lastPage()) as $page => $url)
                <li class="page-item {{ $page == $courts->currentPage() ? 'active' : '' }}">
                    <a class="page-link" href="{{ $url }}">{{ $page }}</a>
                </li>
                @endforeach

                @if($courts->hasMorePages())
                <li class="page-item">
                    <a class="page-link" href="{{ $courts->nextPageUrl() }}">Tiếp &raquo;</a>
                </li>
                @else
                <li class="page-item disabled">
                    <span class="page-link">Tiếp &raquo;</span>
                </li>
                @endif
            </ul>
        </nav>
        @endif

        @else
        <!-- No Results -->
        <div class="alert alert-info" role="alert">
            <i class="bi bi-info-circle"></i> Không tìm thấy sân phù hợp. Vui lòng thử lại với các tiêu chí khác.
        </div>
        @endif
    </div>
</div>
@endsection
