@extends('layouts.employee')
@section('title','Sự cố sân - SmashZone')
@section('page_heading','Báo cáo sự cố sân')
@section('content')
@include('partials.incident-ui')
<div class="d-flex flex-wrap gap-2 mb-3">
<a class="sz-action sz-action--small" href="{{ route('admin.incidents.bulk') }}">Sự cố theo khung giờ</a>
<a class="sz-action sz-action--small" href="{{ route('incident-tickets.index') }}">Yêu cầu từ khách</a>
</div>
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<details class="sz-more sz-work-panel mb-3" @if($errors->any() || request('court_id')) open @endif>
<summary class="sz-action sz-action--primary"><i class="bi bi-plus-lg" aria-hidden="true"></i>Tạo báo cáo sự cố</summary>
<form method="POST" action="{{ route('employee.incidents.store') }}" enctype="multipart/form-data" class="mt-3" data-venue-submit>@csrf
<div class="row g-3">
<div class="col-md-4"><label class="form-label" for="report-court">Sân</label><select id="report-court" name="court_id" class="form-select" required>@foreach($courts as $court)<option value="{{ $court->id }}" @selected(old('court_id', request('court_id')) == $court->id)>{{ $court->name }}</option>@endforeach</select></div>
<div class="col-md-4"><label class="form-label" for="report-type">Loại sự cố</label><input id="report-type" name="type" class="form-control" value="{{ old('type') }}" required></div>
<div class="col-md-4"><label class="form-label" for="report-severity">Mức độ</label><select id="report-severity" name="severity" class="form-select">@foreach(['LOW'=>'Thấp','MEDIUM'=>'Trung bình','HIGH'=>'Cao','CRITICAL'=>'Nghiêm trọng'] as $code=>$label)<option value="{{ $code }}" @selected(old('severity', 'LOW') === $code)>{{ $label }}</option>@endforeach</select></div>
<div class="col-12"><label class="form-label" for="report-description">Mô tả</label><textarea id="report-description" name="description" class="form-control" rows="2" required>{{ old('description') }}</textarea></div>
<div class="col-12">@include('partials.media-upload', ['name'=>'images[]', 'label'=>'Ảnh minh chứng', 'accept'=>'image/jpeg,image/png,image/webp', 'hint'=>'Mỗi ảnh tối đa 4MB.'])</div>
<div class="col-md-6"><label for="handling-direction" class="form-label">Hướng xử lý đề xuất</label><textarea id="handling-direction" name="handling_direction" maxlength="1000" rows="3" class="form-control @error('handling_direction') is-invalid @enderror">{{ old('handling_direction') }}</textarea>@error('handling_direction')<div class="invalid-feedback">{{ $message }}</div>@enderror<div class="form-text">Ghi nhận đề xuất; không tự động đổi sân, đổi lịch hoặc hoàn tiền.</div></div>
<div class="col-md-6"><label for="staff-note" class="form-label">Ghi chú</label><textarea id="staff-note" name="staff_note" maxlength="1000" rows="3" class="form-control @error('staff_note') is-invalid @enderror">{{ old('staff_note') }}</textarea>@error('staff_note')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
<div class="col-12"><p class="alert alert-warning mb-2">Mức độ Cao hoặc Nghiêm trọng sẽ chuyển sân sang trạng thái bảo trì theo quy trình hiện tại.</p></div>
</div>
<button class="sz-action sz-action--primary sz-action--small"><i class="bi bi-send" aria-hidden="true"></i>Gửi báo cáo</button>
</form></details>
<div class="staff-card table-responsive"><table class="table staff-table mb-0"><thead><tr><th>Mã</th><th>Sân</th><th>Mức độ</th><th>Trạng thái</th></tr></thead><tbody>
@forelse($incidents as $incident)<tr><td>{{ $incident->incident_code }}<details class="mt-2"><summary class="small">Nội dung báo cáo</summary><p class="small text-break mt-2" style="white-space:pre-wrap">{{ $incident->description }}</p>@if($incident->resolution_note)<p class="small text-break" style="white-space:pre-wrap">{{ $incident->resolution_note }}</p>@endif</details></td><td>{{ $incident->court?->name }}<br><small>{{ $incident->type }}</small></td><td>{{ ['LOW'=>'Thấp','MEDIUM'=>'Trung bình','HIGH'=>'Cao','CRITICAL'=>'Nghiêm trọng'][$incident->severity] ?? $incident->severity }}</td><td><span class="badge {{ in_array($incident->status, ['RESOLVED','CLOSED']) ? 'bg-success' : ($incident->status === 'IN_PROGRESS' ? 'bg-primary' : 'bg-secondary') }}">{{ ['OPEN'=>'Mới','IN_PROGRESS'=>'Đang xử lý','RESOLVED'=>'Đã giải quyết','CLOSED'=>'Đã đóng'][$incident->status] ?? $incident->status }}</span></td></tr>
@empty<tr><td colspan="4" class="text-muted text-center py-4">Chưa có báo cáo sự cố.</td></tr>@endforelse
</tbody></table></div><div class="mt-3">{{ $incidents->links() }}</div>
@endsection
