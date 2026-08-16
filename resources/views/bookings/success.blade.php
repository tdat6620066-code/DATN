@extends('layouts.app')

@section('title', 'Thanh toán thành công - SmashZone')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-6">
        <!-- Success Message -->
        <div class="card text-center mb-4">
            <div class="card-body py-5">
                <div style="font-size: 4rem; color: #10b981; margin-bottom: 20px;">
                    <i class="bi bi-check-circle-fill"></i>
                </div>
                <h2 class="mb-3">Thanh toán thành công!</h2>
                <p class="lead text-muted mb-0">Đặt sân của bạn đã được xác nhận</p>
            </div>
        </div>

        <!-- Booking Details -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-receipt"></i> Chi tiết đặt sân</h5>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <p><strong>Mã đặt sân:</strong></p>
                        <p class="text-monospace" style="font-size: 1.1rem; color: #6366f1;">{{ $booking->booking_code }}</p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Trạng thái:</strong></p>
                        <p><span class="badge bg-success">Xác nhận</span></p>
                    </div>
                </div>

                <hr>

                <!-- Booking Items -->
                <h6 class="mb-3">Thông tin sân:</h6>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Sân</th>
                                <th>Ngày</th>
                                <th>Khung giờ</th>
                                <th>Thành tiền</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($booking->bookingDetails as $detail)
                            <tr>
                                <td>{{ $detail->court->name }}</td>
                                <td>{{ $detail->booking_date->format('d/m/Y') }}</td>
                                <td>{{ $detail->timeSlot->name }}</td>
                                <td>{{ number_format($detail->subtotal, 0, ',', '.') }} VNĐ</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <hr>

                <!-- Total -->
                @php
                    $subtotal = $booking->bookingDetails->sum('subtotal');
                    $discount = $booking->discount ?? 0;
                    $total = $subtotal - $discount;
                @endphp
                <div class="d-flex justify-content-between mb-2">
                    <strong>Tạm tính:</strong>
                    <strong>{{ number_format($subtotal, 0, ',', '.') }} VNĐ</strong>
                </div>
                @if($discount > 0)
                <div class="d-flex justify-content-between mb-2">
                    <strong>Chiết khấu:</strong>
                    <strong style="color: #10b981;">-{{ number_format($discount, 0, ',', '.') }} VNĐ</strong>
                </div>
                @endif
                <div class="d-flex justify-content-between" style="border-top: 2px solid #6366f1; padding-top: 10px;">
                    <strong style="color: #6366f1; font-size: 1.1rem;">Tổng cộng:</strong>
                    <strong style="color: #6366f1; font-size: 1.1rem;">{{ number_format($total, 0, ',', '.') }} VNĐ</strong>
                </div>
            </div>
        </div>

        <!-- QR Code -->
        @if($qrCode)
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="bi bi-qr-code"></i> Mã QR để check-in</h5>
            </div>
            <div class="card-body text-center">
                <img src="data:image/png;base64,{{ $qrCode }}" alt="QR Code" style="max-width: 250px; margin: 20px 0;">
                <p class="text-muted small">Xuất trình mã QR này tại sân để check-in</p>
            </div>
        </div>
        @endif

        <!-- Next Steps -->
        <div class="card mb-4">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="bi bi-info-circle"></i> Những điều bạn cần biết</h5>
            </div>
            <div class="card-body">
                <ul class="mb-0">
                    <li class="mb-2">Hãy đến sân trước 15 phút để chuẩn bị</li>
                    <li class="mb-2">Mang theo mã QR hoặc xuất trình mã đặt sân {{ $booking->booking_code }}</li>
                    <li class="mb-2">Vui lòng liên hệ ngay nếu có thay đổi kế hoạch</li>
                    <li>Sau khi sử dụng sân, bạn có thể đánh giá trên hệ thống của chúng tôi</li>
                </ul>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="row gap-2">
            <div class="col">
                <a href="/bookings" class="btn btn-primary w-100">
                    <i class="bi bi-list-check"></i> Xem tất cả đặt sân
                </a>
            </div>
            <div class="col">
                <a href="/courts" class="btn btn-outline-primary w-100">
                    <i class="bi bi-search"></i> Đặt thêm sân
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
