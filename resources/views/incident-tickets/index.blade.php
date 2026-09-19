@extends(auth()->user()->role === 'ADMIN' ? 'layouts.admin' : 'layouts.employee')
@section('page_heading','Sự cố & Khiếu nại booking')
@section('content')
@include('partials.incident-ui')
<link rel="stylesheet" href="{{ asset('css/ticket-inbox.css') }}?v={{ filemtime(public_path('css/ticket-inbox.css')) }}">
<section class="ticket-inbox" aria-labelledby="ticket-heading">
<header class="ticket-heading"><div><span class="ticket-eyebrow">TRUNG TÂM HỖ TRỢ</span><h1 id="ticket-heading">Yêu cầu từ khách hàng</h1><p>Theo dõi sự cố, mong muốn của khách và người phụ trách.</p></div><a class="btn btn-outline-danger" href="{{ route('admin.incidents.bulk') }}"><i class="bi bi-calendar-x me-2" aria-hidden="true"></i>Xử lý sự cố sân</a></header>
<div class="ticket-filter"><div><strong>{{ number_format($tickets->total()) }} yêu cầu</strong><p class="small text-muted mb-0">{{ request('status') ? (\App\Models\CourtIncident::TICKET_STATUSES[request('status')] ?? 'Theo bộ lọc') : 'Tất cả trạng thái' }}</p></div><form method="GET" action="{{ route('incident-tickets.index') }}" class="d-flex align-items-end gap-2"><div><label class="form-label small" for="ticket-status">Lọc theo trạng thái</label><select id="ticket-status" name="status" class="form-select"><option value="">Tất cả trạng thái</option>@foreach(\App\Models\CourtIncident::TICKET_STATUSES as $code=>$label)<option value="{{ $code }}" @selected(request('status') === $code)>{{ $label }}</option>@endforeach</select></div><button class="btn btn-primary" type="submit">Lọc</button></form></div>
<div class="ticket-list">
@forelse($tickets as $ticket)
@php
$tone = match($ticket->status) {'RESOLVED'=>'success','REJECTED'=>'danger','REVIEWING','APPROVED'=>'info','PENDING','NEED_MORE_INFO'=>'warning',default=>'neutral'};
$icon = match($ticket->requested_solution) {'REFUND'=>'cash-coin','RESCHEDULE'=>'calendar-event','CHANGE_COURT'=>'arrow-left-right',default=>'chat-dots'};
@endphp
<article class="ticket-row ticket-row--{{ $tone }}">
<div class="ticket-main"><div class="ticket-type-icon"><i class="bi bi-{{ $icon }}" aria-hidden="true"></i></div><div><span class="ticket-field">Yêu cầu của khách</span><a class="ticket-title" href="{{ route('incident-tickets.show',$ticket) }}">{{ \App\Models\CourtIncident::SOLUTIONS[$ticket->requested_solution] ?? 'Hỗ trợ booking' }}</a><details class="ticket-code"><summary>Mã {{ \Illuminate\Support\Str::limit($ticket->incident_code,14) }}</summary><span>{{ $ticket->incident_code }}</span></details><time datetime="{{ $ticket->created_at->toIso8601String() }}">{{ $ticket->created_at->format('d/m/Y · H:i') }}</time></div></div>
<div class="ticket-customer"><span class="ticket-field">Khách hàng / Sân</span><strong>{{ $ticket->reporter?->name ?? 'Không còn thông tin khách' }}</strong><span><i class="bi bi-geo-alt" aria-hidden="true"></i> {{ $ticket->court?->name ?? 'Không còn thông tin sân' }}</span><span class="ticket-booking">{{ $ticket->booking?->booking_code ?? 'Không còn booking' }}</span></div>
<div class="ticket-progress"><span class="ticket-field">Trạng thái</span><x-status-badge :tone="$tone" :label="\App\Models\CourtIncident::TICKET_STATUSES[$ticket->status] ?? $ticket->status"/><span class="ticket-assignee"><i class="bi bi-person-check" aria-hidden="true"></i> {{ $ticket->assignee?->name ?? 'Chưa phân công' }}</span></div>
<a class="btn btn-outline-primary ticket-open" href="{{ route('incident-tickets.show',$ticket) }}" aria-label="Xem yêu cầu {{ $ticket->incident_code }}">Xem chi tiết <i class="bi bi-arrow-right ms-1" aria-hidden="true"></i></a>
</article>
@empty
<x-empty-state icon="bi-inbox" title="Không có yêu cầu phù hợp" description="Thử thay đổi trạng thái lọc hoặc quay lại khi có yêu cầu mới."/>
@endforelse
</div>
@if($tickets->hasPages())<div class="mt-4">{{ $tickets->links() }}</div>@endif
</section>
@endsection
