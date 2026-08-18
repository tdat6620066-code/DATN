@extends('layouts.app')

@section('title', 'Đặt sân định kỳ - SmashZone')

@section('content')
<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-repeat"></i> Đặt sân định kỳ</h5>
            </div>
            <div class="card-body">
                <form action="/booking/recurring" method="POST" id="recurringForm">
                    @csrf

                    <!-- Step 1: Select Court -->
                    <div class="mb-4">
                        <h6 class="mb-3"><i class="bi bi-1-circle"></i> Chọn sân cầu lông</h6>
                        <div class="mb-3">
                            <label for="court_id" class="form-label">Sân cầu lông</label>
                            <select class="form-select @error('court_id') is-invalid @enderror" id="court_id" name="court_id" required>
                                <option value="">-- Chọn sân --</option>
                                @foreach($courts ?? [] as $c)
                                <option value="{{ $c->id }}" {{ old('court_id') == $c->id ? 'selected' : '' }}>
                                    {{ $c->name }} - {{ $c->courtType->name }}
                                </option>
                                @endforeach
                            </select>
                            @error('court_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <hr>

                    <!-- Step 2: Select Time Slot -->
                    <div class="mb-4">
                        <h6 class="mb-3"><i class="bi bi-2-circle"></i> Chọn khung giờ</h6>
                        <div class="mb-3">
                            <label for="time_slot_id" class="form-label">Khung giờ</label>
                            <select class="form-select @error('time_slot_id') is-invalid @enderror" id="time_slot_id" name="time_slot_id" required>
                                <option value="">-- Chọn khung giờ --</option>
                                @foreach($timeSlots ?? [] as $slot)
                                <option value="{{ $slot->id }}" {{ old('time_slot_id') == $slot->id ? 'selected' : '' }}>
                                    {{ $slot->name }}
                                </option>
                                @endforeach
                            </select>
                            @error('time_slot_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <hr>

                    <!-- Step 3: Select Date Range -->
                    <div class="mb-4">
                        <h6 class="mb-3"><i class="bi bi-3-circle"></i> Chọn khoảng thời gian</h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="start_date" class="form-label">Từ ngày</label>
                                <input type="date" class="form-control @error('start_date') is-invalid @enderror" id="start_date" name="start_date" required>
                                @error('start_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="end_date" class="form-label">Đến ngày</label>
                                <input type="date" class="form-control @error('end_date') is-invalid @enderror" id="end_date" name="end_date" required>
                                @error('end_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <hr>

                    <!-- Step 4: Select Days of Week -->
                    <div class="mb-4">
                        <h6 class="mb-3"><i class="bi bi-4-circle"></i> Chọn ngày trong tuần</h6>
                        <p class="text-muted small mb-3">Chọn những ngày bạn muốn đặt sân định kỳ</p>
                        <div class="row">
                            @php
                                $daysOfWeek = [
                                    ['value' => 1, 'name' => 'Thứ hai'],
                                    ['value' => 2, 'name' => 'Thứ ba'],
                                    ['value' => 3, 'name' => 'Thứ tư'],
                                    ['value' => 4, 'name' => 'Thứ năm'],
                                    ['value' => 5, 'name' => 'Thứ sáu'],
                                    ['value' => 6, 'name' => 'Thứ bảy'],
                                    ['value' => 0, 'name' => 'Chủ nhật'],
                                ];
                            @endphp
                            @foreach($daysOfWeek as $day)
                            <div class="col-md-4 mb-2">
                                <div class="form-check">
                                    <input class="form-check-input days-checkbox" type="checkbox" id="day{{ $day['value'] }}" 
                                           name="days_of_week[]" value="{{ $day['value'] }}"
                                           {{ in_array($day['value'], old('days_of_week', [])) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="day{{ $day['value'] }}">
                                        {{ $day['name'] }}
                                    </label>
                                </div>
                            </div>
                            @endforeach
                        </div>
                        @error('days_of_week')
                        <div class="alert alert-danger mt-2">{{ $message }}</div>
                        @enderror
                    </div>

                    <hr>

                    <!-- Step 5: Voucher -->
                    <div class="mb-4">
                        <h6 class="mb-3"><i class="bi bi-5-circle"></i> Mã khuyến mãi (Tùy chọn)</h6>
                        <div class="mb-3">
                            <label for="voucher_code" class="form-label">Nhập mã voucher</label>
                            <input type="text" class="form-control @error('voucher_code') is-invalid @enderror" 
                                   id="voucher_code" name="voucher_code" placeholder="Ví dụ: GROUP25">
                            @error('voucher_code')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">Nhập mã khuyến mãi nếu bạn có</small>
                        </div>
                    </div>

                    <hr>

                    <!-- Submit Button -->
                    <div class="mb-3">
                        <button type="submit" class="btn btn-primary btn-lg w-100">
                            <i class="bi bi-check-circle"></i> Tiếp tục thanh toán
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Summary Sidebar -->
    <div class="col-lg-4">
        <div class="card sticky-top" style="top: 20px;">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-receipt"></i> Tóm tắt</h5>
            </div>
            <div class="card-body">
                <div class="booking-summary">
                    <div id="summary">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> Chọn sân, khung giờ, ngày để xem tóm tắt
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
const courtSelect = document.getElementById('court_id');
const startDateInput = document.getElementById('start_date');
const endDateInput = document.getElementById('end_date');
const timeSlotSelect = document.getElementById('time_slot_id');
const summaryDiv = document.getElementById('summary');

function updateSummary() {
    const courtId = courtSelect.value;
    const timeSlotId = timeSlotSelect.value;
    const startDate = startDateInput.value;
    const endDate = endDateInput.value;

    const checkedDays = Array.from(document.querySelectorAll('.days-checkbox:checked')).map(cb => cb.value);

    if (!courtId || !timeSlotId || !startDate || !endDate || checkedDays.length === 0) {
        summaryDiv.innerHTML = '<div class="alert alert-info"><i class="bi bi-info-circle"></i> Chọn thông tin để xem tóm tắt</div>';
        return;
    }

    const dayNames = ['Chủ nhật', 'Thứ hai', 'Thứ ba', 'Thứ tư', 'Thứ năm', 'Thứ sáu', 'Thứ bảy'];
    const selectedCourt = courtSelect.options[courtSelect.selectedIndex].text;
    const selectedTimeSlot = timeSlotSelect.options[timeSlotSelect.selectedIndex].text;

    let html = '';
    html += `<div class="summary-item"><strong>Sân:</strong> <span>${selectedCourt}</span></div>`;
    html += `<div class="summary-item"><strong>Khung giờ:</strong> <span>${selectedTimeSlot}</span></div>`;

    const startDateObj = new Date(startDate);
    const endDateObj = new Date(endDate);
    const startStr = startDateObj.toLocaleDateString('vi-VN');
    const endStr = endDateObj.toLocaleDateString('vi-VN');
    html += `<div class="summary-item"><strong>Khoảng thời gian:</strong> <span>${startStr} - ${endStr}</span></div>`;

    const daysList = checkedDays.map(d => dayNames[d]).join(', ');
    html += `<div class="summary-item"><strong>Các ngày:</strong> <span>${daysList}</span></div>`;

    // Calculate number of bookings
    let count = 0;
    const currentDate = new Date(startDate);
    while (currentDate <= endDateObj) {
        const dayOfWeek = currentDate.getDay();
        if (checkedDays.includes(dayOfWeek.toString())) {
            count++;
        }
        currentDate.setDate(currentDate.getDate() + 1);
    }

    html += `<div class="summary-item"><strong>Số lần đặt:</strong> <span>${count} lần</span></div>`;
    html += `<div class="alert alert-info mt-2"><small><i class="bi bi-info-circle"></i> Mỗi lần đặt sẽ được tính riêng với giá hiện tại</small></div>`;

    summaryDiv.innerHTML = html;
}

courtSelect.addEventListener('change', updateSummary);
timeSlotSelect.addEventListener('change', updateSummary);
startDateInput.addEventListener('change', updateSummary);
endDateInput.addEventListener('change', updateSummary);
document.querySelectorAll('.days-checkbox').forEach(cb => cb.addEventListener('change', updateSummary));

// Set minimum dates to today
const today = new Date().toISOString().split('T')[0];
startDateInput.min = today;
endDateInput.min = today;
</script>
@endpush
@endsection
