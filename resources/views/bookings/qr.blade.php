@extends('layouts.app')

@section('title', 'Mã QR check-in - SmashZone')

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

    .qr-card {
        border: 1px solid var(--line);
        border-radius: 20px;
        box-shadow: 0 16px 40px rgba(15, 23, 42, 0.08);
        overflow: hidden;
        background: #fff;
    }

    .qr-card .qr-head {
        background: var(--ink);
        color: #fff;
        padding: 20px 26px;
    }

    .qr-card .qr-head h5 {
        margin: 0;
        font-weight: 800;
    }

    .qr-card .qr-head i {
        color: var(--brand);
    }

    .qr-body {
        padding: 32px;
        text-align: center;
    }

    .qr-box {
        display: inline-block;
        padding: 18px;
        background: #fff;
        border: 2px dashed var(--line);
        border-radius: 18px;
    }

    .qr-box svg {
        max-width: 280px;
        width: 100%;
        height: auto;
        display: block;
    }

    .booking-code-pill {
        display: inline-block;
        background: var(--brand-soft);
        color: var(--brand-dark);
        border: 1px solid #bfe8d4;
        padding: 7px 16px;
        border-radius: 999px;
        font-family: Consolas, Monaco, monospace;
        font-weight: 700;
        letter-spacing: 0.5px;
    }

    .qr-info {
        text-align: left;
        max-width: 420px;
        margin: 24px auto 0;
    }

    .qr-info .row-line {
        display: flex;
        justify-content: space-between;
        padding: 10px 0;
        border-bottom: 1px dashed var(--line);
        font-size: 0.95rem;
        color: var(--muted);
    }

    .qr-info .row-line strong {
        color: var(--ink);
    }
</style>
@endpush

@section('content')
<div class="row justify-content-center py-4">
    <div class="col-lg-6">
        <a href="{{ route('bookings.show', $booking) }}" class="btn btn-outline-secondary btn-sm mb-3 rounded-pill px-3">
            <i class="bi bi-arrow-left me-1"></i> Quay lại chi tiết đặt sân
        </a>

        <div class="qr-card">
            <div class="qr-head">
                <h5><i class="bi bi-qr-code me-2"></i>Mã QR check-in</h5>
            </div>
            <div class="qr-body">
                <!-- <div class="booking-code-pill mb-3">{{ $booking->booking_code }}</div> -->

                <div class="qr-box">
                    {!! $qr_code !!}
                </div>

                <p class="text-muted small mb-0 mt-3">
                    <i class="bi bi-info-circle me-1"></i>
                    Xuất trình mã QR này tại sân để nhân viên check-in.
                </p>

                <div class="qr-info">
                    <div class="row-line">
                        <span>Khách hàng</span>
                        <strong>{{ $booking->user->name }}</strong>
                    </div>
                    <div class="row-line">
                        <span>Trạng thái</span>
                        <strong>{{ $booking->status === 'CHECKED_IN' ? 'Đã nhận sân' : 'Đã xác nhận' }}</strong>
                    </div>
                    @foreach($booking->bookingDetails as $detail)
                    <div class="row-line">
                        <span>{{ $detail->court->name }}</span>
                        <strong>{{ $detail->booking_date->format('d/m/Y') }} · {{ $detail->timeSlot->name }}</strong>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
@endsection