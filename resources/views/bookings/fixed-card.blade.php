@php
    $group = $booking->fixedBooking;
    $groupStatus = ['AWAITING_PAYMENT'=>'Chờ thanh toán', 'PAYMENT_FAILED'=>'Thanh toán chưa thành công', 'ACTIVE'=>'Đã xác nhận', 'COMPLETED'=>'Hoàn thành', 'EXPIRED'=>'Hết hạn', 'LEGACY'=>'Lịch cố định'];
@endphp
<article class="card mb-4" data-fixed-booking="{{ $group->id }}">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
            <div><h5 class="mb-1">{{ $group->code }}</h5><p class="small text-muted mb-0">Lịch cố định · Đặt lúc {{ $group->created_at->format('d/m/Y H:i') }}</p></div>
            <span class="badge bg-light text-dark">{{ $groupStatus[$group->status] ?? $group->status }}</span>
        </div>
        <p>{{ $group->bookings->count() }} buổi · {{ \Carbon\Carbon::parse($group->definition['start_date'])->format('d/m/Y') }} – {{ \Carbon\Carbon::parse($group->definition['end_date'])->format('d/m/Y') }}</p>
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <strong>{{ number_format($group->total_price ?? $group->bookings->sum('total_amount'), 0, ',', '.') }}đ</strong>
            <a class="btn btn-outline-primary btn-sm" href="{{ route('bookings.fixed.show', $group) }}">{{ in_array($group->status, ['AWAITING_PAYMENT','PAYMENT_FAILED']) ? 'Xem lịch và thanh toán' : 'Xem các buổi đã đặt' }}</a>
        </div>
    </div>
</article>
