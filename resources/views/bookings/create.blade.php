@extends('layouts.app')

@section('title', 'Đặt sân - SmashZone')

@section('content')
<style>
    .calendar-day-picker {
        display: flex;
        gap: 8px;
        overflow-x: auto;
        padding: 10px 0;
        margin-bottom: 20px;
    }
    
    .calendar-day {
        flex-shrink: 0;
        width: 70px;
        height: 80px;
        padding: 8px;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s;
        background: white;
    }
    
    .calendar-day:hover {
        border-color: #0d6efd;
        background: #f0f7ff;
    }
    
    .calendar-day.selected {
        background: #00d084;
        color: white;
        border-color: #00d084;
    }
    
    .calendar-day-number {
        font-size: 20px;
        font-weight: bold;
        margin-bottom: 4px;
    }
    
    .calendar-day-dow {
        font-size: 11px;
        color: #666;
    }
    
    .calendar-day.selected .calendar-day-dow {
        color: white;
    }
    
    .availability-table {
        border-collapse: collapse;
        width: 100%;
        font-size: 13px;
    }
    
    .availability-table thead {
        background: #f8f9fa;
        position: sticky;
        top: 0;
        z-index: 10;
    }
    
    .availability-table th {
        padding: 12px 8px;
        text-align: center;
        border-bottom: 2px solid #dee2e6;
        font-weight: 600;
        font-size: 12px;
    }
    
    .availability-table td {
        padding: 12px 8px;
        border: 1px solid #dee2e6;
        text-align: center;
        position: relative;
    }
    
    .court-name {
        font-weight: 600;
        text-align: left;
        background: #f8f9fa;
        min-width: 60px;
    }
    
    .time-slot-cell {
        cursor: pointer;
        position: relative;
        transition: all 0.2s;
        user-select: none;
        font-weight: 500;
    }
    
    .time-slot-cell:hover {
        background: #eff6ff;
    }
    
    .slot-available {
        background: #f0fdf4;
        color: #16a34a;
    }
    
    .slot-available:hover {
        background: #dcfce7;
    }
    
    .slot-booked {
        background: #fef2f2;
        color: #dc2626;
        text-decoration: line-through;
        cursor: not-allowed;
    }
    
    .slot-hold {
        background: #fef3c7;
        color: #d97706;
    }
    
    .slot-maintenance {
        background: #f3f4f6;
        color: #6b7280;
        cursor: not-allowed;
    }
    
    .slot-selected {
        background: #00d084 !important;
        color: white !important;
        border: 2px solid #00b871;
    }
    
    .legend {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
        gap: 15px;
        margin-top: 20px;
        padding: 15px;
        background: #f8f9fa;
        border-radius: 8px;
    }
    
    .legend-item {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 13px;
    }
    
    .legend-color {
        width: 20px;
        height: 20px;
        border-radius: 4px;
    }
    
    .booking-summary {
        position: sticky;
        top: 100px;
        background: white;
        padding: 20px;
        border-radius: 8px;
        border: 1px solid #e0e0e0;
    }
    
    .booking-summary h6 {
        font-weight: 600;
        margin-bottom: 15px;
        font-size: 14px;
    }
    
    .summary-item {
        display: flex;
        justify-content: space-between;
        padding: 10px 0;
        border-bottom: 1px solid #f0f0f0;
        font-size: 13px;
    }
    
    .summary-item:last-child {
        border-bottom: none;
    }
    
    .total-price {
        font-size: 18px;
        font-weight: bold;
        color: #00d084;
        padding-top: 15px;
        border-top: 2px solid #e0e0e0;
    }
</style>

<div class="container-fluid">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex align-items-center gap-3 mb-3">
                <a href="/courts/1" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-chevron-left"></i>
                </a>
                <h3 class="mb-0">Sân Cầu 1991 Club</h3>
                <button type="button" class="btn btn-sm btn-outline-secondary ms-auto">
                    <i class="bi bi-info-circle"></i>
                </button>
            </div>

            <!-- Date Display & Calendar Toggle -->
            <div class="d-flex align-items-center gap-2 mb-3">
                <span style="color: #00d084; font-size: 16px; font-weight: bold;">
                    <i class="bi bi-calendar-event"></i> 
                    <span id="displayDate">{{ $bookingDate->formatLocalized('%A, %d %B %Y') }}</span>
                </span>
                <button type="button" class="btn btn-sm btn-outline-success ms-auto" id="changeDateBtn">
                    <i class="bi bi-calendar-plus"></i> Chọn ngày
                </button>
            </div>

            <!-- Calendar Day Picker -->
            <div class="calendar-day-picker" id="datePickerContainer">
                @foreach($dateRange as $date)
                <div class="calendar-day {{ $date->toDateString() === $bookingDate->toDateString() ? 'selected' : '' }}" 
                     data-date="{{ $date->toDateString() }}" onclick="selectDate(this)">
                    <div class="calendar-day-number">{{ $date->day }}</div>
                    <div class="calendar-day-dow">{{ $date->format('D') }}</div>
                    <div style="font-size: 9px; color: #999;">T{{ $date->month }}</div>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Main Content -->
        <div class="col-lg-9">
            <!-- Info Banner -->
            <div style="background: #f0f9ff; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #0084ff;">
                <h6 style="margin-bottom: 8px; color: #0084ff; font-size: 14px;">Lịch sân cầu lông</h6>
                <p style="margin: 0; font-size: 13px; color: #666;">
                    Khách vui lòng lưng đặt 2 tiếng, nếu đặt lệ giờ vui lòng nhận theo hotline 0982.949.974
                </p>
            </div>

            <!-- Availability Table -->
            <form method="POST" action="/booking" id="bookingForm">
                @csrf
                <input type="hidden" name="booking_date" id="bookingDateInput" value="{{ $bookingDate->toDateString() }}">
                <input type="hidden" name="court_id" id="courtIdInput" value="1">
                <input type="hidden" name="time_slot_ids" id="timeSlotIdsInput" value="">

                <div class="table-responsive" style="max-height: 600px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 8px;">
                    <table class="availability-table">
                        <thead>
                            <tr>
                                <th style="position: sticky; left: 0; background: #f8f9fa; z-index: 11; min-width: 60px;">Sân</th>
                                @foreach($timeSlots as $slot)
                                <th>{{ \Carbon\Carbon::createFromFormat('H:i:s', $slot->start_time)->format('H:i') }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($courts as $court)
                            <tr>
                                <td class="court-name" style="position: sticky; left: 0; z-index: 10;">Sân {{ $loop->iteration }}</td>
                                @foreach($timeSlots as $slot)
                                @php
                                    $availability = $availabilityData[$court->id][$slot->id] ?? [];
                                    $status = $availability['status'] ?? 'AVAILABLE';
                                    $price = $availability['price'] ?? 0;
                                    $priceDisplay = $price > 0 ? (int)($price / 1000) . 'k' : '-';
                                    
                                    $statusClass = match($status) {
                                        'BOOKED' => 'slot-booked',
                                        'HOLD' => 'slot-hold',
                                        'MAINTENANCE' => 'slot-maintenance',
                                        default => 'slot-available'
                                    };
                                    
                                    $isSelectable = in_array($status, ['AVAILABLE']);
                                @endphp
                                <td class="time-slot-cell {{ $statusClass }}" 
                                    data-court-id="{{ $court->id }}"
                                    data-slot-id="{{ $slot->id }}"
                                    data-status="{{ $status }}"
                                    data-price="{{ $price }}"
                                    @if($isSelectable) onclick="toggleSlot(this)" @endif>
                                    <div>{{ $priceDisplay }}</div>
                                </td>
                                @endforeach
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Legend -->
                <div class="legend">
                    <div class="legend-item">
                        <div class="legend-color" style="background: #fef3c7;"></div>
                        <span>Đã chọn</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background: #f0fdf4;"></div>
                        <span>Trống</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background: #fef2f2;"></div>
                        <span>Đã đặt</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background: #f3f4f6;"></div>
                        <span>Khóa</span>
                    </div>
                </div>
            </form>
        </div>

        <!-- Sidebar - Booking Summary -->
        <div class="col-lg-3">
            <div class="booking-summary">
                <h6><i class="bi bi-calendar-check"></i> Chi tiết đặt sân</h6>
                
                <div class="summary-item">
                    <span>Ngày:</span>
                    <strong id="summaryDate">{{ $bookingDate->format('d/m/Y') }}</strong>
                </div>
                
                <div class="summary-item">
                    <span>Số lượng slot:</span>
                    <strong id="summarySlotCount">0</strong>
                </div>
                
                <div class="summary-item">
                    <span>Thời lượng:</span>
                    <strong id="summaryDuration">0h</strong>
                </div>
                
                <div class="total-price">
                    Tổng: <span id="totalPrice">0đ</span>
                </div>

                <button type="submit" form="bookingForm" class="btn btn-success w-100 mt-3" id="checkoutBtn" disabled>
                    <i class="bi bi-check-circle"></i> Tiếp tục thanh toán
                </button>

                <hr>

                <div class="mb-3">
                    <label for="voucherCode" class="form-label" style="font-size: 13px;">Mã khuyến mãi</label>
                    <div class="input-group input-group-sm">
                        <input type="text" class="form-control" id="voucherCode" name="voucher_code" 
                               placeholder="Nhập mã voucher" style="font-size: 12px;">
                        <button class="btn btn-outline-secondary" type="button">Áp dụng</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let selectedSlots = [];
const durationPerSlot = 0.5; // 30 minutes per slot

function selectDate(element) {
    // Update calendar UI
    document.querySelectorAll('.calendar-day').forEach(d => d.classList.remove('selected'));
    element.classList.add('selected');
    
    const selectedDate = element.dataset.date;
    document.getElementById('bookingDateInput').value = selectedDate;
    
    // Format and update display
    const dateObj = new Date(selectedDate);
    const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    const dateStr = dateObj.toLocaleDateString('vi-VN', options);
    document.getElementById('displayDate').textContent = dateStr;
    document.getElementById('summaryDate').textContent = dateObj.toLocaleDateString('vi-VN');
    
    // Reload page with new date
    window.location.href = `/booking?booking_date=${selectedDate}`;
}

function toggleSlot(element) {
    const courtId = element.dataset.courtId;
    const slotId = element.dataset.slotId;
    const status = element.dataset.status;
    const price = parseFloat(element.dataset.price);
    const key = `${courtId}-${slotId}`;
    
    // Can't select booked or maintenance slots
    if (status === 'BOOKED' || status === 'MAINTENANCE') {
        return;
    }
    
    // Toggle selection
    const index = selectedSlots.findIndex(s => s.key === key);
    
    if (index >= 0) {
        selectedSlots.splice(index, 1);
        element.classList.remove('slot-selected');
    } else {
        selectedSlots.push({ key, courtId, slotId, price });
        element.classList.add('slot-selected');
    }
    
    updateSummary();
}

function updateSummary() {
    // Update time slot IDs
    const slotIds = selectedSlots.map(s => s.slotId).join(',');
    document.getElementById('timeSlotIdsInput').value = slotIds;
    
    // Update summary
    document.getElementById('summarySlotCount').textContent = selectedSlots.length;
    document.getElementById('summaryDuration').textContent = (selectedSlots.length * durationPerSlot).toFixed(1) + 'h';
    
    const total = selectedSlots.reduce((sum, s) => sum + s.price, 0);
    document.getElementById('totalPrice').textContent = total.toLocaleString('vi-VN') + 'đ';
    
    // Enable checkout button if slots selected
    document.getElementById('checkoutBtn').disabled = selectedSlots.length === 0;
}
</script>
@endsection
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-calendar-check"></i> Tạo đặt sân</h5>
            </div>
            <div class="card-body">
                <form action="/booking" method="POST" id="bookingForm">
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

                    <!-- Step 2: Select Date -->
                    <div class="mb-4">
                        <h6 class="mb-3"><i class="bi bi-2-circle"></i> Chọn ngày</h6>
                        <div class="mb-3">
                            <label for="booking_date" class="form-label">Ngày đặt sân</label>
                            <input type="date" class="form-control @error('booking_date') is-invalid @enderror" id="booking_date" name="booking_date" required>
                            @error('booking_date')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">Bạn có thể đặt sân trong vòng 30 ngày tới</small>
                        </div>
                    </div>

                    <hr>

                    <!-- Step 3: Select Time Slots -->
                    <div class="mb-4">
                        <h6 class="mb-3"><i class="bi bi-3-circle"></i> Chọn khung giờ</h6>

                        <div id="timeSlotContainer" class="mb-3" style="display: none;">
                            <div class="alert alert-info">Đang tải khung giờ...</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Khung giờ có sẵn</label>
                            <div class="row" id="timeSlots">
                                <div class="col-12">
                                    <p class="text-muted">Vui lòng chọn sân và ngày trước</p>
                                </div>
                            </div>
                            @error('time_slot_ids')
                            <div class="alert alert-danger mt-2">{{ $message }}</div>
                            @enderror
                        </div>

                        <input type="hidden" id="selectedTimeSlots" name="time_slot_ids" value="">
                    </div>

                    <hr>

                    <!-- Step 4: Voucher -->
                    <div class="mb-4">
                        <h6 class="mb-3"><i class="bi bi-4-circle"></i> Mã khuyến mãi (Tùy chọn)</h6>
                        <div class="mb-3">
                            <label for="voucher_code" class="form-label">Nhập mã voucher</label>
                            <div class="input-group">
                                <input type="text" class="form-control @error('voucher_code') is-invalid @enderror" id="voucher_code" name="voucher_code" placeholder="Ví dụ: WELCOME20">
                                @error('voucher_code')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <small class="text-muted">Nhập mã khuyến mãi nếu bạn có</small>
                        </div>
                    </div>

                    <hr>

                    <!-- Submit Button -->
                    <div class="mb-3">
                        <button type="submit" class="btn btn-primary btn-lg w-100" id="submitBtn" disabled>
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
                <h5 class="mb-0"><i class="bi bi-receipt"></i> Tóm tắt đơn hàng</h5>
            </div>
            <div class="card-body">
                <div class="booking-summary">
                    <div id="summary">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> Chọn sân, ngày và khung giờ để xem tóm tắt
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
const bookingDateInput = document.getElementById('booking_date');
const timeSlotContainer = document.getElementById('timeSlots');
const selectedTimeSlotsInput = document.getElementById('selectedTimeSlots');
const summaryDiv = document.getElementById('summary');
const submitBtn = document.getElementById('submitBtn');

const selectedSlots = new Set();

async function loadTimeSlots() {
    const courtId = courtSelect.value;
    const date = bookingDateInput.value;

    if (!courtId || !date) {
        timeSlotContainer.innerHTML = '<p class="text-muted">Vui lòng chọn sân và ngày</p>';
        updateSummary();
        return;
    }

    try {
        const response = await fetch(`/courts/${courtId}/availability?booking_date=${date}`);
        const data = await response.json();

        let html = '';
        data.time_slots.forEach(slot => {
            const isAvailable = slot.status === 'AVAILABLE';
            const isSelected = selectedSlots.has(slot.time_slot_id);
            
            html += `
                <div class="col-md-6 col-lg-12 mb-2">
                    <button type="button" 
                        class="btn btn-sm w-100 time-slot-btn ${isAvailable ? 'available' : ''} ${isSelected ? 'selected' : ''}"
                        data-slot-id="${slot.time_slot_id}"
                        data-slot-name="${slot.name}"
                        data-slot-price="${slot.price || 0}"
                        ${!isAvailable ? 'disabled' : ''}
                        onclick="toggleTimeSlot(${slot.time_slot_id}, '${slot.name}', ${slot.price || 0}, event)">
                        ${slot.name}
                        <br>
                        <small>${slot.status === 'AVAILABLE' ? 'Trống' : slot.status === 'BOOKED' ? 'Đã đặt' : slot.status === 'HOLD' ? 'Tạm giữ' : 'Bảo trì'}</small>
                    </button>
                </div>
            `;
        });

        timeSlotContainer.innerHTML = html || '<p class="text-muted">Không có khung giờ nào</p>';
        updateSummary();
    } catch (error) {
        timeSlotContainer.innerHTML = '<div class="alert alert-danger">Lỗi khi tải khung giờ</div>';
        console.error(error);
    }
}

function toggleTimeSlot(slotId, slotName, price, event) {
    event.preventDefault();
    
    if (selectedSlots.has(slotId)) {
        selectedSlots.delete(slotId);
    } else {
        selectedSlots.add(slotId);
    }

    // Update button styles
    document.querySelectorAll('.time-slot-btn').forEach(btn => {
        if (btn.dataset.slotId == slotId) {
            btn.classList.toggle('selected');
        }
    });

    // Update hidden input
    selectedTimeSlotsInput.value = Array.from(selectedSlots).join(',');
    updateSummary();
}

function updateSummary() {
    const courtId = courtSelect.value;
    const date = bookingDateInput.value;
    const voucherCode = document.getElementById('voucher_code').value;

    if (!courtId || !date) {
        summaryDiv.innerHTML = '<div class="alert alert-info"><i class="bi bi-info-circle"></i> Chọn sân, ngày và khung giờ</div>';
        submitBtn.disabled = true;
        return;
    }

    if (selectedSlots.size === 0) {
        submitBtn.disabled = true;
    } else {
        submitBtn.disabled = false;
    }

    let html = '';
    let totalPrice = 0;

    // Court info
    const selectedCourt = courtSelect.options[courtSelect.selectedIndex].text;
    html += `<div class="summary-item"><strong>Sân:</strong> <span>${selectedCourt}</span></div>`;

    // Date
    const dateObj = new Date(date);
    const dateStr = dateObj.toLocaleDateString('vi-VN');
    html += `<div class="summary-item"><strong>Ngày:</strong> <span>${dateStr}</span></div>`;

    // Time slots
    if (selectedSlots.size > 0) {
        document.querySelectorAll('.time-slot-btn.selected').forEach(btn => {
            const price = parseFloat(btn.dataset.slotPrice);
            totalPrice += price;
            html += `<div class="summary-item"><strong>${btn.dataset.slotName}:</strong> <span>${price.toLocaleString('vi-VN')} VNĐ</span></div>`;
        });
    }

    // Subtotal
    html += `<div class="summary-item"><strong>Tạm tính:</strong> <span>${totalPrice.toLocaleString('vi-VN')} VNĐ</span></div>`;

    // Discount
    if (voucherCode) {
        // Discount calculation would happen on the server
        html += `<div class="summary-item"><strong>Mã voucher:</strong> <span>${voucherCode}</span></div>`;
    }

    // Total
    html += `<div class="summary-total"><strong>Tổng cộng:</strong> <span>${totalPrice.toLocaleString('vi-VN')} VNĐ</span></div>`;

    summaryDiv.innerHTML = html;
}

courtSelect.addEventListener('change', loadTimeSlots);
bookingDateInput.addEventListener('change', loadTimeSlots);
document.getElementById('voucher_code').addEventListener('change', updateSummary);

// Set minimum date to today
const today = new Date().toISOString().split('T')[0];
bookingDateInput.min = today;
</script>
@endpush
@endsection
