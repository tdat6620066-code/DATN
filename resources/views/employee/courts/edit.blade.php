@extends('layouts.employee')

@section('title', 'Cập nhật trạng thái sân - SmashZone')
@section('page_heading', 'Quản lý sân')

@push('styles')
<style>
.court-edit-grid{display:grid;grid-template-columns:1fr 1.2fr;gap:20px}.court-panel{padding:24px}.court-panel h2{margin:0 0 20px;font-size:17px;font-weight:800}.court-summary{padding:20px;border-radius:13px;background:linear-gradient(135deg,#092f41,#086052);color:#fff}.court-summary small{color:#85e8b8;font-size:10px;font-weight:800;letter-spacing:1px}.court-summary h1{margin:7px 0 4px;font-size:24px;font-weight:800}.court-summary p{margin:0;color:#bdd2d5;font-size:12px}.state-option{position:relative}.state-option input{position:absolute;opacity:0}.state-option label{display:flex;align-items:center;gap:12px;margin-bottom:10px;padding:14px;border:1px solid #dfe8e4;border-radius:12px;cursor:pointer}.state-option input:checked+label{border-color:#08b96b;background:#edfbf4;box-shadow:0 0 0 3px rgba(8,185,107,.1)}.state-option i{display:grid;place-items:center;width:38px;height:38px;border-radius:10px;background:#e5f6ed;color:#049957;font-size:18px}.state-option strong,.state-option small{display:block}.state-option small{margin-top:2px;color:#75878e;font-size:11px}.affected-bookings{margin-top:20px}.affected-item{display:flex;justify-content:space-between;padding:11px 0;border-bottom:1px solid #edf2ef;font-size:12px}.warning-box{padding:13px;border-radius:10px;background:#fff3dd;color:#985900;font-size:12px}.status-form label.form-label{font-size:11px;font-weight:800}.status-form textarea{border-color:#dce6e1;border-radius:10px;font-size:13px}@media(max-width:800px){.court-edit-grid{grid-template-columns:1fr}}
</style>
@endpush

@section('content')
<div class="staff-page-title"><a class="text-decoration-none text-success small fw-bold" href="{{ route('employee.courts.index') }}">← Danh sách sân</a><h1 class="mt-2">Cập nhật trạng thái sân</h1><p>Trạng thái mới sẽ được áp dụng ngay vào lịch khả dụng.</p></div>
<div class="court-edit-grid">
    <section class="staff-card court-panel"><div class="court-summary"><small>{{ $court->code }}</small><h1>{{ $court->name }}</h1><p>{{ $court->courtType->name }} · {{ $court->address ?: 'Chưa cập nhật địa chỉ' }}</p></div>
        <div class="affected-bookings"><h2>Booking có thể bị ảnh hưởng</h2>
        @forelse($court->bookingDetails as $detail)<div class="affected-item"><span><strong>{{ $detail->booking->booking_code }}</strong><br>{{ $detail->booking->user->name }}</span><span class="text-end">{{ $detail->booking_date->format('d/m/Y') }}<br>{{ $detail->timeSlot->name }}</span></div>
        @empty<div class="text-muted small">Không có booking sắp tới cần xử lý.</div>@endforelse</div>
    </section>
    <section class="staff-card court-panel"><h2>Chọn trạng thái vận hành</h2>
        @if($court->bookingDetails->isNotEmpty())<div class="warning-box mb-3"><i class="bi bi-exclamation-triangle me-2"></i>Cần xử lý các booking bên trái trước khi khóa hoặc bảo trì sân.</div>@endif
        <form class="status-form" method="POST" action="{{ route('employee.courts.update', $court) }}">@csrf @method('PUT')
            @foreach([['AVAILABLE','bi-check-circle','Sẵn sàng','Khách hàng có thể đặt sân'],['LOCKED','bi-lock','Khóa sân','Tạm ngừng nhận booking'],['MAINTENANCE','bi-tools','Bảo trì','Dừng nhận booking để bảo trì']] as [$value,$icon,$title,$description])
            <div class="state-option"><input id="state-{{ $value }}" type="radio" name="operational_status" value="{{ $value }}" {{ old('operational_status', $court->operational_status) === $value ? 'checked' : '' }}><label for="state-{{ $value }}"><i class="bi {{ $icon }}"></i><span><strong>{{ $title }}</strong><small>{{ $description }}</small></span></label></div>
            @endforeach
            <label class="form-label mt-2" for="status_reason">Lý do khóa / bảo trì</label><textarea class="form-control mb-3" id="status_reason" name="status_reason" rows="4" placeholder="Nhập lý do cụ thể...">{{ old('status_reason', $court->status_reason) }}</textarea>
            <button class="staff-button staff-button-primary"><i class="bi bi-floppy"></i>Lưu trạng thái</button>
        </form>
    </section>
</div>
@endsection
