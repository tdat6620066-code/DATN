@extends('layouts.app')

@section('title', 'Chi tiết đặt sân - SmashZone')

@push('styles')
<style>
    :root {
        --brand: #08b96b;
        --brand-dark: #079957;
        --brand-soft: #e8f9f1;
        --ink: #0f172a;
        --muted: #64748b;
        --line: #e2e8f0;
    }

    .booking-hero {
        border-radius: 16px;
        padding: 24px 28px;
        color: #fff;
        position: relative;
        overflow: hidden;
        box-shadow: 0 12px 28px rgba(15, 23, 42, 0.12);
    }

    .booking-hero::after {
        content: "";
        position: absolute;
        right: -70px;
        top: -70px;
        width: 220px;
        height: 220px;
        background: rgba(255, 255, 255, 0.12);
        border-radius: 50%;
    }

    .booking-hero.status-pending { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }
    .booking-hero.status-confirmed { background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%); }
    .booking-hero.status-completed { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
    .booking-hero.status-cancelled { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); }
    .booking-hero.status-expired { background: linear-gradient(135deg, #64748b 0%, #475569 100%); }

    .booking-hero .hero-icon {
        width: 52px;
        height: 52px;
        border-radius: 12px;
        background: rgba(255, 255, 255, 0.18);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.6rem;
    }

    .booking-code-pill {
        display: inline-block;
        background: rgba(255, 255, 255, 0.2);
        border: 1px solid rgba(255, 255, 255, 0.35);
        padding: 6px 14px;
        border-radius: 999px;
        font-family: 'Consolas', 'Monaco', monospace;
        font-weight: 700;
        letter-spacing: 0.5px;
    }

    .hero-status-badge {
        background: rgba(255, 255, 255, 0.95);
        color: var(--ink);
        font-weight: 700;
        padding: 7px 16px;
        border-radius: 999px;
        font-size: 0.9rem;
    }

    .booking-card {
        border: 1px solid var(--line);
        border-radius: 16px;
        box-shadow: 0 4px 16px rgba(15, 23, 42, 0.05);
        overflow: hidden;
    }

    .booking-card .card-header {
        background: #fff;
        border-bottom: 1px solid var(--line);
        padding: 18px 22px;
    }

    .booking-card .card-header i {
        color: var(--brand);
    }

    .booking-card .card-body {
        padding: 24px;
    }

    .info-tile {
        background: #f8fafc;
        border: 1px solid var(--line);
        border-radius: 12px;
        padding: 16px 18px;
        height: 100%;
    }

    .info-tile .tile-label {
        font-size: 0.78rem;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        color: var(--muted);
        font-weight: 600;
        margin-bottom: 6px;
    }

    .info-tile .tile-value {
        font-weight: 700;
        color: var(--ink);
        font-size: 1rem;
        word-break: break-word;
    }

    .court-row {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 16px 0;
        border-bottom: 1px dashed var(--line);
    }

    .court-row:last-child { border-bottom: none; padding-bottom: 0; }

    .court-thumb {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        background: var(--brand-soft);
        color: var(--brand);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.35rem;
        flex-shrink: 0;
    }

    .pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 10px;
        border-radius: 999px;
        font-size: 0.78rem;
        font-weight: 600;
    }

    .status-badge { font-weight: 600; padding: 6px 12px; border-radius: 999px; font-size: 0.82rem; }
    .status-badge.warning { background: #fef3c7; color: #b45309; }
    .status-badge.success { background: #dcfce7; color: #15803d; }
    .status-badge.danger { background: #fee2e2; color: #b91c1c; }
    .status-badge.secondary { background: #e2e8f0; color: #475569; }
    .status-badge.info { background: #e0f2fe; color: #0369a1; }

    .stepper { display: flex; align-items: center; }
    .step { display: flex; flex-direction: column; align-items: center; flex: 1; text-align: center; position: relative; }
    .step .dot {
        width: 34px; height: 34px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.95rem; font-weight: 700; z-index: 2;
        background: #e2e8f0; color: #94a3b8; border: 2px solid #fff;
        box-shadow: 0 0 0 2px #e2e8f0;
    }
    .step.active .dot { background: var(--brand); color: #fff; box-shadow: 0 0 0 4px var(--brand-soft); }
    .step.completed .dot { background: #ffffff; color: var(--brand); box-shadow: 0 0 0 2px var(--brand); }
    .step .label { font-size: 0.74rem; color: var(--muted); margin-top: 8px; font-weight: 600; }
    .step.active .label { color: var(--brand-dark); font-weight: 700; }
    .step .line {
        position: absolute; top: 17px; left: 50%; width: 100%; height: 3px;
        background: #e2e8f0; z-index: 1;
    }
    .step:first-child .line { display: none; }
    .step.completed .line { background: var(--brand); }

    .summary-sticky { position: sticky; top: 20px; }

    .summary-item {
        display: flex; justify-content: space-between; align-items: center;
        padding: 12px 0; border-bottom: 1px solid var(--line);
        color: var(--muted); font-size: 0.95rem;
    }

    .summary-total {
        display: flex; justify-content: space-between; align-items: center;
        padding-top: 16px; margin-top: 4px; font-weight: 800; font-size: 1.3rem;
        color: var(--brand-dark);
    }

    .action-btn {
        border-radius: 10px;
        padding: 10px 18px;
        font-weight: 600;
    }
</style>
@endpush

@section('content')
@php
    $statusConfig = [
        'PENDING_PAYMENT' => ['label' => 'Chờ thanh toán', 'badge' => 'warning', 'hero' => 'pending', 'icon' => 'bi-hourglass-split'],
        'CONFIRMED' => ['label' => 'Đã xác nhận', 'badge' => 'info', 'hero' => 'confirmed', 'icon' => 'bi-calendar2-check'],
        'CHECKED_IN' => ['label' => 'Đã nhận sân', 'badge' => 'info', 'hero' => 'confirmed', 'icon' => 'bi-box-arrow-in-right'],
        'COMPLETED' => ['label' => 'Đã hoàn thành', 'badge' => 'success', 'hero' => 'completed', 'icon' => 'bi-check-circle'],
        'CANCELLED' => ['label' => 'Đã hủy', 'badge' => 'danger', 'hero' => 'cancelled', 'icon' => 'bi-x-circle'],
        'EXPIRED' => ['label' => 'Đã hết hạn', 'badge' => 'secondary', 'hero' => 'expired', 'icon' => 'bi-clock-history'],
    ];
    $status = $statusConfig[$booking->status] ?? ['label' => $booking->status, 'badge' => 'secondary', 'hero' => 'expired', 'icon' => 'bi-receipt'];

    $paymentMethods = [
        'cash' => 'Tiền mặt',
        'momo' => 'Ví MoMo',
        'vnpay' => 'VNPay',
        'zalopay' => 'ZaloPay',
        'bank_transfer' => 'Chuyển khoản ngân hàng',
        'credit_card' => 'Thẻ tín dụng / ghi nợ',
    ];
    $methodLabel = $booking->payment ? ($paymentMethods[$booking->payment->payment_method] ?? ucfirst($booking->payment->payment_method)) : '—';

    $subtotal = $booking->bookingDetails->sum('subtotal');
    $discount = ($booking->voucher_id && $booking->discount) ? $booking->discount : 0;
    $total = $subtotal - $discount;

    // Stepper states
    $stepStates = [
        'booked' => true,
        'paid' => !in_array($booking->status, ['PENDING_PAYMENT', 'CANCELLED', 'EXPIRED']),
        'confirmed' => in_array($booking->status, ['CONFIRMED', 'CHECKED_IN', 'COMPLETED']),
        'checked_in' => in_array($booking->status, ['CHECKED_IN', 'COMPLETED']),
        'completed' => $booking->status === 'COMPLETED',
    ];
    $currentStep = match ($booking->status) {
        'PENDING_PAYMENT' => 'booked',
        'CONFIRMED' => 'confirmed',
        'CHECKED_IN' => 'checked_in',
        'COMPLETED' => 'completed',
        default => 'booked',
    };
@endphp

<div class="row g-4">
    <div class="col-lg-8">
        <a href="{{ route('bookings.index') }}" class="btn btn-outline-secondary btn-sm mb-3 rounded-pill px-3">
            <i class="bi bi-arrow-left me-1"></i> Danh sách đặt sân
        </a>

        <!-- Hero status -->
        <div class="booking-hero status-{{ $status['hero'] }} mb-4">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                <div class="d-flex align-items-center gap-3">
                    <span class="hero-icon"><i class="bi {{ $status['icon'] }}"></i></span>
                    <div>
                        <div class="text-uppercase small fw-semibold opacity-75">Mã đặt sân</div>
                        <div class="booking-code-pill mt-1">{{ $booking->booking_code }}</div>
                    </div>
                </div>
                <span class="hero-status-badge">{{ $status['label'] }}</span>
            </div>
            <div class="mt-3 small opacity-75">
                <i class="bi bi-calendar3 me-1"></i>
                Ngày đặt: {{ $booking->created_at->format('d/m/Y H:i') }}
                @if($booking->confirmed_at)
                    <span class="mx-2">•</span><i class="bi bi-check2-circle me-1"></i>Xác nhận: {{ $booking->confirmed_at->format('d/m/Y H:i') }}
                @endif
            </div>
        </div>

        @if(!in_array($booking->status, ['CANCELLED', 'EXPIRED']))
        <!-- Progress stepper -->
        <div class="booking-card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-signpost-split me-2"></i>Tiến trình đặt sân</h5>
            </div>
            <div class="card-body pt-4">
                <div class="stepper">
                    <div class="step {{ $stepStates['booked'] ? 'completed' : 'active' }}">
                        <div class="line"></div>
                        <div class="dot"><i class="bi bi-cart-check"></i></div>
                        <div class="label">Đặt sân</div>
                    </div>
                    <div class="step {{ $stepStates['paid'] ? ($currentStep === 'booked' ? 'active' : 'completed') : '' }}">
                        <div class="line"></div>
                        <div class="dot"><i class="bi bi-credit-card"></i></div>
                        <div class="label">Thanh toán</div>
                    </div>
                    <div class="step {{ $stepStates['confirmed'] ? ($currentStep === 'confirmed' ? 'active' : 'completed') : '' }}">
                        <div class="line"></div>
                        <div class="dot"><i class="bi bi-check-lg"></i></div>
                        <div class="label">Xác nhận</div>
                    </div>
                    <div class="step {{ $stepStates['checked_in'] ? ($currentStep === 'checked_in' ? 'active' : 'completed') : '' }}">
                        <div class="line"></div>
                        <div class="dot"><i class="bi bi-box-arrow-in-right"></i></div>
                        <div class="label">Nhận sân</div>
                    </div>
                    <div class="step {{ $stepStates['completed'] ? 'active' : '' }}">
                        <div class="line"></div>
                        <div class="dot"><i class="bi bi-flag"></i></div>
                        <div class="label">Hoàn thành</div>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <!-- Booking details -->
        <div class="booking-card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-receipt me-2"></i>Thông tin đặt sân</h5>
            </div>
            <div class="card-body">
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="info-tile">
                            <div class="tile-label">Mã đặt sân</div>
                            <div class="tile-value" style="font-family:'Consolas','Monaco',monospace;">{{ $booking->booking_code }}</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="info-tile">
                            <div class="tile-label">Trạng thái</div>
                            <div class="tile-value"><span class="status-badge {{ $status['badge'] }}">{{ $status['label'] }}</span></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="info-tile">
                            <div class="tile-label">Số lượng sân</div>
                            <div class="tile-value">{{ $booking->bookingDetails->count() }} khung giờ</div>
                        </div>
                    </div>
                    @if($booking->note)
                    <div class="col-12">
                        <div class="info-tile">
                            <div class="tile-label">Ghi chú</div>
                            <div class="tile-value" style="font-weight:500; font-size:0.95rem;">{{ $booking->note }}</div>
                        </div>
                    </div>
                    @endif
                </div>

                <h6 class="text-uppercase small fw-bold text-muted mb-2"><i class="bi bi-grid me-1"></i>Chi tiết sân</h6>
                @foreach($booking->bookingDetails as $detail)
                <div class="court-row">
                    <div class="court-thumb"><i class="bi bi-dribbble"></i></div>
                    <div class="flex-grow-1">
                        <div class="fw-bold" style="color: var(--ink);">{{ $detail->court->name }}</div>
                        <div class="small text-muted">
                            <i class="bi bi-calendar3 me-1"></i>{{ $detail->booking_date->format('d/m/Y') }}
                            <span class="mx-1">•</span>
                            <i class="bi bi-clock me-1"></i>{{ $detail->timeSlot->name }}
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="small text-muted">{{ number_format($detail->price, 0, ',', '.') }} VNĐ</div>
                        <div class="fw-bold" style="color: var(--brand-dark);">{{ number_format($detail->subtotal, 0, ',', '.') }} VNĐ</div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        <!-- Payment info -->
        @if($booking->payment)
        <div class="booking-card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-credit-card-2-front me-2"></i>Thông tin thanh toán</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="info-tile">
                            <div class="tile-label">Phương thức thanh toán</div>
                            <div class="tile-value">{{ $methodLabel }}</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-tile">
                            <div class="tile-label">Trạng thái thanh toán</div>
                            <div class="tile-value">
                                @php
                                    $paymentStatusMap = [
                                        'PENDING' => ['warning', 'Chờ thanh toán'],
                                        'PAID' => ['success', 'Đã thanh toán'],
                                        'FAILED' => ['danger', 'Thất bại'],
                                        'REFUNDED' => ['secondary', 'Đã hoàn tiền'],
                                    ];
                                    [$pBadge, $pText] = $paymentStatusMap[$booking->payment->status] ?? ['secondary', $booking->payment->status];
                                @endphp
                                <span class="status-badge {{ $pBadge }}">{{ $pText }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-tile">
                            <div class="tile-label">Mã giao dịch</div>
                            <div class="tile-value" style="font-family:'Consolas','Monaco',monospace; font-size:0.9rem;">{{ $booking->payment->transaction_id ?? '—' }}</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-tile">
                            <div class="tile-label">Thời gian thanh toán</div>
                            <div class="tile-value">{{ $booking->payment->paid_at ? $booking->payment->paid_at->format('d/m/Y H:i') : '—' }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <!-- Actions -->
        <div class="booking-card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-sliders me-2"></i>Thao tác</h5>
            </div>
            <div class="card-body">
                @if($booking->status === 'PENDING_PAYMENT')
                    <p class="text-muted mb-3">
                        <i class="bi bi-info-circle me-1"></i>
                        Đơn đặt sân của bạn đang chờ thanh toán. Vui lòng thanh toán qua VNPay để hoàn tất.
                    </p>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="{{ route('bookings.vnpay', $booking) }}" class="btn btn-success action-btn">
                            <i class="bi bi-credit-card me-1"></i> Thanh toán ngay
                        </a>
                        <form action="{{ route('bookings.cancel', $booking) }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-outline-danger action-btn" onclick="return confirm('Bạn chắc chắn muốn hủy đặt sân này?')">
                                <i class="bi bi-trash me-1"></i> Hủy đặt sân
                            </button>
                        </form>
                    </div>
                @elseif(in_array($booking->status, ['CONFIRMED', 'CHECKED_IN', 'COMPLETED']))
                    <div class="alert alert-success d-flex align-items-center mb-0 border-0 rounded-3" style="background: #e8f9f1;">
                        <i class="bi bi-check-circle-fill fs-4 me-3" style="color: var(--brand);"></i>
                        <div>
                            <strong>Đặt sân đã được xác nhận.</strong>
                            <div class="small text-muted">Vui lòng đến sân đúng giờ. Chúc bạn có buổi chơi vui vẻ!</div>
                        </div>
                    </div>
                @elseif($booking->status === 'CANCELLED')
                    <div class="alert alert-danger d-flex align-items-center mb-0 border-0 rounded-3" style="background: #fee2e2;">
                        <i class="bi bi-x-circle-fill fs-4 me-3" style="color: #b91c1c;"></i>
                        <div>
                            <strong>Đặt sân này đã bị hủy.</strong>
                            <div class="small text-muted">Bạn có thể đặt lại sân mới nếu cần.</div>
                        </div>
                    </div>
                @elseif($booking->status === 'EXPIRED')
                    <div class="alert alert-secondary d-flex align-items-center mb-0 border-0 rounded-3" style="background: #e2e8f0;">
                        <i class="bi bi-clock-history fs-4 me-3" style="color: #475569;"></i>
                        <div>
                            <strong>Đặt sân này đã hết hạn.</strong>
                            <div class="small text-muted">Vui lòng tạo một đơn đặt mới để tiếp tục.</div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Summary sidebar -->
    <div class="col-lg-4">
        <div class="summary-sticky">
            <div class="booking-card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-calculator me-2"></i>Tóm tắt thanh toán</h5>
                </div>
                <div class="card-body">
                    <div class="summary-item">
                        <span>Tạm tính</span>
                        <span class="fw-semibold text-dark">{{ number_format($subtotal, 0, ',', '.') }} VNĐ</span>
                    </div>

                    @if($discount > 0)
                    <div class="summary-item">
                        <span>Chiết khấu</span>
                        <span class="fw-semibold" style="color: var(--brand);">-{{ number_format($discount, 0, ',', '.') }} VNĐ</span>
                    </div>
                    @endif

                    <div class="summary-total">
                        <span>Tổng cộng</span>
                        <span>{{ number_format($total, 0, ',', '.') }} VNĐ</span>
                    </div>

                    <hr>

                    <div class="d-flex align-items-start gap-2 small text-muted">
                        <i class="bi bi-shield-check fs-5" style="color: var(--brand);"></i>
                        <span>Số tiền sẽ được thanh toán theo phương thức bạn đã chọn. Giao dịch được bảo mật.</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection