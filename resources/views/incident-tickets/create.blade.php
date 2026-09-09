@extends('layouts.app')
@section('content')
@include('partials.incident-ui')
<div class="container py-4 sz-support-page"><header class="sz-support-header">
<a class="small text-decoration-none" href="{{ route('bookings.show',$booking) }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> Trở về đơn {{ $booking->booking_code }}</a>
<h1>Báo cáo sự cố</h1><p>Cho SmashZone biết vấn đề bạn gặp phải để được hỗ trợ.</p></header>
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
@foreach($tickets as $ticket)<p><a href="{{ route('incident-tickets.show',$ticket) }}">{{ $ticket->incident_code }} · {{ \App\Models\CourtIncident::TICKET_STATUSES[$ticket->status] ?? $ticket->status }}</a></p>@endforeach
@if($booking->payment?->status !== 'PAID')
<p>Chức năng báo cáo sự cố dành cho booking đã thanh toán. Bạn vẫn có thể mở các yêu cầu trước đây ở trên để theo dõi.</p>
@elseif(!$tickets->contains(fn ($t) => $t->active_booking_id !== null))
<form method="POST" enctype="multipart/form-data" action="{{ route('incident-tickets.store',$booking) }}" class="sz-work-panel">@csrf
<label>Lượt sân gặp sự cố</label><select name="booking_detail_id" class="form-select mb-3" required>@foreach($booking->bookingDetails as $detail)<option value="{{ $detail->id }}" @selected(old('booking_detail_id') == $detail->id)>{{ $detail->court->name }} · {{ $detail->booking_date->format('d/m/Y') }} · {{ $detail->timeSlot->start_time }}–{{ $detail->timeSlot->end_time }}</option>@endforeach</select>
<label>Loại sự cố</label><select name="type" class="form-select mb-3" required>@foreach(\App\Models\CourtIncident::TYPES as $code=>$label)<option value="{{ $code }}" @selected(old('type') === $code)>{{ $label }}</option>@endforeach</select>
<label>Mô tả sự cố</label><textarea name="description" class="form-control mb-3" rows="4" minlength="10" maxlength="4000" required>{{ old('description') }}</textarea>
@include('partials.media-upload', ['label' => 'Ảnh/video minh chứng (tùy chọn)'])
<label>Mong muốn xử lý</label><select name="requested_solution" class="form-select mb-3" required>@foreach(\App\Models\CourtIncident::SOLUTIONS as $code=>$label)<option value="{{ $code }}" @selected(old('requested_solution') === $code)>{{ $label }}</option>@endforeach</select>
<p class="sz-help">Nhân viên sẽ xác minh và cập nhật kết quả trong yêu cầu của bạn.</p><div class="sz-action-bar"><button class="sz-action sz-action--primary"><i class="bi bi-send" aria-hidden="true"></i>Gửi báo cáo</button><a class="sz-action" href="{{ route('bookings.show', $booking) }}">Quay lại</a></div></form>
@else<p>Booking đã có yêu cầu đang mở. Mở yêu cầu ở trên để bổ sung hoặc theo dõi.</p>@endif
</div>
@endsection
