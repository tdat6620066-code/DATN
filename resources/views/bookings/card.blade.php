<!-- Booking Card Component -->
<div class="card mb-4">
    <div class="card-body">
        <div class="row">
            <!-- Booking Info -->
            <div class="col-md-8">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h5 class="mb-1">{{ $booking->booking_code }}</h5>
                        <p class="text-muted small mb-0">
                            <i class="bi bi-calendar-event"></i>
                            {{ $booking->created_at->format('d/m/Y H:i') }}
                        </p>
                    </div>
                    <div>
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
                    </div>
                </div>

                <!-- Booking Details -->
                <div class="mb-3">
                    <h6 class="mb-2">Chi tiết sân:</h6>
                    <div class="row">
                        @foreach($booking->bookingDetails as $detail)
                        <div class="col-md-6 mb-2">
                            <div class="p-2" style="background-color: #f3f4f6; border-radius: 6px;">
                                <p class="mb-1"><strong>{{ $detail->court->name }}</strong></p>
                                <p class="mb-0 small">
                                    <i class="bi bi-calendar-check"></i> {{ $detail->booking_date->format('d/m/Y') }}<br>
                                    <i class="bi bi-clock"></i> {{ $detail->timeSlot->name }}<br>
                                    <strong>{{ number_format($detail->subtotal, 0, ',', '.') }} VNĐ</strong>
                                </p>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>

                <!-- Total Amount -->
                <div class="d-flex justify-content-between">
                    <span>Tổng cộng:</span>
                    <strong style="color: #6366f1; font-size: 1.1rem;">
                        {{ number_format($booking->bookingDetails->sum('subtotal') - ($booking->discount ?? 0), 0, ',', '.') }} VNĐ
                    </strong>
                </div>
            </div>

            <!-- Actions -->
            <div class="col-md-4">
                <div class="d-flex flex-column gap-2" style="height: 100%;">
                    <a href="/booking/{{ $booking->id }}" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-eye"></i> Chi tiết
                    </a>

                    @if($booking->status === 'PENDING_PAYMENT')
                    <form action="/booking/{{ $booking->id }}/confirm-payment" method="POST" style="margin: 0;">
                        @csrf
                        <button type="submit" class="btn btn-success btn-sm w-100">
                            <i class="bi bi-check-circle"></i> Xác nhận thanh toán
                        </button>
                    </form>
                    
                    <form action="/booking/{{ $booking->id }}/cancel" method="POST" style="margin: 0;">
                        @csrf
                        <button type="submit" class="btn btn-danger btn-sm w-100" onclick="return confirm('Bạn chắc chắn muốn hủy?')">
                            <i class="bi bi-trash"></i> Hủy
                        </button>
                    </form>
                    @elseif($booking->status === 'CONFIRMED' || $booking->status === 'COMPLETED')
                    <form action="/booking/{{ $booking->id }}/cancel" method="POST" style="margin: 0;">
                        @csrf
                        <button type="submit" class="btn btn-danger btn-sm w-100" onclick="return confirm('Bạn chắc chắn muốn hủy?')">
                            <i class="bi bi-trash"></i> Hủy
                        </button>
                    </form>
                    @endif

                    <div class="mt-auto">
                        @if($booking->payment)
                        <small class="text-muted">
                            Thanh toán: <strong>{{ ucfirst($booking->payment->payment_method) }}</strong><br>
                            @php
                                $paymentStatusMap = [
                                    'PENDING' => 'Chờ thanh toán',
                                    'PAID' => 'Đã thanh toán',
                                    'FAILED' => 'Thất bại',
                                    'REFUNDED' => 'Hoàn tiền',
                                ];
                            @endphp
                            Trạng thái: <strong>{{ $paymentStatusMap[$booking->payment->status] ?? $booking->payment->status }}</strong>
                        </small>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
