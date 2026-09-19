@extends('layouts.employee')
@section('page_heading', 'Khách hàng')
@section('content')
<header class="sz-staff-heading"><div><h1>Khách hàng</h1><p>Tra cứu thông tin liên hệ phục vụ lịch sân.</p></div></header>
<form method="GET" class="d-flex gap-2 mb-4"><input type="hidden" name="panel" value="customers"><input type="search" name="search" class="form-control" value="{{ request('search') }}" placeholder="Tên hoặc số điện thoại" aria-label="Tìm khách hàng"><button class="btn btn-primary">Tìm</button></form>
<div class="staff-card table-responsive"><table class="table staff-table"><thead><tr><th>Khách hàng</th><th>Điện thoại</th><th>Booking</th><th>Trạng thái</th></tr></thead><tbody>@forelse($customers as $customer)<tr><td>{{ $customer->name }}</td><td>{{ $customer->phone }}</td><td>{{ $customer->bookings_count }}</td><td>{{ $customer->status === 'ACTIVE' ? 'Hoạt động' : 'Đã khóa' }}</td></tr>@empty<tr><td colspan="4" class="text-center py-4">Không tìm thấy khách hàng.</td></tr>@endforelse</tbody></table></div><div class="mt-3">{{ $customers->links() }}</div>
@endsection
