@extends('layouts.employee')

@section('title', 'Quản lý trạng thái sân - SmashZone')
@section('page_heading', 'Quản lý sân')

@push('styles')
<style>
.court-state{display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:100px;font-size:10px;font-weight:800}.court-state:before{content:'';width:7px;height:7px;border-radius:50%;background:currentColor}.court-state.available{background:#e3f8ed;color:#009557}.court-state.locked{background:#fee9e7;color:#d23d32}.court-state.maintenance{background:#fff1d9;color:#be6b00}.availability-text{color:#71828b;font-size:11px}.availability-text.occupied{color:#1378ad;font-weight:800}
</style>
@endpush

@section('content')
<div class="staff-page-title"><h1>Trạng thái sân</h1><p>Kiểm soát khả dụng, khóa sân và lịch bảo trì.</p></div>
<div class="staff-card"><div class="table-responsive"><table class="table staff-table align-middle">
    <thead><tr><th>Mã sân</th><th>Tên sân</th><th>Loại sân</th><th>Vận hành</th><th>Sử dụng hiện tại</th><th>Lý do</th><th>Cập nhật</th><th></th></tr></thead>
    <tbody>@forelse($courts as $court)
    <tr>
        <td class="fw-bold">{{ $court->code }}</td><td>{{ $court->name }}</td><td>{{ $court->courtType->name }}</td>
        <td><span class="court-state {{ strtolower($court->operational_status) }}">{{ \App\Support\StatusLabel::get($court->operational_status) }}</span></td>
        <td><span class="availability-text {{ strtolower($court->availability_status) }}">{{ \App\Support\StatusLabel::get($court->availability_status) }}</span></td>
        <td class="text-muted">{{ Str::limit($court->status_reason ?: '—', 45) }}</td>
        <td class="text-muted small">{{ $court->status_updated_at?->format('d/m/Y H:i') ?? '—' }}</td>
        <td><a class="staff-button" href="{{ route('employee.courts.edit', $court) }}"><i class="bi bi-pencil-square"></i>Cập nhật</a></td>
    </tr>
    @empty<tr><td colspan="8" class="text-center py-5 text-muted">Chưa có sân.</td></tr>@endforelse</tbody>
</table></div></div><div class="mt-3">{{ $courts->links() }}</div>
@endsection
