@extends(auth()->user()->role === 'ADMIN' ? 'layouts.admin' : 'layouts.employee')
@section('page_heading', 'Sự cố sân — xử lý hàng loạt')
@section('content')
@include('partials.incident-ui')
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<div class="sz-work-panel">
<h2 class="h5">Khoảng thời gian sân không thể phục vụ</h2>
<form method="POST" action="{{ route('admin.incidents.bulk.store') }}">
@csrf
<label>Sân</label><select name="court_id" class="form-select mb-2" required>@foreach($courts as $court)<option value="{{ $court->id }}" @selected(old('court_id', $data['court_id'] ?? null) == $court->id)>{{ $court->name }}</option>@endforeach</select>
<label>Ngày</label><input type="date" name="date" value="{{ old('date', $data['date'] ?? now()->toDateString()) }}" class="form-control mb-2" required>
<label>Từ</label><input type="time" name="start_time" value="{{ old('start_time', $data['start_time'] ?? '17:00') }}" class="form-control mb-2" required>
<label>Đến</label><input type="time" name="end_time" value="{{ old('end_time', $data['end_time'] ?? '22:00') }}" class="form-control mb-2" required>
<label>Lý do</label><select name="reason_code" class="form-select mb-2">@foreach(\App\Models\RefundRequest::REASONS as $code => $label)<option value="{{ $code }}" @selected(old('reason_code', $data['reason_code'] ?? 'WEATHER') === $code)>{{ $label }}</option>@endforeach</select>
<label>Ghi chú sự cố</label><textarea name="reason" class="form-control mb-2" required maxlength="2000">{{ old('reason', $data['reason'] ?? '') }}</textarea>
<p>Hủy các lượt sân bị ảnh hưởng. Khách đã thanh toán được chọn hoàn phần chưa sử dụng, đổi lịch hoặc đổi sân; chưa tạo refund tự động.</p>
<label class="d-block"><input type="checkbox" name="notify_customers" value="1" @checked(old('notify_customers', $data['notify_customers'] ?? 1))> Gửi thông báo khách hàng</label>
<button class="sz-action sz-action--primary mt-3">Xem trước booking bị ảnh hưởng</button>
</form>
</div>
@isset($bookings)
<div class="card p-4 mt-3"><h2 class="h5">{{ $bookings->count() }} booking bị ảnh hưởng</h2>
<p>Chỉ các lượt trùng sân, ngày và khoảng giờ sự cố bị hủy. Những lượt khác trong booking tiếp tục được giữ. Khách đã thanh toán chọn phương án xử lý sau khi nhận thông báo.</p>
<table class="table"><thead><tr><th>Booking / khách</th><th>Tất cả lịch trong đơn</th><th>Thanh toán / xử lý</th></tr></thead><tbody>
@foreach($bookings as $booking)<tr><td><a href="{{ route(auth()->user()->role === 'ADMIN' ? 'admin.bookings.show' : 'employee.bookings.show', $booking) }}">{{ $booking->booking_code }}</a><br>{{ $booking->user->name }}</td><td>@foreach($booking->bookingDetails as $detail)<div>{{ $detail->court->name }} · {{ $detail->booking_date->format('d/m/Y') }} · {{ $detail->timeSlot->start_time }}–{{ $detail->timeSlot->end_time }}</div>@endforeach</td><td>{{ $booking->payment_status }}<br>{{ $booking->payment?->status === 'PAID' ? 'Duyệt hoàn '.number_format($booking->total_amount).'đ' : 'Hủy booking chưa thanh toán' }}</td></tr>@endforeach
</tbody></table>
<form method="POST" action="{{ route('admin.incidents.bulk.store') }}">@csrf
@foreach($data as $key => $value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
<input type="hidden" name="confirm" value="1"><input type="hidden" name="preview_token" value="{{ $token }}">
<button class="sz-action sz-action--danger">Xác nhận sự cố và xử lý {{ $bookings->count() }} booking</button>
</form></div>
@endisset
@endsection
