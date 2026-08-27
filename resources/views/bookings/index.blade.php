@extends('layouts.app')

@section('title', 'Đặt lịch - SmashZone')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-list-check"></i> Đặt lịch</h2>
            <a href="/booking/create" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Đặt sân mới
            </a>
        </div>

        @if($bookings->count() > 0)
            <!-- Tabs for filtering -->
            <ul class="nav nav-tabs mb-4" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="all-tab" data-bs-toggle="tab" data-bs-target="#all" type="button" role="tab" aria-controls="all" aria-selected="true">
                        Tất cả
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending" type="button" role="tab" aria-controls="pending" aria-selected="false">
                        Chờ thanh toán
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="confirmed-tab" data-bs-toggle="tab" data-bs-target="#confirmed" type="button" role="tab" aria-controls="confirmed" aria-selected="false">
                        Xác nhận
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="completed-tab" data-bs-toggle="tab" data-bs-target="#completed" type="button" role="tab" aria-controls="completed" aria-selected="false">
                        Hoàn thành
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="cancelled-tab" data-bs-toggle="tab" data-bs-target="#cancelled" type="button" role="tab" aria-controls="cancelled" aria-selected="false">
                        Hủy
                    </button>
                </li>
            </ul>

            <!-- Tab Content -->
            <div class="tab-content">
                <!-- All Bookings -->
                <div class="tab-pane fade show active" id="all" role="tabpanel" aria-labelledby="all-tab">
                    @php
                        $allBookings = $bookings;
                    @endphp
                    @if($allBookings->count() > 0)
                        @foreach($allBookings as $booking)
                            @include('bookings.card', ['booking' => $booking])
                        @endforeach
                    @else
                        <div class="alert alert-info" role="alert">
                            <i class="bi bi-info-circle"></i> Bạn chưa có đặt sân nào
                        </div>
                    @endif
                </div>

                <!-- Pending Payments -->
                <div class="tab-pane fade" id="pending" role="tabpanel" aria-labelledby="pending-tab">
                    @php
                        $pendingBookings = $bookings->filter(fn($b) => $b->status === 'PENDING_PAYMENT');
                    @endphp
                    @if($pendingBookings->count() > 0)
                        @foreach($pendingBookings as $booking)
                            @include('bookings.card', ['booking' => $booking])
                        @endforeach
                    @else
                        <div class="alert alert-info" role="alert">
                            <i class="bi bi-info-circle"></i> Không có đặt sân chờ thanh toán
                        </div>
                    @endif
                </div>

                <!-- Confirmed -->
                <div class="tab-pane fade" id="confirmed" role="tabpanel" aria-labelledby="confirmed-tab">
                    @php
                        $confirmedBookings = $bookings->filter(fn($b) => $b->status === 'CONFIRMED');
                    @endphp
                    @if($confirmedBookings->count() > 0)
                        @foreach($confirmedBookings as $booking)
                            @include('bookings.card', ['booking' => $booking])
                        @endforeach
                    @else
                        <div class="alert alert-info" role="alert">
                            <i class="bi bi-info-circle"></i> Không có đặt sân đã xác nhận
                        </div>
                    @endif
                </div>

                <!-- Completed -->
                <div class="tab-pane fade" id="completed" role="tabpanel" aria-labelledby="completed-tab">
                    @php
                        $completedBookings = $bookings->filter(fn($b) => $b->status === 'COMPLETED');
                    @endphp
                    @if($completedBookings->count() > 0)
                        @foreach($completedBookings as $booking)
                            @include('bookings.card', ['booking' => $booking])
                        @endforeach
                    @else
                        <div class="alert alert-info" role="alert">
                            <i class="bi bi-info-circle"></i> Không có đặt sân hoàn thành
                        </div>
                    @endif
                </div>

                <!-- Cancelled -->
                <div class="tab-pane fade" id="cancelled" role="tabpanel" aria-labelledby="cancelled-tab">
                    @php
                        $cancelledBookings = $bookings->filter(fn($b) => in_array($b->status, ['CANCELLED', 'EXPIRED']));
                    @endphp
                    @if($cancelledBookings->count() > 0)
                        @foreach($cancelledBookings as $booking)
                            @include('bookings.card', ['booking' => $booking])
                        @endforeach
                    @else
                        <div class="alert alert-info" role="alert">
                            <i class="bi bi-info-circle"></i> Không có đặt sân bị hủy
                        </div>
                    @endif
                </div>
            </div>

            <!-- Pagination -->
            @if($bookings->hasPages())
            <nav aria-label="Booking pagination" class="mt-4">
                <ul class="pagination justify-content-center">
                    @if($bookings->onFirstPage())
                    <li class="page-item disabled">
                        <span class="page-link">&laquo; Trước</span>
                    </li>
                    @else
                    <li class="page-item">
                        <a class="page-link" href="{{ $bookings->previousPageUrl() }}">&laquo; Trước</a>
                    </li>
                    @endif

                    @foreach($bookings->getUrlRange(1, $bookings->lastPage()) as $page => $url)
                    <li class="page-item {{ $page == $bookings->currentPage() ? 'active' : '' }}">
                        <a class="page-link" href="{{ $url }}">{{ $page }}</a>
                    </li>
                    @endforeach

                    @if($bookings->hasMorePages())
                    <li class="page-item">
                        <a class="page-link" href="{{ $bookings->nextPageUrl() }}">Tiếp &raquo;</a>
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
            <div class="alert alert-info alert-lg text-center" role="alert">
                <i class="bi bi-inbox" style="font-size: 3rem; margin-bottom: 20px; display: block;"></i>
                <h4>Chưa có đặt sân nào</h4>
                <p class="mb-0">Hãy bắt đầu đặt sân cầu lông ngay bây giờ!</p>
                <div class="mt-3">
                    <a href="/courts" class="btn btn-primary">
                        <i class="bi bi-search"></i> Tìm sân ngay
                    </a>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
