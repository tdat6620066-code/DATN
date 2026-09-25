@extends(auth()->user()->role === 'ADMIN' ? 'layouts.admin' : (auth()->user()->role === 'EMPLOYEE' ? 'layouts.employee' : 'layouts.app'))
@section('page_heading','Chi tiết yêu cầu hỗ trợ')
@section('content')
@include('partials.incident-ui')
@include('partials.detail-styles')
<div class="sz-ticket py-3">
<header class="sz-ticket-header">
<div><a class="small text-decoration-none" href="{{ auth()->user()->role === 'CUSTOMER' ? route('bookings.show', $ticket->booking) : route('incident-tickets.index') }}">← {{ auth()->user()->role === 'CUSTOMER' ? 'Chi tiết booking' : 'Yêu cầu hỗ trợ' }}</a><h1>Chi tiết yêu cầu</h1><div class="sz-ticket-code">{{ $ticket->incident_code }}</div></div>
<span class="sz-ticket-status">{{ \App\Models\CourtIncident::TICKET_STATUSES[$ticket->status] }}</span>
</header>
<div class="sz-ticket-grid"><div>
<section class="sz-work-panel mb-3">
<h2 class="h6 mb-4">Thông tin đơn & sự cố</h2>
<dl class="sz-ticket-facts">
<div><dt>Mã đặt sân</dt><dd>{{ $ticket->booking->booking_code }}</dd></div>
<div><dt>Khách hàng</dt><dd>{{ $ticket->reporter->name }}</dd></div>
<div><dt>Sân</dt><dd>{{ $ticket->court->name }}</dd></div>
<div><dt>Lịch chơi</dt><dd>{{ \Carbon\Carbon::parse($ticket->booking_snapshot['date'] ?? $ticket->detail->booking_date)->format('d/m/Y') }} · {{ substr($ticket->booking_snapshot['start_time'] ?? $ticket->detail->timeSlot->start_time,0,5) }}–{{ substr($ticket->booking_snapshot['end_time'] ?? $ticket->detail->timeSlot->end_time,0,5) }}</dd></div>
<div><dt>Loại sự cố</dt><dd>{{ \App\Models\CourtIncident::TYPES[$ticket->type] }}</dd></div>
<div><dt>Mong muốn của khách</dt><dd>{{ \App\Models\CourtIncident::SOLUTIONS[$ticket->requested_solution] }}</dd></div>
</dl>
@if($ticket->refund_recipient && !$ticket->resolutions()->exists())
<div class="border-top pt-3"><h3 class="h6">Thông tin nhận hoàn tiền</h3>
<p>{{ $ticket->refund_recipient['bank_name'] }} · ********{{ substr($ticket->refund_recipient['bank_account_number'], -4) }}</p>
@if(auth()->id() === $ticket->reported_by || auth()->user()->hasPermission('refunds.process'))<p>{{ $ticket->refund_recipient['bank_account_holder'] }}</p>@endif
<small>Đã xác nhận: {{ \Carbon\Carbon::parse($ticket->refund_recipient['confirmed_at'])->format('d/m/Y H:i') }}</small></div>
@endif
<p class="text-break border-top pt-3 mb-0">{{ $ticket->description }}</p>
@if($ticket->evidences->isNotEmpty())
<div class="mt-3"><h3 class="h6">Ảnh/video minh chứng ({{ $ticket->evidences->count() }})</h3>
<div class="d-flex flex-wrap gap-3">
@foreach($ticket->evidences as $evidence)
    @if(str_starts_with($evidence->file_type, 'image/'))
    <a href="{{ route('incident-tickets.evidence', $evidence) }}" target="_blank" rel="noopener" title="Xem ảnh đầy đủ">
        <img src="{{ route('incident-tickets.evidence', $evidence) }}" alt="Minh chứng #{{ $evidence->id }}" loading="lazy" class="rounded border" style="width:160px;height:130px;object-fit:contain;background:#f8fafc">
    </a>
    @else
    <video src="{{ route('incident-tickets.evidence', $evidence) }}" controls preload="metadata" class="rounded border" style="max-width:100%;width:280px;max-height:220px" aria-label="Video minh chứng #{{ $evidence->id }}"></video>
    @endif
@endforeach
</div></div>
@endif
<div class="small text-muted border-top pt-3 mt-3">Phụ trách: {{ $ticket->assignee?->name ?? 'Chưa phân công' }}
@if($ticket->proposed_solution)<div class="mt-1">Đề xuất: {{ \App\Models\CourtIncident::SOLUTIONS[$ticket->proposed_solution] ?? $ticket->proposed_solution }} @if($ticket->proposed_amount) · {{ number_format($ticket->proposed_amount) }}đ @endif</div>@endif
</div>
</section>
@if(auth()->user()->role === 'CUSTOMER')
<a class="sz-action mb-3" href="{{ route('bookings.show',$ticket->booking) }}"><i class="bi bi-arrow-left-right" aria-hidden="true"></i>Xem phương án hỗ trợ</a>
@if(in_array($ticket->status,['PENDING','REVIEWING','NEED_MORE_INFO']))
<form method="POST" action="{{ route('incident-tickets.supplement',$ticket) }}" enctype="multipart/form-data" class="sz-work-panel">@csrf<label>Bổ sung thông tin</label><textarea name="note" class="form-control mb-2" maxlength="4000" required>{{ old('note') }}</textarea>@include('partials.media-upload', ['label' => 'Thêm ảnh/video minh chứng', 'hint' => 'Tối đa 5 tệp/lần, 20MB/tệp; tổng 10 tệp cho một yêu cầu.'])<button class="sz-action sz-action--primary mt-3">Gửi bổ sung</button></form>
@endif
@else
@php($pendingResolution = $ticket->status === 'APPROVED' && $ticket->resolutions()->where('status', '!=', 'RESOLVED')->exists())
@if($pendingResolution)
<section class="sz-work-panel mb-3">
<h2 class="h6">Đã chấp thuận · Chưa hoàn tất hoàn tiền</h2>
@if($ticket->resolutions->contains(fn($resolution) => $resolution->refundRequests->isNotEmpty()))
<p class="mb-0">Mở khoản hoàn bên dưới để tiếp tục chi trả. Yêu cầu sẽ tự đóng sau khi xác nhận hoàn tiền thành công.</p>
@else
<p class="mb-0">Khách cần mở chi tiết booking, chọn “Hoàn toàn booking” và gửi thông tin nhận tiền. Sau đó quản trị viên duyệt và thực hiện chi trả. Chấp thuận hỗ trợ chưa có nghĩa là đã hoàn tiền.</p>
@endif
</section>
@endif
@if(!in_array($ticket->status,['REJECTED','RESOLVED']) && !$pendingResolution)
<form method="POST" action="{{ route('incident-tickets.review',$ticket) }}" class="sz-work-panel mb-3">@csrf
<h2 class="h6 mb-3">{{ auth()->user()->role === 'ADMIN' ? 'Xem xét yêu cầu' : 'Báo cáo Admin' }}</h2>
<label>Kết quả xác minh</label><textarea name="note" class="form-control mb-3" rows="2" maxlength="4000" required>{{ old('note') }}</textarea>
@if(auth()->user()->role === 'ADMIN')<label>Nhân sự phụ trách</label><select name="assigned_to" class="form-select mb-3"><option value="">Giữ người đang phụ trách</option>@foreach($staff as $person)<option value="{{ $person->id }}">{{ $person->name }}</option>@endforeach</select>@endif
<div class="row g-2 mb-3">
<div class="col-md-7"><label for="review-solution" class="small mb-1">Đề xuất xử lý</label><select id="review-solution" name="proposed_solution" class="form-select">@foreach(\App\Models\CourtIncident::AVAILABLE_SOLUTIONS as $code=>$label)<option value="{{ $code }}" @selected(old('proposed_solution', $ticket->proposed_solution ?? $ticket->requested_solution) === $code)>{{ $label }}</option>@endforeach</select></div>
<div class="col-md-5"><label for="review-amount" class="small mb-1">Số tiền hoàn toàn booking (đ)</label><input id="review-amount" name="amount" type="number" min="0.01" step="0.01" value="{{ app(\App\Services\BookingRefundPolicy::class)->remaining($ticket->booking) }}" class="form-control" readonly><small class="text-muted">Hoàn booking được tự tính từ khoản đã thanh toán, trừ khoản đã hoàn. Booking đã check-in không được hoàn.</small></div>
</div>
<p class="small text-muted">{{ auth()->user()->role === 'ADMIN' ? 'Kiểm tra minh chứng trước khi quyết định hỗ trợ.' : 'Admin sẽ xem xét báo cáo trước khi duyệt.' }}</p>
<div class="sz-action-bar">
@if($ticket->status !== 'APPROVED')
@if(auth()->user()->role === 'EMPLOYEE')<button name="action" value="PROPOSE" class="sz-action sz-action--primary sz-action--small"><i class="bi bi-send-check" aria-hidden="true"></i>Gửi Admin duyệt</button>@endif
<details class="sz-more"><summary class="sz-action"><i class="bi bi-three-dots" aria-hidden="true"></i>Thao tác khác</summary><div class="sz-more-content">
<button name="action" value="REVIEWING" class="sz-action sz-action--small">Tiếp nhận</button><button name="action" value="NEED_MORE_INFO" class="sz-action sz-action--small">Yêu cầu bổ sung</button>
</div></details>
@endif
@if(auth()->user()->role === 'ADMIN')
@if($ticket->status !== 'APPROVED')<button name="action" value="APPROVED" class="sz-action sz-action--primary"><i class="bi bi-check2-circle" aria-hidden="true"></i>Chấp thuận hỗ trợ</button><button name="action" value="REJECTED" class="sz-action sz-action--danger">Từ chối</button>@else<button name="action" value="RESOLVED" class="sz-action sz-action--primary"><i class="bi bi-check2-all" aria-hidden="true"></i>Hoàn tất hỗ trợ</button>@endif
@endif
</div></form>
@endif
@if(auth()->user()->role === 'ADMIN')<a class="sz-action" href="{{ route('admin.bookings.show',$ticket->booking) }}"><i class="bi bi-wallet2" aria-hidden="true"></i>Xem đơn & xử lý hoàn tiền</a>@endif
@endif
@if(auth()->user()->role === 'CUSTOMER' && $ticket->status === 'APPROVED')
@include('partials.incident-resolutions', ['booking' => $ticket->booking])
@endif
@foreach($ticket->resolutions as $resolution)
@foreach($resolution->refundRequests as $refund)
<p class="mt-3">Yêu cầu hoàn #{{ $refund->id }} · {{ number_format($refund->amount) }}đ · {{ $refund->payout_label }}</p>
@if(in_array(auth()->user()->role, ['ADMIN','EMPLOYEE']) && auth()->user()->hasPermission('refunds.process'))
<a class="btn btn-primary mb-3" href="{{ route('refund-payouts.show', $refund) }}">{{ $refund->refund ? 'Xem biên nhận hoàn tiền' : 'Mở khoản hoàn & chi trả' }} <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
@endif
@include('partials.refund-recipient', ['refundRequest'=>$refund])
@endforeach
@endforeach
</div><aside>@include('partials.incident-timeline', ['timeline'=>$timeline])</aside></div>
</div>
@endsection
