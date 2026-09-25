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
<div class="sz-choice-list">
@if(app(\App\Services\BookingRefundPolicy::class)->eligible($booking))
<details class="sz-choice" name="resolution-{{ $resolution->id }}">
<summary><span class="sz-choice-icon"><i class="bi bi-wallet2" aria-hidden="true"></i></span><span class="sz-choice-copy"><strong>Hoàn toàn booking</strong><small>Đề nghị hoàn {{ number_format(app(\App\Services\BookingRefundPolicy::class)->remaining($booking)) }}đ cho toàn bộ booking</small></span><i class="bi bi-chevron-down sz-choice-chevron" aria-hidden="true"></i></summary>
<div class="sz-choice-body"><form method="POST" action="{{ route('incident-resolutions.choose', $resolution) }}">@csrf<input type="hidden" name="choice" value="REFUND">
<h4 class="h6">Thông tin nhận hoàn tiền</h4>
<p>Booking {{ $booking->booking_code }} · Số tiền dự kiến hoàn: {{ number_format(app(\App\Services\BookingRefundPolicy::class)->remaining($booking)) }}đ</p>
@include('partials.refund-bank-fields')
<div class="sz-action-bar"><button class="sz-action sz-action--primary"><i class="bi bi-send" aria-hidden="true"></i>Gửi yêu cầu hoàn tiền</button></div></form></div></details>
@else
<p class="alert alert-info">Booking đã check-in không được hoàn tiền.</p>
@endif

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
