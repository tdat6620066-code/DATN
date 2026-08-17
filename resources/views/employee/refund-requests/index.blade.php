@extends('layouts.employee')

@section('title', 'Yêu cầu hoàn tiền - SmashZone')
@section('page_heading', 'Hủy & hoàn tiền')

@section('content')
<div class="staff-page-title"><h1>Yêu cầu hủy / hoàn tiền</h1><p>Kiểm tra thông tin, chính sách và xử lý yêu cầu của khách hàng.</p></div>
    <div class="staff-card"><div class="table-responsive"><table class="table staff-table align-middle">
        <thead><tr><th>#</th><th>Booking</th><th>Khách hàng</th><th>Số tiền</th><th>Lý do</th><th>Trạng thái</th><th></th></tr></thead>
        <tbody>
        @forelse($requests as $item)
            <tr>
                <td>{{ $item->id }}</td><td>{{ $item->booking->booking_code }}</td><td>{{ $item->requester->name }}</td>
                <td>{{ number_format($item->amount) }} đ</td><td>{{ Str::limit($item->reason, 60) }}</td>
                <td><span class="staff-badge">{{ \App\Support\StatusLabel::get($item->status) }}</span></td>
                <td><a class="staff-button" href="{{ route('employee.refund-requests.show', $item) }}"><i class="bi bi-eye"></i>Mở yêu cầu</a></td>
            </tr>
        @empty
            <tr><td colspan="7" class="text-center py-4">Chưa có yêu cầu.</td></tr>
        @endforelse
        </tbody>
    </table></div></div>
    <div class="mt-3">{{ $requests->links() }}</div>
@endsection
