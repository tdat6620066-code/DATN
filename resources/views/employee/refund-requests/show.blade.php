@extends('layouts.employee')

@section('title', 'Chi tiết yêu cầu hoàn tiền - SmashZone')
@section('page_heading', 'Xử lý hoàn tiền')

@push('styles')
<style>
.refund-detail-grid{display:grid;grid-template-columns:1.45fr 1fr;gap:20px}.refund-detail-card{padding:24px}.refund-detail-card h2{margin:0 0 20px;font-size:17px;font-weight:800}.refund-info{display:grid;grid-template-columns:165px 1fr;gap:0}.refund-info dt,.refund-info dd{margin:0;padding:13px 0;border-bottom:1px solid #edf2ef}.refund-info dt{color:#72848b;font-size:11px;font-weight:800;text-transform:uppercase}.refund-info dd{font-size:13px}.refund-amount{color:#00a561;font-size:20px;font-weight:800}.review-form label{color:#526970;font-size:11px;font-weight:800}.review-form .form-control,.review-form .form-select{border-color:#dce6e1;border-radius:9px;font-size:13px}.review-form .form-control:focus,.review-form .form-select:focus{border-color:#09bc6d;box-shadow:0 0 0 3px rgba(9,188,109,.12)}@media(max-width:850px){.refund-detail-grid{grid-template-columns:1fr}.refund-info{grid-template-columns:1fr}.refund-info dt{padding-bottom:2px;border:0}.refund-info dd{padding-top:2px}}
</style>
@endpush

@section('content')
    <div class="staff-page-title"><a class="text-decoration-none text-success small fw-bold" href="{{ route('employee.refund-requests.index') }}">← Quay lại danh sách</a><h1 class="mt-2">Yêu cầu #{{ $refundRequest->id }}</h1><p>Đối chiếu booking và đưa ra quyết định xử lý.</p></div>
    <div class="refund-detail-grid"><div class="staff-card refund-detail-card">
        <h2>Thông tin yêu cầu</h2><dl class="refund-info mb-0">
            <dt>Booking</dt><dd>{{ $refundRequest->booking->booking_code }} <span class="staff-badge ms-2">{{ \App\Support\StatusLabel::get($refundRequest->booking->status) }}</span></dd>
            <dt>Khách hàng</dt><dd>{{ $refundRequest->requester->name }}<br><small class="text-muted">{{ $refundRequest->requester->email }}</small></dd>
            <dt>Đã thanh toán</dt><dd>{{ number_format($refundRequest->booking->payment?->amount ?? 0) }} đ</dd>
            <dt>Yêu cầu hoàn</dt><dd><span class="refund-amount">{{ number_format($refundRequest->amount) }} đ</span></dd>
            <dt>Lý do</dt><dd>{{ $refundRequest->reason }}</dd>
            <dt>Thông tin bổ sung</dt><dd>{{ $refundRequest->supporting_information ?: 'Chưa có' }}</dd>
            <dt>Trạng thái</dt><dd><span class="staff-badge">{{ \App\Support\StatusLabel::get($refundRequest->status) }}</span></dd>
        </dl>
    </div>
    <div class="staff-card refund-detail-card">
        <h2>Xử lý yêu cầu</h2>
        @if(in_array($refundRequest->status, ['PENDING', 'NEEDS_INFO']))
        <form class="review-form" method="POST" action="{{ route('employee.refund-requests.review', $refundRequest) }}">@csrf
            <label class="form-label">Quyết định</label>
            <select name="decision" class="form-select mb-3" required>
                <option value="APPROVED">Phê duyệt</option><option value="REJECTED">Từ chối</option><option value="NEEDS_INFO">Yêu cầu bổ sung</option>
            </select>
            <label class="form-label">Ghi chú quyết định</label><textarea name="decision_note" class="form-control mb-3" rows="3"></textarea>
            <label class="form-label">Thông tin cần bổ sung</label><textarea name="requested_information" class="form-control mb-3" rows="3"></textarea>
            <button class="staff-button staff-button-primary"><i class="bi bi-check2-circle"></i>Xác nhận xử lý</button>
        </form>
        @else<p class="mb-0">Yêu cầu đã được xử lý.</p>@endif
    </div></div>
@endsection
