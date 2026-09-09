@include('partials.incident-ui')
@php($resolutions = $booking->incidentResolutions()->with(['incident', 'refundRequests.refund'])->get())
@if($resolutions->isNotEmpty())
<section class="sz-work-panel sz-resolution">
<div class="sz-resolution-heading"><i class="bi bi-shield-exclamation" aria-hidden="true"></i><div><h3>Phương án hỗ trợ</h3><p class="sz-help">Chọn cách xử lý phù hợp cho lượt sân gặp sự cố.</p></div></div>
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
@foreach($resolutions as $resolution)
<article class="sz-resolution-item">

<strong>{{ $resolution->original_slot['court'] }} · {{ $resolution->original_slot['date'] }} · {{ $resolution->original_slot['start_time'] }}–{{ $resolution->original_slot['end_time'] }}</strong>
<p class="sz-help">{{ $resolution->incident->description }}</p>
<div class="sz-resolution-meta"><span class="sz-resolution-amount">Giá trị chưa sử dụng: {{ number_format($resolution->refund_amount) }}đ</span></div>
@if($resolution->status === 'AWAITING_CHOICE' && auth()->user()->role === 'CUSTOMER')
<p class="sz-help">Đổi lịch hoặc sân được kiểm tra chỗ trống khi xác nhận. SmashZone chịu phần giá tăng và hoàn phần chênh lệch nếu giá thấp hơn.</p>
<div class="sz-choice-list">
<details class="sz-choice" name="resolution-{{ $resolution->id }}">
<summary><span class="sz-choice-icon"><i class="bi bi-wallet2" aria-hidden="true"></i></span><span class="sz-choice-copy"><strong>Hoàn tiền</strong><small>Đề nghị hoàn {{ number_format($resolution->refund_amount) }}đ cho phần chưa sử dụng</small></span><i class="bi bi-chevron-down sz-choice-chevron" aria-hidden="true"></i></summary>
<div class="sz-choice-body"><form method="POST" action="{{ route('incident-resolutions.choose', $resolution) }}">@csrf<input type="hidden" name="choice" value="REFUND">
<h4 class="h6">Thông tin nhận hoàn tiền</h4>
<p>Booking {{ $booking->booking_code }} · Số tiền dự kiến hoàn: {{ number_format($resolution->refund_amount) }}đ</p>
@include('partials.refund-bank-fields')
<div class="sz-action-bar"><button class="sz-action sz-action--primary"><i class="bi bi-send" aria-hidden="true"></i>Gửi yêu cầu hoàn tiền</button></div></form></div></details>
<details class="sz-choice" name="resolution-{{ $resolution->id }}">
<summary><span class="sz-choice-icon"><i class="bi bi-calendar2-week" aria-hidden="true"></i></span><span class="sz-choice-copy"><strong>Đổi lịch chơi</strong><small>Giữ nguyên sân, chọn ngày và giờ khác</small></span><i class="bi bi-chevron-down sz-choice-chevron" aria-hidden="true"></i></summary>
<div class="sz-choice-body"><form method="POST" action="{{ route('incident-resolutions.choose', $resolution) }}">@csrf<input type="hidden" name="choice" value="RESCHEDULE"><input type="hidden" name="court_id" value="{{ $resolution->original_slot['court_id'] }}">
<div class="row g-3"><div class="col-sm-6"><label for="new-date-{{ $resolution->id }}">Ngày mới</label><input id="new-date-{{ $resolution->id }}" type="date" name="date" min="{{ now()->toDateString() }}" class="form-control mt-1" required></div>
<div class="col-sm-6"><label for="new-time-{{ $resolution->id }}">Khung giờ mới</label><select id="new-time-{{ $resolution->id }}" name="time_slot_id" class="form-select mt-1">@foreach(\App\Models\TimeSlot::where('status','ACTIVE')->orderBy('start_time')->get() as $slot)<option value="{{ $slot->id }}">{{ $slot->start_time }}–{{ $slot->end_time }}</option>@endforeach</select></div></div>
<p class="sz-help">Chọn khung giờ có cùng thời lượng chưa sử dụng.</p>
<div class="sz-action-bar"><button class="sz-action sz-action--primary"><i class="bi bi-calendar-check" aria-hidden="true"></i>Xác nhận đổi lịch</button></div></form></div></details>
<details class="sz-choice" name="resolution-{{ $resolution->id }}">
<summary><span class="sz-choice-icon"><i class="bi bi-arrow-left-right" aria-hidden="true"></i></span><span class="sz-choice-copy"><strong>Đổi sân</strong><small>Chọn sân khác vào cùng ngày và khung giờ</small></span><i class="bi bi-chevron-down sz-choice-chevron" aria-hidden="true"></i></summary>
<div class="sz-choice-body"><form method="POST" action="{{ route('incident-resolutions.choose', $resolution) }}">@csrf<input type="hidden" name="choice" value="CHANGE_COURT"><input type="hidden" name="date" value="{{ $resolution->original_slot['date'] }}"><input type="hidden" name="time_slot_id" value="{{ $resolution->original_slot['time_slot_id'] }}">
<label for="new-court-{{ $resolution->id }}">Sân mới</label><select id="new-court-{{ $resolution->id }}" name="court_id" class="form-select mt-1">@foreach(\App\Models\Court::where('status','ACTIVE')->where('id','!=',$resolution->original_slot['court_id'])->orderBy('name')->get() as $court)<option value="{{ $court->id }}">{{ $court->name }}</option>@endforeach</select>
<div class="sz-action-bar"><button class="sz-action sz-action--primary"><i class="bi bi-check2-circle" aria-hidden="true"></i>Xác nhận đổi sân</button></div></form></div></details>
</div>
@else
<p>{{ ['AWAITING_CHOICE' => 'Chờ khách lựa chọn', 'REFUND_PENDING' => 'Yêu cầu hoàn tiền đang xử lý', 'RESOLVED' => 'Đã xử lý'][$resolution->status] ?? $resolution->status }} · {{ ['REFUND' => 'Hoàn tiền', 'RESCHEDULE' => 'Đổi lịch', 'CHANGE_COURT' => 'Đổi sân'][$resolution->choice] ?? '' }}</p>
@endif
@foreach($resolution->refundRequests as $refundRequest)<p>Yêu cầu #{{ $refundRequest->id }}: {{ number_format($refundRequest->amount) }}đ · {{ $refundRequest->payout_label }}</p>@endforeach
<details class="sz-disclosure mt-3"><summary>Xem tiến trình xử lý</summary>
@include('partials.incident-timeline', ['timeline'=>app(\App\Services\IncidentTimelineService::class)->resolution($resolution)])
</details>
</article>
@endforeach
</section>
@endif
