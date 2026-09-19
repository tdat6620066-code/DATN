@php
    $operations = app(\App\Services\BookingOperationsService::class);
    $timing = $operations->timing($booking);
    $due = $operations->amountDue($booking);
    $rentals = $operations->activeLines($booking)->where('requires_return', true)->with('item')->get();
    $start = $timing['start']; $end = $timing['end'];
    $actor = auth()->user();
@endphp
<section class="card p-4 my-3" id="booking-operations">
    <h2 class="h5">Nhận sân và trả sân</h2>
    @if($booking->extended_from_id)<p>Đơn gia hạn của buổi #{{ $booking->extended_from_id }}.</p>@endif
    @foreach(\App\Models\Booking::where('extended_from_id', $booking->id)->get() as $extension)
        <p>Buổi gia hạn: <a href="{{ route($actor->role === 'ADMIN' ? 'admin.bookings.show' : 'employee.bookings.show', $extension) }}">{{ $extension->booking_code }}</a></p>
    @endforeach
    <p>Nhận sân: {{ $booking->checked_in_at?->format('d/m/Y H:i') ?? 'Chưa nhận' }} · {{ $booking->checkedInBy?->name }}</p>
    <p>Trả sân: {{ $booking->checked_out_at?->format('d/m/Y H:i') ?? 'Chưa trả' }} · {{ $booking->checkedOutBy?->name }}</p>
    @if($booking->no_show_at)<p>Khách không đến · {{ $booking->no_show_at->format('d/m/Y H:i') }}. Không tự động hoàn tiền.</p>@endif
    @if($booking->checkout_exception_reason)<p class="alert alert-warning">Trả sân ngoại lệ: {{ $booking->checkout_exception_reason }}. Khoản chưa thu và đồ thuê chưa trả vẫn cần xử lý.</p>@endif
    @if($start && $booking->checked_in_at?->gt($start))
        <p class="text-warning">Nhận sân muộn {{ (int) $start->diffInMinutes($booking->checked_in_at) }} phút. Giờ kết thúc vẫn là {{ $end->format('H:i') }}.</p>
    @endif
    @if($booking->status === 'CHECKED_IN' && $end)<p>Thời gian còn lại: {{ max(0, (int) now()->diffInMinutes($end, false)) }} phút.</p>@endif
    @if($booking->status === 'CONFIRMED' && $actor->hasPermission('bookings.checkin'))
        <p class="small text-muted">Nhận sân sớm tối đa {{ config('booking.checkin_early_minutes') }} phút. Nhận sớm không thay đổi giờ được sử dụng sân.</p>
        <form method="POST" action="{{ route('operations.check-in', $booking) }}">@csrf<button class="btn btn-success">Xác nhận nhận sân</button></form>
        @if($start && now()->gte($start->copy()->addMinutes(config('booking.no_show_after_minutes'))))
            <form class="mt-2" method="POST" action="{{ route('operations.no-show', $booking) }}">@csrf<button class="btn btn-outline-warning">Đánh dấu khách không đến</button></form>
        @endif
    @endif
    @if($rentals->isNotEmpty())
        <h3 class="h6 mt-3">Kiểm tra trả đồ thuê</h3>
        @foreach($rentals as $line)
            <div class="border-top py-2"><strong>{{ $line->item?->name }}</strong> · Đã trả {{ $line->returned_quantity }}/{{ $line->quantity }}
            @if($actor->hasPermission('services.manage') && ($booking->status === 'CHECKED_IN' || $booking->checkout_exception_reason) && $line->returned_quantity < $line->quantity)
                <form class="d-flex gap-2 mt-2" method="POST" action="{{ route('operations.return', [$booking, $line]) }}">@csrf
                    <input class="form-control" style="max-width:100px" type="number" name="returned_quantity" aria-label="Tổng số lượng đã trả" min="{{ $line->returned_quantity }}" max="{{ $line->quantity }}" value="{{ $line->quantity }}" required>
                    <button class="btn btn-outline-success btn-sm">Xác nhận đã trả</button>
                </form>
            @endif</div>
        @endforeach
        <p class="small text-muted">Đồ hỏng hoặc mất: báo sự cố để xử lý, không tự động tính phạt.</p>
    @endif
    <p class="fw-bold mt-3">Dịch vụ còn phải thu: {{ number_format($due, 0, ',', '.') }}đ</p>
    @if($booking->status === 'CHECKED_IN' && $actor->hasPermission('bookings.checkout'))
        @if($actor->role === 'EMPLOYEE')
            @include('employee.partials.checkout-modal')
        @else
        <form method="POST" action="{{ route('operations.checkout', $booking) }}">@csrf
            @if($due > 0 && $actor->hasPermission('payments.counter'))<input type="hidden" name="amount" value="{{ $due }}">@endif
            @if($booking->serviceOrders()->whereIn('status', ['PENDING', 'PAID'])->exists() && $actor->hasPermission('services.manage'))
                <label class="d-block mb-2"><input type="checkbox" name="confirm_services_received" value="1"> Xác nhận khách đã nhận tất cả dịch vụ liệt kê</label>
            @endif
            <button class="btn btn-success">{{ $due > 0 && $actor->hasPermission('payments.counter') ? 'Xác nhận đã thu '.number_format($due, 0, ',', '.').'đ tiền mặt và trả sân' : 'Hoàn tất trả sân' }}</button>
        </form>
        @endif
        @if($actor->role === 'ADMIN')
            <details class="mt-3"><summary>Trả sân ngoại lệ (quản trị viên)</summary>
                <form class="mt-2" method="POST" action="{{ route('operations.checkout', $booking) }}">@csrf
                    <label>Lý do ngoại lệ<textarea name="exception_reason" minlength="10" maxlength="1000" class="form-control" required></textarea></label>
                    <p class="small">Không ghi nhận tiền đã thu hoặc đồ đã trả. Công nợ và lịch sử ngoại lệ được giữ lại để đối soát.</p>
                    <button class="btn btn-outline-danger">Xác nhận trả sân ngoại lệ</button>
                </form>
            </details>
        @endif
    @endif
    @if($actor->role === 'ADMIN' && $booking->status === 'CHECKED_IN' && $end && $actor->hasPermission('bookings.checkout') && $actor->hasPermission('payments.counter'))
        @php
            $lastDetail = $booking->bookingDetails()->where('status', '!=', 'CANCELLED')->with('timeSlot')->get()->sortBy('timeSlot.end_time')->last();
            $nextSlot = \App\Models\TimeSlot::where('status', 'ACTIVE')->where('start_time', $lastDetail?->timeSlot->end_time)->first();
            $extensionPrice = $nextSlot && $lastDetail ? app(\App\Services\BookingService::class)->getCurrentPrice($lastDetail->court_id, $nextSlot->id, $lastDetail->booking_date) : null;
        @endphp
        <details class="mt-3"><summary>Gia hạn giờ chơi</summary>
            @if($nextSlot && $extensionPrice !== null)
                <p>{{ $nextSlot->name }} · {{ number_format($extensionPrice, 0, ',', '.') }}đ. Hệ thống kiểm tra sân trống khi xác nhận; tạo đơn gia hạn riêng để giữ nguyên hóa đơn cũ.</p>
                <form method="POST" action="{{ route('operations.extend', $booking) }}">@csrf<input type="hidden" name="time_slot_id" value="{{ $nextSlot->id }}"><input type="hidden" name="amount" value="{{ $extensionPrice }}"><button class="btn btn-outline-success">Xác nhận đã thu tiền mặt và gia hạn</button></form>
            @else<p>Chưa có khung giờ hoặc giá gia hạn phù hợp.</p>@endif
        </details>
    @endif
    @if($actor->hasPermission('incidents.manage'))<a class="d-block mt-3" href="{{ route($actor->isAdmin() ? 'admin.incidents.index' : 'employee.incidents.index', ['booking_id' => $booking->id]) }}">Báo cáo sự cố</a>@endif
</section>
