@extends('layouts.app')

@section('title', 'Đặt sân định kỳ - SmashZone')

@section('content')
@php
    $bookingType = $bookingType ?? request('booking_type', 'weekly');
    $weekDays = [1 => 'Thứ hai', 2 => 'Thứ ba', 3 => 'Thứ tư', 4 => 'Thứ năm', 5 => 'Thứ sáu', 6 => 'Thứ bảy', 0 => 'Chủ nhật'];
    $selectedWeekDays = array_map('intval', old('days_of_week', request('days_of_week', [])));
    $selectedSlots = array_map('intval', old('time_slot_ids', request('time_slot_ids', array_filter([old('time_slot_id', request('time_slot_id'))]))));
    $selectedMonthDays = array_map('intval', old('days_of_month', request('days_of_month', [])));
@endphp
<style>
    .recurring-page{max-width:1180px;margin:auto;padding:32px 16px 56px}.recurring-layout{display:grid;grid-template-columns:minmax(0,1fr) 330px;gap:24px;align-items:start}.recurring-title{display:flex;gap:14px;align-items:flex-start;margin-bottom:24px}.recurring-title a{display:grid;place-items:center;width:42px;height:42px;border:1px solid #dce8e2;border-radius:12px;color:#10293a;text-decoration:none}.recurring-title h1{margin:0;font-size:28px;font-weight:800;color:#10293a}.recurring-title p{margin:5px 0;color:#64748b;font-size:14px}.recurring-card{overflow:hidden;border:1px solid #dce8e2;border-radius:18px;background:#fff;box-shadow:0 8px 28px rgba(6,59,42,.06)}.recurring-card+ .recurring-card{margin-top:24px}.card-head{display:flex;align-items:center;gap:11px;padding:19px 22px;border-bottom:1px solid #dce8e2}.card-head i{display:grid;place-items:center;width:32px;height:32px;border-radius:9px;background:#ddf9e9;color:#079552}.card-head h2{margin:0;font-size:17px;font-weight:800;color:#10293a}.card-body{padding:24px 22px}.booking-tag{display:inline-flex;align-items:center;gap:7px;margin-bottom:24px;padding:8px 11px;border-radius:99px;background:#e9fbf0;color:#067941;font-size:12px;font-weight:800}.booking-step{padding-bottom:24px;margin-bottom:24px;border-bottom:1px solid #edf2ef}.booking-step:last-of-type{border:0}.step-title{display:flex;align-items:center;gap:10px;margin:0 0 16px;font-size:15px;font-weight:800;color:#10293a}.step-number{display:grid;place-items:center;width:25px;height:25px;border-radius:50%;background:#ddf9e9;color:#047e43;font-size:12px}.form-label{font-size:13px;font-weight:700;color:#344b5a}.form-control,.form-select{min-height:46px;border-color:#dce8e2;border-radius:10px}.form-control:focus,.form-select:focus{border-color:#08c968;box-shadow:0 0 0 3px rgba(8,201,104,.12)}.date-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.day-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px}.month-grid{grid-template-columns:repeat(7,1fr)}.day-option{position:relative;display:block;cursor:pointer}.day-option input{position:absolute;opacity:0}.day-option span{display:flex;align-items:center;justify-content:center;min-height:48px;padding:8px;border:1px solid #dce8e2;border-radius:10px;color:#405868;font-size:13px;font-weight:700;text-align:center}.month-grid .day-option span{min-height:40px}.day-option input:checked+span{border-color:#08c968;background:#e9fff1;color:#057c42;box-shadow:inset 0 0 0 1px #08c968}.hint{margin:0 0 14px;color:#64748b;font-size:13px}.preview-btn,.confirm-btn{width:100%;min-height:50px;border:0;border-radius:12px;font-weight:800}.preview-btn{background:#08c968;color:#063b2a;box-shadow:0 10px 20px rgba(8,201,104,.25)}.preview-btn:hover{background:#04b85d;color:#fff}.summary{position:sticky;top:20px}.summary-row{display:flex;justify-content:space-between;gap:16px;padding:12px 0;border-bottom:1px solid #edf2ef;color:#64748b;font-size:13px}.summary-row strong{max-width:62%;text-align:right;color:#10293a}.summary-note{display:flex;gap:9px;margin-top:16px;padding:12px;border-radius:10px;background:#effaf3;color:#39715a;font-size:12px;line-height:1.55}.summary-note i{color:#08a758;font-size:15px}.conflicts{margin-bottom:20px;padding:16px;border:1px solid #ffd587;border-radius:12px;background:#fff9e9;color:#805b13;font-size:13px}.schedule-list{max-height:360px;overflow:auto;border:1px solid #dce8e2;border-radius:12px}.schedule-row{display:flex;justify-content:space-between;gap:12px;padding:13px 15px;border-bottom:1px solid #edf2ef;font-size:14px}.schedule-row:last-child{border:0}.schedule-row i{color:#08b75e;margin-right:7px}.schedule-row strong{color:#087c42}.preview-total{display:flex;justify-content:space-between;margin-top:16px;padding:16px;border-radius:12px;background:#e9fbf0;color:#23543a}.preview-total strong:last-child{color:#058846;font-size:20px}.confirm-btn{margin-top:16px;background:#08b95d;color:#fff}.confirm-btn:hover{background:#078c48}@media(max-width:991px){.recurring-layout{grid-template-columns:1fr}.summary{position:static}}@media(max-width:600px){.recurring-page{padding:20px 12px 40px}.card-body{padding:18px 16px}.date-grid{grid-template-columns:1fr}.day-grid{grid-template-columns:repeat(2,1fr)}.month-grid{grid-template-columns:repeat(5,1fr)}}
.day-option input:focus-visible + span { outline: 2px solid #072132; outline-offset: 3px; }
</style>
<main class="recurring-page">
    <div class="recurring-title"><a href="{{ route('courts.index') }}" aria-label="Quay lại"><i class="bi bi-chevron-left"></i></a><div><h1>Đặt sân định kỳ</h1><p>Thiết lập lịch chơi lặp lại và kiểm tra từng buổi trước khi xác nhận.</p></div></div>
    @if(session('booking_errors'))
        <div class="alert alert-danger"><strong>Một số lịch không còn khả dụng:</strong><ul class="mb-0 mt-2">@foreach(session('booking_errors') as $error)<li>{{ \Carbon\Carbon::parse($error['booking_date'])->format('d/m/Y') }} · {{ $error['message'] }}</li>@endforeach</ul></div>
    @endif
    @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
    <div class="recurring-layout"><section>
        <div class="recurring-card"><div class="card-head"><i class="bi bi-repeat"></i><h2>Thiết lập lịch đặt sân</h2></div><div class="card-body"><span class="booking-tag"><i class="bi bi-arrow-repeat"></i>{{ $bookingType === 'monthly' ? 'Đặt theo tháng' : 'Đặt theo tuần' }}</span>
        <form action="{{ route('bookings.recurring.preview') }}" method="POST">@csrf<input type="hidden" name="booking_type" value="{{ $bookingType }}">
            <div class="booking-step"><h3 class="step-title"><span class="step-number">1</span>Chọn sân và khung giờ</h3><div class="row g-3"><div class="col-md-6"><label class="form-label" for="court_id">Sân cầu lông</label>
                @if(isset($selectedCourt) && $selectedCourt)
                    <input type="hidden" name="court_id" value="{{ $selectedCourt->id }}">
                    <input class="form-control" id="court_id" value="{{ $selectedCourt->name }}{{ $selectedCourt->courtType ? ' · '.$selectedCourt->courtType->name : '' }}" readonly aria-label="Sân đã chọn">
                    <small class="text-muted">Sân được giữ theo lựa chọn ban đầu.</small>
                @else
                    <select class="form-select @error('court_id') is-invalid @enderror" id="court_id" name="court_id" required><option value="">Chọn sân</option>@foreach($courts as $court)<option value="{{ $court->id }}" @selected(old('court_id', request('court_id')) == $court->id)>{{ $court->name }}{{ $court->courtType ? ' · '.$court->courtType->name : '' }}</option>@endforeach</select>
                    @error('court_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                @endif
            </div><div class="col-12"><fieldset><legend class="form-label">Khung giờ mỗi buổi</legend>
<p class="hint">Chọn một hoặc nhiều khung giờ liền nhau. Ví dụ: 18:00–19:00 và 19:00–20:00 thành một buổi 18:00–20:00.</p>
<div class="day-grid">@foreach($timeSlots->sortBy('start_time') as $slot)
<label class="day-option"><input type="checkbox" name="time_slot_ids[]" value="{{ $slot->id }}" data-start="{{ substr($slot->start_time,0,5) }}" data-end="{{ substr($slot->end_time,0,5) }}" @checked(in_array($slot->id, $selectedSlots))><span>{{ substr($slot->start_time,0,5) }}–{{ substr($slot->end_time,0,5) }}</span></label>
@endforeach</div><p id="slot-selection-hint" class="small text-muted mt-2 mb-0" aria-live="polite"></p>
@error('time_slot_ids')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
</fieldset></div></div></div>
            <div class="booking-step"><h3 class="step-title"><span class="step-number">2</span>Chọn khoảng thời gian</h3><div class="date-grid"><div><label class="form-label" for="start_date">Ngày bắt đầu</label><input class="form-control @error('start_date') is-invalid @enderror" type="date" id="start_date" name="start_date" min="{{ today()->toDateString() }}" value="{{ old('start_date', request('start_date')) }}" required>@error('start_date')<div class="invalid-feedback">{{ $message }}</div>@enderror</div><div><label class="form-label" for="end_date">Ngày kết thúc</label><input class="form-control @error('end_date') is-invalid @enderror" type="date" id="end_date" name="end_date" min="{{ today()->toDateString() }}" value="{{ old('end_date', request('end_date')) }}" required>@error('end_date')<div class="invalid-feedback">{{ $message }}</div>@enderror</div></div></div>
            <div class="booking-step"><h3 class="step-title"><span class="step-number">3</span>{{ $bookingType === 'monthly' ? 'Chọn ngày lặp trong tháng' : 'Chọn các thứ lặp trong tuần' }}</h3><p class="hint">{{ $bookingType === 'monthly' ? 'Ngày không có trong tháng sẽ được hệ thống bỏ qua.' : 'Chọn ít nhất một thứ để tạo lịch trong khoảng thời gian đã chọn.' }}</p>
            @if($bookingType === 'monthly')
                <div class="day-grid month-grid">@for($day = 1; $day <= 31; $day++)<label class="day-option"><input type="checkbox" name="days_of_month[]" value="{{ $day }}" @checked(in_array($day, $selectedMonthDays))><span>{{ $day }}</span></label>@endfor</div>
                @error('days_of_month')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
            @else
                <div class="day-grid">@foreach($weekDays as $value => $name)<label class="day-option"><input type="checkbox" name="days_of_week[]" value="{{ $value }}" @checked(in_array($value, $selectedWeekDays))><span>{{ $name }}</span></label>@endforeach</div>
                @error('days_of_week')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
            @endif
            </div>
            <div class="booking-step"><h3 class="step-title"><span class="step-number">4</span>Mã khuyến mãi <small class="text-muted fw-normal">(tùy chọn)</small></h3><input class="form-control" name="voucher_code" value="{{ old('voucher_code', request('voucher_code')) }}" maxlength="50" placeholder="Nhập mã voucher nếu có"></div>
            <button type="submit" class="preview-btn"><i class="bi bi-calendar2-check me-2"></i>Kiểm tra lịch dự kiến</button>
        </form></div></div>
        @if($preview ?? null)
            @include('bookings.recurring-preview')
        @endif
    </section><aside class="recurring-card summary"><div class="card-head"><i class="bi bi-receipt"></i><h2>Tóm tắt đặt sân</h2></div><div class="card-body"><div id="summary"><div class="summary-note"><i class="bi bi-info-circle"></i><span>Chọn đầy đủ thông tin để xem số lịch dự kiến.</span></div></div></div></aside></div>
</main>
@endsection
@push('scripts')
<script>
// Editing the setup or choices requires a new server-side review before confirmation.
document.querySelectorAll('form[action="{{ route('bookings.recurring.preview') }}"] input, form[action="{{ route('bookings.recurring.preview') }}"] select').forEach(field => {
    field.addEventListener('input', () => {
        document.getElementById('schedule-preview')?.setAttribute('hidden', '');
        document.getElementById('final-schedule')?.setAttribute('hidden', '');
    });
});
document.querySelectorAll('#schedule-preview select').forEach(field => {
    field.addEventListener('change', () => document.getElementById('final-schedule')?.setAttribute('hidden', ''));
});
(() => {
    const court = document.getElementById('court_id'), start = document.getElementById('start_date'), end = document.getElementById('end_date');
    const output = document.getElementById('summary'), hint = document.getElementById('slot-selection-hint');
    const slots = [...document.querySelectorAll('input[name="time_slot_ids[]"]')];
    const type = @json($bookingType), names = ['Chủ nhật','Thứ hai','Thứ ba','Thứ tư','Thứ năm','Thứ sáu','Thứ bảy'];
    const dayInputs = [...document.querySelectorAll(type === 'monthly' ? 'input[name="days_of_month[]"]' : 'input[name="days_of_week[]"]')];
    const row = (label, value) => {
        const line = document.createElement('div'); line.className = 'summary-row';
        const caption = document.createElement('span'); caption.textContent = label;
        const text = document.createElement('strong'); text.textContent = value;
        line.append(caption, text); return line;
    };
    const update = () => {
        const chosen = slots.filter(input => input.checked);
        const adjacent = chosen.every((input, index) => index === 0 || chosen[index - 1].dataset.end === input.dataset.start);
        const error = !chosen.length ? 'Chọn ít nhất một khung giờ.' : !adjacent ? 'Các khung giờ phải liền nhau, không cách quãng hoặc chồng lấn.' : '';
        slots.forEach(input => input.setCustomValidity(''));
        if (slots[0]) slots[0].setCustomValidity(error);
        hint.textContent = error || chosen[0].dataset.start + '–' + chosen[chosen.length - 1].dataset.end + ' · ' + chosen.length + ' khung giờ mỗi buổi';
        hint.className = 'small mt-2 mb-0 ' + (error ? 'text-danger' : 'text-muted');
        end.min = start.value || @json(today()->toDateString());
        const days = dayInputs.filter(input => input.checked).map(input => Number(input.value));
        let count = 0;
        if (start.value && end.value) {
            const date = new Date(start.value + 'T00:00:00'), last = new Date(end.value + 'T00:00:00');
            while (date <= last) { if (days.includes(type === 'monthly' ? date.getDate() : date.getDay())) count++; date.setDate(date.getDate() + 1); }
        }
        output.replaceChildren(
            row('Sân', court.options ? court.selectedOptions[0]?.textContent || '—' : court.value),
            row('Giờ mỗi buổi', chosen.length && adjacent ? chosen[0].dataset.start + '–' + chosen[chosen.length - 1].dataset.end : 'Chưa chọn hợp lệ'),
            row('Lặp lại', days.map(day => type === 'monthly' ? 'Ngày ' + day : names[day]).join(', ') || '—'),
            row('Lịch dự kiến', count + ' buổi'), row('Tổng khung giờ', count * chosen.length + ' khung giờ')
        );
    };
    [court, start, end, ...slots, ...dayInputs].forEach(input => input.addEventListener('change', update));
    update();
})();
</script>
@endpush
