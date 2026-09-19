@extends('layouts.employee')
@section('page_heading', 'Đặt sân tại quầy')
@section('content')
<div class="staff-page-title"><h1>Đặt sân tại quầy</h1><p>Chọn lịch để hệ thống kiểm tra sân và tính giá. Bước tiếp theo xác nhận thanh toán.</p></div>
<form method="POST" action="{{ route('employee.counter.store') }}" class="staff-card p-4">@csrf
<div class="row g-3">
    <div class="col-md-4"><label class="form-label" for="phone">Số điện thoại khách</label><input id="phone" name="phone" class="form-control" value="{{ old('phone') }}" maxlength="20" required></div>
    <div class="col-md-4"><label class="form-label" for="name">Tên khách</label><input id="name" name="name" class="form-control" value="{{ old('name') }}" required></div>
    <div class="col-md-4"><label class="form-label" for="email">Email (bắt buộc với khách mới)</label><input id="email" type="email" name="email" class="form-control" value="{{ old('email') }}"></div>
    <div class="col-md-6"><label class="form-label" for="court">Sân</label><select id="court" name="court_id" class="form-select" required>@foreach($courts as $court)<option value="{{ $court->id }}" @selected(old('court_id', request('court_id')) == $court->id)>{{ $court->name }}</option>@endforeach</select></div>
    <div class="col-md-6"><label class="form-label" for="date">Ngày chơi</label><input id="date" type="date" name="booking_date" class="form-control" min="{{ today()->toDateString() }}" value="{{ old('booking_date', request('booking_date', today()->toDateString())) }}" required></div>
    <fieldset class="col-12"><legend class="fs-6">Khung giờ liền nhau</legend><div class="d-flex flex-wrap gap-3">@foreach($slots as $slot)<label><input type="checkbox" name="time_slot_ids[]" value="{{ $slot->id }}" @checked(in_array($slot->id, old('time_slot_ids', request('time_slot_id') ? [(int) request('time_slot_id')] : [])))> {{ $slot->name }}</label>@endforeach</div></fieldset>
</div><button class="staff-button staff-button-primary mt-4">Kiểm tra và giữ sân</button>
</form>
@endsection
