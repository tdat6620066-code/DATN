<div class="recurring-card" id="schedule-preview">
    <div class="card-head"><i class="bi bi-calendar3"></i><h2>Kiểm tra từng buổi</h2></div>
    <div class="card-body">
        <div class="row g-2 mb-4">
            <div class="col-4"><div class="border rounded-3 p-3"><small class="text-muted d-block">Tổng khung giờ</small><strong class="fs-4">{{ count($preview['schedules']) + count($preview['conflicts']) }}</strong></div></div>
            <div class="col-4"><div class="border rounded-3 p-3"><small class="text-muted d-block">Có thể đặt</small><strong class="fs-4 text-success">{{ count($preview['schedules']) }}</strong></div></div>
            <div class="col-4"><div class="border rounded-3 p-3"><small class="text-muted d-block">Cần xử lý</small><strong class="fs-4 text-warning">{{ count($preview['conflicts']) }}</strong></div></div>
        </div>
        <p class="hint">Chỗ trống và giá được kiểm tra lại khi xác nhận. Lịch đã có khách đặt được giữ nguyên; sân hoặc giờ thay thế chỉ áp dụng khi bạn chọn.</p>
        @if(count($preview['schedules']) + count($preview['conflicts']) === 0)
            <div class="alert alert-info">Không có buổi nào khớp khoảng ngày đã chọn. Hãy điều chỉnh lịch phía trên.</div>
        @else
        <form action="{{ route('bookings.recurring.review') }}" method="POST">
            @csrf
            <input type="hidden" name="preview_token" value="{{ $draft['token'] }}">
            @if(count($preview['conflicts']))
                <div class="conflicts"><strong>{{ count($preview['conflicts']) }} khung giờ cần bạn chọn phương án</strong><p class="mb-0 mt-1">Các khung giờ còn trống vẫn được giữ trong lịch dự kiến.</p></div>
                @foreach($preview['conflicts'] as $item)
                <fieldset class="border rounded-3 p-3 mb-3">
                    <legend class="float-none w-auto px-2 fs-6 fw-semibold">{{ $item['date']->format('d/m/Y') }} · {{ $item['time_slot'] }}</legend>
                    <p class="small text-muted">{{ $item['court_name'] }} · {{ $item['reason'] }}</p>
                    <label class="form-label" for="choice-{{ $item['key'] }}">Phương án cho khung giờ này</label>
                    <select class="form-select" id="choice-{{ $item['key'] }}" name="choices[{{ $item['key'] }}]" required>
                        <option value="">Chọn phương án</option>
                        <option value="skip" @selected(($draft['choices'][$item['key']] ?? '') === 'skip')>Bỏ qua khung giờ này — không tính tiền</option>
                        @foreach(['court' => 'Sân khác cùng giờ', 'time' => 'Giờ khác cùng sân (gần nhất trước)'] as $kind => $label)
                            <optgroup label="{{ $label }}">
                            @foreach($item['alternatives'] as $value => $alternative)
                                @if($alternative['kind'] === $kind)
                                <option value="{{ $value }}" @selected(($draft['choices'][$item['key']] ?? '') === $value)>{{ $alternative['court_name'] }} · {{ $alternative['time_slot'] }} · {{ number_format($alternative['price'], 0, ',', '.') }}đ</option>
                                @endif
                            @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    @if(!count($item['alternatives']))<p class="small text-muted mt-2 mb-0">Chưa có sân hoặc giờ thay thế cùng thời lượng. Bạn có thể bỏ qua khung giờ này hoặc điều chỉnh lịch.</p>@endif
                </fieldset>
                @endforeach
            @endif
            @if(count($preview['schedules']))
            <details class="mb-3"><summary class="small text-success py-2">Xem {{ count($preview['schedules']) }} khung giờ có thể đặt</summary>
                <div class="schedule-list">@foreach($preview['schedules'] as $item)<div class="schedule-row"><span>{{ $item['date']->format('d/m/Y') }} · {{ $item['court_name'] }} · {{ $item['time_slot'] }}</span><strong class="text-nowrap">{{ number_format($item['price'], 0, ',', '.') }}đ</strong></div>@endforeach</div>
            </details>
            @endif
            <button class="preview-btn" type="submit">Kiểm tra phương án & số tiền</button>
        </form>
        @endif
    </div>
</div>
@isset($draft['quote'])
@php($quote = $draft['quote'])
<div class="recurring-card" id="final-schedule">
    <div class="card-head"><i class="bi bi-check2-square"></i><h2>Xác nhận lịch đã chọn</h2></div>
    <div class="card-body">
        <p class="hint">{{ count($quote['selected']) }} khung giờ được gộp thành {{ $quote['booking_count'] ?? count($quote['selected']) }} booking. Các giờ liền nhau cùng sân, cùng ngày nằm trong một booking. Bỏ qua {{ count($quote['occurrences']) - count($quote['selected']) }} khung giờ.</p>
        <div class="schedule-list">
        @foreach($quote['selected'] as $key => $item)
            <div class="schedule-row"><span>{{ $item['date']->format('d/m/Y') }} · {{ $item['court_name'] }}<small class="d-block text-muted">{{ $item['time_slot'] }} @if($quote['occurrences'][$key]['choice'] !== 'original') · Phương án thay thế bạn chọn @endif</small></span><strong class="text-nowrap">{{ number_format($item['price'], 0, ',', '.') }}đ</strong></div>
        @endforeach
        </div>
        <div class="summary-row"><span>Tạm tính</span><strong>{{ number_format($quote['subtotal'], 0, ',', '.') }}đ</strong></div>
        @if($quote['discount'] > 0)<div class="summary-row"><span>Ưu đãi cho cả nhóm lịch</span><strong>−{{ number_format($quote['discount'], 0, ',', '.') }}đ</strong></div>@endif
        <div class="preview-total"><span>Tổng thanh toán</span><strong>{{ number_format($quote['total'], 0, ',', '.') }}đ</strong></div>
        <p class="hint mt-3">Thanh toán 100% một lần cho toàn bộ lịch. Giữ chỗ 15 phút sau xác nhận. Giá ưu đãi được chia theo từng buổi để đổi lịch và hoàn tiền khi có sự cố.</p>
        <form method="POST" action="{{ route('bookings.store-recurring') }}">
            @csrf
            <input type="hidden" name="preview_token" value="{{ $draft['token'] }}">
            <input type="hidden" name="confirmation_key" value="{{ $draft['confirmation_key'] }}">
            <label class="d-flex gap-2 align-items-start small"><input class="form-check-input mt-1" type="checkbox" name="confirmed" value="1" required><span>Tôi xác nhận các buổi, sân/giờ thay thế và số tiền trên.</span></label>
            <button class="confirm-btn" type="submit">Xác nhận và giữ chỗ {{ $quote['booking_count'] ?? count($quote['selected']) }} buổi</button>
        </form>
    </div>
</div>
@endisset
