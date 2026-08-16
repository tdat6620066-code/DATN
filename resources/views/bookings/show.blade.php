@extends('layouts.app')

@section('title', 'Chi tiết đặt sân - SmashZone')

@section('content')
<div class="row">
    <div class="col-lg-8">
        <!-- Booking Details -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-receipt"></i> Thông tin đặt sân</h5>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <p><strong>Mã đặt sân:</strong></p>
                        <p class="text-monospace" style="font-size: 1.1rem; color: #6366f1;">{{ $booking->booking_code }}</p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Trạng thái:</strong></p>
                        <p>
                            @php
                                $statusMap = [
                                    'PENDING_PAYMENT' => ['badge-warning', 'Chờ thanh toán'],
                                    'CONFIRMED' => ['badge-success', 'Xác nhận'],
                                    'COMPLETED' => ['badge-success', 'Hoàn thành'],
                                    'CANCELLED' => ['badge-danger', 'Hủy'],
                                    'EXPIRED' => ['badge-secondary', 'Hết hạn'],
                                ];
                                [$badgeClass, $statusText] = $statusMap[$booking->status] ?? ['badge-secondary', $booking->status];
                            @endphp
                            <span class="badge {{ $badgeClass }}">{{ $statusText }}</span>
                        </p>
                    </div>
                </div>

                <hr>

                <!-- Booking Items -->
                <h6 class="mb-3">Chi tiết sân:</h6>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Sân</th>
                                <th>Ngày</th>
                                <th>Khung giờ</th>
                                <th>Giá</th>
                                <th>Thành tiền</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($booking->bookingDetails as $detail)
                            <tr>
                                <td>{{ $detail->court->name }}</td>
                                <td>{{ $detail->booking_date->format('d/m/Y') }}</td>
                                <td>{{ $detail->timeSlot->name }}</td>
                                <td>{{ number_format($detail->price, 0, ',', '.') }} VNĐ</td>
                                <td><strong>{{ number_format($detail->subtotal, 0, ',', '.') }} VNĐ</strong></td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Payment Info -->
        @if($booking->payment)
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="bi bi-credit-card"></i> Thông tin thanh toán</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Phương thức:</strong></p>
                        <p>{{ ucfirst($booking->payment->payment_method) }}</p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Trạng thái thanh toán:</strong></p>
                        <p>
                            @php
                                $paymentStatusMap = [
                                    'PENDING' => ['badge-warning', 'Chờ thanh toán'],
                                    'PAID' => ['badge-success', 'Đã thanh toán'],
                                    'FAILED' => ['badge-danger', 'Thất bại'],
                                    'REFUNDED' => ['badge-secondary', 'Hoàn tiền'],
                                ];
                                [$paymentBadge, $paymentText] = $paymentStatusMap[$booking->payment->status] ?? ['badge-secondary', $booking->payment->status];
                            @endphp
                            <span class="badge {{ $paymentBadge }}">{{ $paymentText }}</span>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <!-- Actions -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-actions"></i> Thao tác</h5>
            </div>
            <div class="card-body">
                @if($booking->status === 'PENDING_PAYMENT')
                <p class="mb-3">Để hoàn tất đặt sân, vui lòng xác nhận thanh toán:</p>
                <form action="/booking/{{ $booking->id }}/confirm-payment" method="POST" style="display: inline;">
                    @csrf
                    <button type="submit" class="btn btn-success me-2">
                        <i class="bi bi-check-circle"></i> Xác nhận thanh toán
                    </button>
                </form>
                <form action="/booking/{{ $booking->id }}/cancel" method="POST" style="display: inline;">
                    @csrf
                    <button type="submit" class="btn btn-danger" onclick="return confirm('Bạn chắc chắn muốn hủy?')">
                        <i class="bi bi-trash"></i> Hủy đặt sân
                    </button>
                </form>
                @elseif($booking->status === 'CONFIRMED' || $booking->status === 'COMPLETED')
                <p class="alert alert-success mb-0">
                    <i class="bi bi-check-circle-fill"></i> Đặt sân đã được xác nhận. Vui lòng đến sân đúng giờ.
                </p>
                @elseif($booking->status === 'CANCELLED')
                <p class="alert alert-danger mb-0">
                    <i class="bi bi-x-circle-fill"></i> Đặt sân này đã bị hủy.
                </p>
                @endif
            </div>
        </div>
    </div>

    <!-- Summary Sidebar -->
    <div class="col-lg-4">
        <div class="card sticky-top" style="top: 20px;">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-calculator"></i> Tóm tắt tiền</h5>
            </div>
            <div class="card-body">
                <div class="booking-summary">
                    @php
                        $subtotal = $booking->bookingDetails->sum('subtotal');
                        $discount = 0;
                        if ($booking->voucher_id && $booking->discount) {
                            $discount = $booking->discount;
                        }
                        $total = $subtotal - $discount;
                    @endphp

                    <div class="summary-item">
                        <strong>Tạm tính:</strong>
                        <span>{{ number_format($subtotal, 0, ',', '.') }} VNĐ</span>
                    </div>

                    @if($discount > 0)
                    <div class="summary-item">
                        <strong>Chiết khấu:</strong>
                        <span style="color: #10b981;">-{{ number_format($discount, 0, ',', '.') }} VNĐ</span>
                    </div>
                    @endif

                    <div class="summary-total">
                        <strong>Tổng cộng:</strong>
                        <span>{{ number_format($total, 0, ',', '.') }} VNĐ</span>
                    </div>

                    <hr>

                    <p class="small mb-0">
                        <i class="bi bi-info-circle"></i>
                        Số tiền sẽ được thanh toán theo phương thức bạn chọn
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
