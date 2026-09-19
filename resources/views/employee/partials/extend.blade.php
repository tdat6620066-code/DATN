@include('employee.partials.playing-session')
@if($booking->status === 'CHECKED_IN' && auth()->user()->hasPermission('payments.counter') && auth()->user()->hasPermission('bookings.view'))
<section class="staff-card p-4 mt-3" id="extend-court">
    <h2 class="h5">Tiếp tục lượt chơi</h2>
    <p class="text-muted">Kiểm tra khung giờ liền kề và các sân thay thế trước khi gia hạn.</p>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#venue-extension">Gia hạn sân</button>
</section>
<div class="modal fade" id="venue-extension" tabindex="-1" aria-labelledby="venue-extension-title" aria-hidden="true">
<div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered"><div class="modal-content">
<div class="modal-header"><h2 class="modal-title fs-5" id="venue-extension-title">Gia hạn sân</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button></div>
<div class="modal-body">
@foreach($booking->bookingDetails->where('status', 'CHECKED_IN') as $candidate)
    @php
        $extensionError = null;
        try {
            $extensionService = app(\App\Services\BookingExtensionService::class);
            $detail = $extensionService->source($booking, $candidate->id);
            $slots = $extensionService->nextSlots($detail, 1);
            $options = $extensionService->options($detail, $slots);
        } catch (\DomainException $exception) { $extensionError = $exception->getMessage(); }
    @endphp
    <section class="mb-4">
        <h3 class="h6">{{ $candidate->court->name }}</h3>
        <p>Hiện tại: {{ $candidate->booking_date->format('d/m/Y') }} · {{ substr($candidate->timeSlot->start_time, 0, 5) }} – {{ substr($candidate->timeSlot->end_time, 0, 5) }}</p>
        @if($extensionError)<div class="alert alert-warning" role="status">{{ $extensionError }}</div>
        @else
            <p class="fw-semibold">Slot tiếp theo: {{ substr($slots->first()->start_time, 0, 5) }} – {{ substr($slots->last()->end_time, 0, 5) }}</p>
            @include('employee.partials.extension-choices')
            <a class="btn btn-outline-primary mt-3" href="{{ route('employee.bookings.extension-options', ['booking'=>$booking, 'detail_id'=>$detail->id]) }}"><i class="bi bi-calendar3 me-2" aria-hidden="true"></i>Xem bảng giờ trống / sân trống</a>
        @endif
    </section>
@endforeach
<p class="small text-muted mb-0">Sau khi tạo đơn gia hạn, tiếp tục xác nhận thanh toán tại trang chi tiết đơn mới.</p>
</div></div></div></div>
@endif
